<?php
declare(strict_types=1);

final class SmtpMailer
{
    public static function settings(bool $withSecret = false): array
    {
        $row = db()->query('SELECT * FROM mail_settings WHERE id=1')->fetch() ?: [];
        $defaults = [
            'id'=>1,'active'=>0,'host'=>'','port'=>587,'encryption'=>'tls','username'=>'','auth_method'=>'login',
            'from_name'=>(string)setting('property_name','StayPilot'),'from_email'=>(string)setting('contact_email',''),
            'reply_to'=>'','timeout_seconds'=>15,'password_encrypted'=>'','updated_at'=>null,
        ];
        $out = array_merge($defaults,$row);
        $out['has_password'] = !empty($out['password_encrypted']);
        if ($withSecret) $out['password'] = Crypto::decrypt($out['password_encrypted'] ?? null);
        unset($out['password_encrypted']);
        return $out;
    }

    public static function save(array $data): array
    {
        $old = self::settings(false);
        $host = trim((string)($data['host'] ?? ''));
        $port = max(1,min(65535,(int)($data['port'] ?? 587)));
        $encryption = in_array(($data['encryption'] ?? 'tls'),['none','tls','ssl'],true) ? (string)$data['encryption'] : 'tls';
        $auth = in_array(($data['auth_method'] ?? 'login'),['none','login','plain'],true) ? (string)$data['auth_method'] : 'login';
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $existing = db()->query('SELECT password_encrypted FROM mail_settings WHERE id=1')->fetchColumn();
        $encrypted = $password !== '' ? Crypto::encrypt($password) : ($existing ?: null);
        $fromName = trim((string)($data['from_name'] ?? ''));
        $fromEmail = trim((string)($data['from_email'] ?? ''));
        $replyTo = trim((string)($data['reply_to'] ?? ''));
        if ($fromEmail !== '' && !filter_var($fromEmail,FILTER_VALIDATE_EMAIL)) throw new ValidationException('Die SMTP-Absenderadresse ist ungültig.');
        if ($replyTo !== '' && !filter_var($replyTo,FILTER_VALIDATE_EMAIL)) throw new ValidationException('Die Antwortadresse ist ungültig.');
        if ((int)($data['active'] ?? 0) && ($host === '' || $fromEmail === '')) throw new ValidationException('Für aktives SMTP sind Server und Absenderadresse erforderlich.');
        $stmt = db()->prepare('INSERT INTO mail_settings(id,active,host,port,encryption,username,password_encrypted,auth_method,from_name,from_email,reply_to,timeout_seconds,updated_by) VALUES(1,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE active=VALUES(active),host=VALUES(host),port=VALUES(port),encryption=VALUES(encryption),username=VALUES(username),password_encrypted=VALUES(password_encrypted),auth_method=VALUES(auth_method),from_name=VALUES(from_name),from_email=VALUES(from_email),reply_to=VALUES(reply_to),timeout_seconds=VALUES(timeout_seconds),updated_by=VALUES(updated_by)');
        $stmt->execute([
            normalize_bool($data['active'] ?? 0),$host,$port,$encryption,$username,$encrypted,$auth,$fromName,$fromEmail,$replyTo,
            max(5,min(60,(int)($data['timeout_seconds'] ?? 15))),Auth::user()['id'] ?? null,
        ]);
        $new = self::settings(false);
        AuditLogger::record('smtp_settings',1,'update',$old,$new,'SMTP-Einstellungen gespeichert; Passwortinhalt geschützt');
        return $new;
    }

    public static function test(array $override = []): array
    {
        $cfg = array_merge(self::settings(true),$override);
        $client = new SmtpConnection($cfg);
        try {
            $client->connect();
            $client->quit();
            return ['ok'=>true,'message'=>'SMTP-Verbindung und Anmeldung waren erfolgreich.','transcript'=>$client->safeTranscript()];
        } catch (Throwable $e) {
            $client->close();
            throw new RuntimeException('SMTP-Test fehlgeschlagen: '.$e->getMessage());
        }
    }

    public static function send(string $to, string $subject, string $text, ?string $html = null, array $attachments = []): array
    {
        if (!filter_var($to,FILTER_VALIDATE_EMAIL)) throw new ValidationException('Die Empfängeradresse ist ungültig.');
        $cfg = self::settings(true);
        if (!(int)$cfg['active']) throw new RuntimeException('SMTP ist nicht aktiviert.');
        if ($cfg['host'] === '' || $cfg['from_email'] === '') throw new RuntimeException('SMTP ist unvollständig konfiguriert.');
        $client = new SmtpConnection($cfg);
        try {
            $client->connect();
            $messageId = '<'.bin2hex(random_bytes(12)).'@'.self::domainFromEmail((string)$cfg['from_email']).'>';
            $message = self::buildMessage($cfg,$to,$subject,$text,$html,$messageId,$attachments);
            $client->sendMessage((string)$cfg['from_email'],$to,$message);
            $client->quit();
            return ['ok'=>true,'message_id'=>$messageId,'transcript'=>$client->safeTranscript()];
        } catch (Throwable $e) {
            $client->close();
            throw new RuntimeException('E-Mail-Versand fehlgeschlagen: '.$e->getMessage());
        }
    }

    private static function encodeHeader(string $value): string
    {
        $value=trim(preg_replace('/[\r\n]+/u',' ',$value)??$value);
        if($value==='')return '';
        if(function_exists('mb_encode_mimeheader'))return mb_encode_mimeheader($value,'UTF-8','B',"\r\n");
        if(preg_match('/^[\x20-\x7E]+$/',$value))return $value;
        return '=?UTF-8?B?'.base64_encode($value).'?=';
    }

    private static function domainFromEmail(string $email): string
    {
        $domain = substr(strrchr($email,'@') ?: '',1);
        return preg_match('/^[A-Za-z0-9.-]+$/',$domain) ? $domain : 'staypilot.local';
    }

    private static function buildMessage(array $cfg,string $to,string $subject,string $text,?string $html,string $messageId,array $attachments=[]): string
    {
        $fromName = trim((string)$cfg['from_name']);
        $fromEmail = (string)$cfg['from_email'];
        $encodedName = $fromName !== '' ? self::encodeHeader($fromName) . ' ' : '';
        $headers = [
            'Date: '.date(DATE_RFC2822),
            'Message-ID: '.$messageId,
            'From: '.$encodedName.'<'.$fromEmail.'>',
            'To: <'.$to.'>',
            'Subject: '.self::encodeHeader($subject),
            'MIME-Version: 1.0',
            'X-Mailer: StayPilot/'.(string)(config()['app_version'] ?? '2.2.0'),
        ];
        if (!empty($cfg['reply_to'])) $headers[] = 'Reply-To: <'.$cfg['reply_to'].'>';
        $validAttachments=[];
        foreach($attachments as $attachment){
            if(!is_array($attachment))continue;
            $path=(string)($attachment['path']??'');
            if($path===''||!is_file($path)||!is_readable($path))throw new RuntimeException('Ein E-Mail-Anhang ist nicht lesbar.');
            $name=basename((string)($attachment['name']??basename($path)));
            $mime=(string)($attachment['mime']??'application/octet-stream');
            if(!preg_match('/^[A-Za-z0-9._ -]{1,190}$/',$name))$name='document.pdf';
            if(!preg_match('#^[A-Za-z0-9.+-]+/[A-Za-z0-9.+-]+$#',$mime))$mime='application/octet-stream';
            $validAttachments[]=['path'=>$path,'name'=>$name,'mime'=>$mime];
        }
        $alternative = function(string $boundary) use($text,$html): string {
            $part='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($text));
            if($html!==null&&$html!=='')$part.='--'.$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($html));
            return $part.'--'.$boundary."--\r\n";
        };
        if($validAttachments){
            $mixed='sp_mix_'.bin2hex(random_bytes(10));$alt='sp_alt_'.bin2hex(random_bytes(10));
            $headers[]='Content-Type: multipart/mixed; boundary="'.$mixed.'"';
            $body='--'.$mixed."\r\nContent-Type: multipart/alternative; boundary=\"".$alt."\"\r\n\r\n".$alternative($alt);
            foreach($validAttachments as $attachment){
                $content=file_get_contents($attachment['path']);if($content===false)throw new RuntimeException('Ein E-Mail-Anhang konnte nicht gelesen werden.');
                $encodedName=self::encodeHeader($attachment['name']);
                $body.='--'.$mixed."\r\nContent-Type: ".$attachment['mime'].'; name="'.$encodedName."\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"".$encodedName."\"\r\n\r\n".chunk_split(base64_encode($content));
            }
            $body.='--'.$mixed."--\r\n";
        } elseif($html!==null&&$html!=='') {
            $boundary='sp_'.bin2hex(random_bytes(12));$headers[]='Content-Type: multipart/alternative; boundary="'.$boundary.'"';$body=$alternative($boundary);
        } else {
            $headers[]='Content-Type: text/plain; charset=UTF-8';$headers[]='Content-Transfer-Encoding: base64';$body=chunk_split(base64_encode($text));
        }
        return implode("\r\n",$headers)."\r\n\r\n".$body;
    }
}

final class SmtpConnection
{
    private array $cfg;
    /** @var resource|null */
    private $socket = null;
    private array $transcript = [];

    public function __construct(array $cfg) { $this->cfg = $cfg; }

    public function connect(): void
    {
        $host = trim((string)($this->cfg['host'] ?? ''));
        $port = (int)($this->cfg['port'] ?? 587);
        if ($host === '') throw new RuntimeException('SMTP-Server fehlt.');
        $scheme = ($this->cfg['encryption'] ?? 'tls') === 'ssl' ? 'ssl://' : '';
        $timeout = max(5,min(60,(int)($this->cfg['timeout_seconds'] ?? 15)));
        $errno=0;$errstr='';
        $this->socket = @stream_socket_client($scheme.$host.':'.$port,$errno,$errstr,$timeout,STREAM_CLIENT_CONNECT);
        if (!$this->socket) throw new RuntimeException("Verbindung zu {$host}:{$port} fehlgeschlagen ({$errno}: {$errstr}).");
        stream_set_timeout($this->socket,$timeout);
        $this->expect([220]);
        $this->command('EHLO '.self::localHost(),[250]);
        if (($this->cfg['encryption'] ?? 'tls') === 'tls') {
            $this->command('STARTTLS',[220]);
            if (!@stream_socket_enable_crypto($this->socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS-Verschlüsselung konnte nicht aktiviert werden.');
            $this->command('EHLO '.self::localHost(),[250]);
        }
        $auth = (string)($this->cfg['auth_method'] ?? 'login');
        $username = (string)($this->cfg['username'] ?? '');
        $password = (string)($this->cfg['password'] ?? '');
        if ($auth !== 'none' && $username !== '') {
            if ($auth === 'plain') {
                $this->command('AUTH PLAIN '.base64_encode("\0{$username}\0{$password}"),[235],true);
            } else {
                $this->command('AUTH LOGIN',[334]);
                $this->command(base64_encode($username),[334],true);
                $this->command(base64_encode($password),[235],true);
            }
        }
    }

    public function sendMessage(string $from,string $to,string $message): void
    {
        $this->command('MAIL FROM:<'.$from.'>',[250]);
        $this->command('RCPT TO:<'.$to.'>',[250,251]);
        $this->command('DATA',[354]);
        $safe = preg_replace('/(?m)^\./','..',$message) ?? $message;
        $this->write(rtrim($safe,"\r\n")."\r\n.\r\n");
        $this->expect([250]);
    }

    public function quit(): void
    {
        if ($this->socket) {
            try { $this->command('QUIT',[221]); } catch (Throwable) {}
            $this->close();
        }
    }

    public function close(): void
    {
        if (is_resource($this->socket)) fclose($this->socket);
        $this->socket = null;
    }

    public function safeTranscript(): array { return array_slice($this->transcript,-30); }

    private function command(string $command,array $expected,bool $secret=false): string
    {
        $this->transcript[] = 'C: '.($secret?'[geschützt]':$command);
        $this->write($command."\r\n");
        return $this->expect($expected);
    }

    private function write(string $data): void
    {
        if (!$this->socket) throw new RuntimeException('Keine aktive SMTP-Verbindung.');
        $length = strlen($data);$written=0;
        while ($written<$length) {
            $n = fwrite($this->socket,substr($data,$written));
            if ($n === false || $n === 0) throw new RuntimeException('SMTP-Verbindung wurde beim Schreiben unterbrochen.');
            $written += $n;
        }
    }

    private function expect(array $expected): string
    {
        if (!$this->socket) throw new RuntimeException('Keine aktive SMTP-Verbindung.');
        $response='';$code=0;
        while (($line=fgets($this->socket,8192)) !== false) {
            $response .= $line;
            $this->transcript[] = 'S: '.rtrim($line);
            if (preg_match('/^(\d{3})([ -])/',$line,$m)) {
                $code=(int)$m[1];
                if ($m[2] === ' ') break;
            }
        }
        $meta = stream_get_meta_data($this->socket);
        if ($response === '' && !empty($meta['timed_out'])) throw new RuntimeException('Zeitüberschreitung bei der SMTP-Antwort.');
        if (!in_array($code,$expected,true)) throw new RuntimeException('SMTP antwortete unerwartet: '.trim($response));
        return $response;
    }

    private static function localHost(): string
    {
        $host = gethostname() ?: 'localhost';
        return preg_match('/^[A-Za-z0-9.-]+$/',$host) ? $host : 'localhost';
    }
}
