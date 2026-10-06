<?php
declare(strict_types=1);

/**
 * Kleine, abhängungsfreie PDF-Ausgabe für revisionssichere Textdokumente.
 * Verwendet nur die PDF-Basisschrift Helvetica und Windows-1252-Zeichen.
 */
final class SimplePdf
{
    public static function create(string $title, array $lines): string
    {
        $pages=[];$page=[];$y=790;
        $append=function(string $text,int $size=10,bool $bold=false,int $indent=0) use (&$pages,&$page,&$y):void{
            $wrapped=SimplePdf::wrap($text,$size,$indent);
            foreach($wrapped as $line){
                if($y<55){$pages[]=$page;$page=[];$y=790;}
                $page[]=['text'=>$line,'size'=>$size,'bold'=>$bold,'x'=>50+$indent,'y'=>$y];
                $y-=max(14,$size+5);
            }
        };
        $append($title,18,true);$y-=6;
        foreach($lines as $line){
            if(is_array($line)){$append((string)($line['text']??''),(int)($line['size']??10),(bool)($line['bold']??false),(int)($line['indent']??0));if(!empty($line['space']))$y-=(int)$line['space'];}
            else $append((string)$line,10,false);
        }
        if($page||!$pages)$pages[]=$page;
        return self::build($pages);
    }


    /**
     * Erzeugt eine dokumentnaehere PDF aus der HTML-A4-Vorschau.
     * Diese Ausgabe ist weiterhin abhaengigkeitsfrei, uebernimmt aber Logo,
     * Akzentlinie, Ueberschriften, Absaetze und einfache Tabellen statt nur Text.
     */
    public static function createFromHtml(string $title, string $html): string
    {
        if (trim($html) === '') {
            return self::create($title, []);
        }

        $parsed = self::parseDocumentHtml($title, $html);
        return self::buildDocument($parsed);
    }

    private static function parseDocumentHtml(string $title, string $html): array
    {
        $accent = '#2563eb';
        if (preg_match('/border-bottom\s*:\s*[^;]*?(#[0-9a-f]{3,6})/i', $html, $m)) {
            $accent = $m[1];
        }
        $marginMm = 24;
        if (preg_match('/\.page\s*\{[^}]*padding\s*:\s*([0-9.]+)mm/is', $html, $m)) {
            $marginMm = max(8, min(50, (float)$m[1]));
        }
        $margin = $marginMm * 2.834645669;

        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return self::parseDocumentHtmlFallback($title, $html, $accent, $margin);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $logo = null;
        $img = $xpath->query('//img')->item(0);
        if ($img instanceof DOMElement) {
            $src = (string)$img->getAttribute('src');
            $logoPath = self::resolveImagePath($src);
            if ($logoPath !== null && is_file($logoPath)) {
                $logo = [
                    'path' => $logoPath,
                    'width' => self::styleMaxWidth($img->getAttribute('style'), 120),
                ];
            }
        }

        $h1 = $xpath->query('//h1')->item(0);
        $docTitle = $h1 ? trim($h1->textContent) : $title;
        if ($docTitle === '') {
            $docTitle = $title;
        }

        $headerNode = $xpath->query('//section[contains(@class,"head")]')->item(0);
        $bodyNode = $xpath->query('//section[not(contains(@class,"head")) and not(contains(@class,"foot"))]')->item(0);
        $footerNode = $xpath->query('//section[contains(@class,"foot")]')->item(0);
        $headerItems = [];
        if ($headerNode instanceof DOMElement) {
            $headerClone = $headerNode->cloneNode(true);
            if ($headerClone instanceof DOMElement) {
                foreach (iterator_to_array($headerClone->getElementsByTagName('h1')) as $h) {
                    if ($h->parentNode) { $h->parentNode->removeChild($h); }
                }
                foreach (iterator_to_array($headerClone->getElementsByTagName('img')) as $imgNode) {
                    if ($imgNode->parentNode) { $imgNode->parentNode->removeChild($imgNode); }
                }
                $headerItems = self::extractPdfItems($headerClone);
            }
        }
        $items = self::extractPdfItems($bodyNode ?: $dom->documentElement);
        $footer = $footerNode ? trim(self::domTextWithBreaks($footerNode)) : '';

        return [
            'title' => $docTitle,
            'accent' => $accent,
            'margin' => $margin,
            'logo' => $logo,
            'header_items' => $headerItems,
            'items' => $items,
            'footer' => $footer,
        ];
    }


    private static function parseDocumentHtmlFallback(string $title, string $html, string $accent, float $margin): array
    {
        $logo = null;
        if (preg_match('#<img\b[^>]*src=["\']([^"\']+)["\'][^>]*>#i', $html, $m)) {
            $logoPath = self::resolveImagePath($m[1]);
            if ($logoPath !== null && is_file($logoPath)) {
                $logo = ['path' => $logoPath, 'width' => 120];
            }
        }
        $docTitle = $title;
        if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $m)) {
            $candidate = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            if ($candidate !== '') {
                $docTitle = $candidate;
            }
        }
        $body = $html;
        $body = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $body) ?? $body;
        $body = preg_replace('#<section[^>]*class=["\'][^"\']*head[^"\']*["\'][^>]*>.*?</section>#is', '', $body) ?? $body;
        $footer = '';
        if (preg_match('#<section[^>]*class=["\'][^"\']*foot[^"\']*["\'][^>]*>(.*?)</section>#is', $body, $m)) {
            $footer = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $body = str_replace($m[0], '', $body);
        }
        $items = [];
        if (preg_match_all('#<h([23])[^>]*>(.*?)</h\1>|<p[^>]*>(.*?)</p>|<tr[^>]*>(.*?)</tr>#is', $body, $matches, PREG_SET_ORDER)) {
            $pendingRows = [];
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    if ($pendingRows) { $items[] = ['type' => 'table', 'rows' => $pendingRows]; $pendingRows = []; }
                    $items[] = ['type' => 'h' . $match[1], 'text' => trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))];
                } elseif (!empty($match[3])) {
                    if ($pendingRows) { $items[] = ['type' => 'table', 'rows' => $pendingRows]; $pendingRows = []; }
                    $txt = trim(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                    if ($txt !== '') { $items[] = ['type' => 'p', 'text' => $txt]; }
                } elseif (!empty($match[4])) {
                    $cells = [];
                    if (preg_match_all('#<t[dh][^>]*>(.*?)</t[dh]>#is', $match[4], $cellMatches)) {
                        foreach ($cellMatches[1] as $cell) { $cells[] = trim(html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); }
                    }
                    if ($cells) { $pendingRows[] = $cells; }
                }
            }
            if ($pendingRows) { $items[] = ['type' => 'table', 'rows' => $pendingRows]; }
        }
        if (!$items) {
            $plain = trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', "
", $body) ?? $body), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            foreach (preg_split('/\R+/', $plain) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') { $items[] = ['type' => 'p', 'text' => $line]; }
            }
        }
        return ['title' => $docTitle, 'accent' => $accent, 'margin' => $margin, 'logo' => $logo, 'header_items' => [], 'items' => $items, 'footer' => $footer];
    }

    private static function extractPdfItems(?DOMNode $node): array
    {
        if (!$node) {
            return [];
        }
        $items = [];
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                self::addParagraphTextItems($items, self::domTextWithBreaks($child));
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'head'], true)) {
                continue;
            }
            $class = ' ' . strtolower((string)$child->getAttribute('class')) . ' ';
            if ($tag === 'hr' || str_contains($class, ' page-break ')) {
                $items[] = ['type' => 'pagebreak'];
                continue;
            }
            if (in_array($tag, ['h1', 'h2', 'h3'], true)) {
                $text = trim(preg_replace('/\s+/u', ' ', $child->textContent) ?? '');
                if ($text !== '') {
                    $items[] = ['type' => $tag, 'text' => $text];
                }
                continue;
            }
            if (in_array($tag, ['p', 'div'], true)) {
                $hasBlockChild = false;
                foreach ($child->childNodes as $grand) {
                    if ($grand instanceof DOMElement && in_array(strtolower($grand->tagName), ['table', 'h1', 'h2', 'h3', 'p', 'div'], true)) {
                        $hasBlockChild = true;
                        break;
                    }
                }
                if ($hasBlockChild) {
                    $items = array_merge($items, self::extractPdfItems($child));
                } else {
                    self::addParagraphTextItems($items, self::domTextWithBreaks($child));
                }
                continue;
            }
            if ($tag === 'ul' || $tag === 'ol') {
                foreach ($child->getElementsByTagName('li') as $li) {
                    $text = trim(preg_replace('/\s+/u', ' ', self::domTextWithBreaks($li)) ?? '');
                    if ($text !== '') {
                        $items[] = ['type' => 'p', 'text' => '• ' . $text];
                    }
                }
                continue;
            }
            if ($tag === 'table') {
                $rows = [];
                foreach ($child->getElementsByTagName('tr') as $tr) {
                    $cells = [];
                    foreach ($tr->childNodes as $td) {
                        if ($td instanceof DOMElement && in_array(strtolower($td->tagName), ['td','th'], true)) {
                            $cells[] = trim(preg_replace('/\s+/u', ' ', $td->textContent) ?? '');
                        }
                    }
                    if ($cells) {
                        $rows[] = $cells;
                    }
                }
                if ($rows) {
                    $items[] = ['type' => 'table', 'rows' => $rows];
                }
                continue;
            }
            $items = array_merge($items, self::extractPdfItems($child));
        }
        return $items;
    }

    private static function resolveImagePath(string $src): ?string
    {
        $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        if ($src === '' || str_starts_with($src, 'data:') || preg_match('~^https?://~i', $src)) {
            return null;
        }
        $src = preg_replace('~^\.\./~', '', $src) ?? $src;
        $src = ltrim($src, '/');
        if (function_exists('root_path')) {
            return root_path($src);
        }
        return dirname(__DIR__, 2) . '/' . $src;
    }

    private static function styleMaxWidth(string $style, int $fallback): int
    {
        if (preg_match('/max-width\s*:\s*([0-9.]+)px/i', $style, $m) || preg_match('/width\s*:\s*([0-9.]+)px/i', $style, $m)) {
            return max(40, min(260, (int)round((float)$m[1] * 0.75)));
        }
        return $fallback;
    }


    private static function domTextWithBreaks(DOMNode $node): string
    {
        $parts = [];
        $walk = function(DOMNode $n) use (&$walk, &$parts): void {
            if ($n instanceof DOMText || $n instanceof DOMCdataSection) {
                $parts[] = $n->wholeText;
                return;
            }
            if ($n instanceof DOMElement) {
                $tag = strtolower($n->tagName);
                if ($tag === 'br') {
                    $parts[] = "\n";
                    return;
                }
                if (in_array($tag, ['p','div','li','tr'], true) && $parts && end($parts) !== "\n") {
                    $parts[] = "\n";
                }
                foreach ($n->childNodes as $child) {
                    $walk($child);
                }
                if (in_array($tag, ['p','div','li','tr'], true)) {
                    $parts[] = "\n";
                }
            }
        };
        $walk($node);
        $text = implode('', $parts);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace("/\r\n|\r/u", "\n", $text) ?? $text;
        $text = preg_replace("/[\t ]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/ *\n */u", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private static function addParagraphTextItems(array &$items, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        foreach (preg_split('/\n{1,}/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $items[] = ['type' => 'p', 'text' => $line];
            }
        }
    }

    private static function buildDocument(array $doc): string
    {
        $pageW = 595.28;
        $pageH = 841.89;
        $margin = (float)($doc['margin'] ?? 68);
        $contentW = $pageW - ($margin * 2);
        $accent = self::rgb((string)($doc['accent'] ?? '#2563eb'));
        $commands = [];
        $pages = [];
        $images = [];
        $y = $pageH - $margin;

        $newPage = function() use (&$pages, &$commands, &$y, $pageH, $margin): void {
            $pages[] = $commands;
            $commands = [];
            $y = $pageH - $margin;
        };
        $ensure = function(float $needed) use (&$y, $margin, $newPage): void {
            if ($y - $needed < $margin + 24) {
                $newPage();
            }
        };
        $addText = function(string $text, float $size = 10, bool $bold = false, float $space = 6, float $indent = 0) use (&$commands, &$y, $margin, $contentW, $ensure): void {
            $lines = SimplePdf::wrapForWidth($text, (int)$size, (int)($contentW - $indent));
            foreach ($lines as $line) {
                $ensure($size + 8);
                $commands[] = ['op' => 'text', 'text' => $line, 'x' => $margin + $indent, 'y' => $y, 'size' => $size, 'bold' => $bold];
                $y -= max(12, $size + 5);
            }
            $y -= $space;
        };

        if (!empty($doc['logo']['path'])) {
            $img = self::prepareImage((string)$doc['logo']['path']);
            if ($img) {
                $id = 'Im' . (count($images) + 1);
                $images[$id] = $img;
                $drawW = min((float)($doc['logo']['width'] ?? 90), $contentW * 0.42);
                $drawH = $drawW * ($img['height'] / max(1, $img['width']));
                $ensure($drawH + 12);
                $commands[] = ['op' => 'image', 'id' => $id, 'x' => $margin, 'y' => $y - $drawH, 'w' => $drawW, 'h' => $drawH];
                $y -= $drawH + 18;
            }
        }

        $addText((string)($doc['title'] ?? ''), 20, true, 10);
        foreach ((array)($doc['header_items'] ?? []) as $item) {
            $type = (string)($item['type'] ?? 'p');
            if ($type === 'table') {
                self::drawTable($commands, $y, $margin, $contentW, (array)($item['rows'] ?? []), $ensure, 8);
            } elseif ($type !== 'pagebreak') {
                $addText((string)($item['text'] ?? ''), 9, false, 4);
            }
        }
        $commands[] = ['op' => 'line', 'x1' => $margin, 'y1' => $y + 2, 'x2' => $margin + $contentW, 'y2' => $y + 2, 'w' => 2.2, 'rgb' => $accent];
        $y -= 24;

        foreach ((array)($doc['items'] ?? []) as $item) {
            $type = (string)($item['type'] ?? 'p');
            if ($type === 'h1' || $type === 'h2') {
                $addText((string)$item['text'], 15, true, 8);
            } elseif ($type === 'h3') {
                $addText((string)$item['text'], 13, true, 6);
            } elseif ($type === 'table') {
                self::drawTable($commands, $y, $margin, $contentW, (array)($item['rows'] ?? []), $ensure, 9);
            } elseif ($type === 'pagebreak') {
                $newPage();
            } else {
                $addText((string)($item['text'] ?? ''), 11, false, 8);
            }
        }

        $footer = trim((string)($doc['footer'] ?? ''));
        if ($footer !== '') {
            $ensure(48);
            $commands[] = ['op' => 'line', 'x1' => $margin, 'y1' => $y, 'x2' => $margin + $contentW, 'y2' => $y, 'w' => 0.7, 'rgb' => [219,228,240]];
            $y -= 18;
            $addText($footer, 9, false, 0);
        }

        if ($commands || !$pages) {
            $pages[] = $commands;
        }
        return self::buildRichPdf($pages, $images);
    }

    private static function drawTable(array &$commands, float &$y, float $margin, float $contentW, array $rows, callable $ensure, int $fontSize = 9): void
    {
        $cols = 1;
        foreach ($rows as $r) { $cols = max($cols, count((array)$r)); }
        $cellW = $contentW / max(1, $cols);
        foreach ($rows as $row) {
            $row = array_values((array)$row);
            $wrappedCells = [];
            $maxLines = 1;
            for ($i = 0; $i < $cols; $i++) {
                $lines = self::wrapForWidth((string)($row[$i] ?? ''), $fontSize, (int)($cellW - 12));
                $wrappedCells[$i] = $lines;
                $maxLines = max($maxLines, count($lines));
            }
            $rowH = max(20, 10 + ($maxLines * ($fontSize + 4)));
            $ensure($rowH + 4);
            $rowTop = $y + 4;
            for ($i = 0; $i < $cols; $i++) {
                $x = $margin + ($i * $cellW);
                $commands[] = ['op' => 'rect', 'x' => $x, 'y' => $rowTop - $rowH, 'w' => $cellW, 'h' => $rowH, 'rgb' => [219,228,240]];
                $lineY = $rowTop - 14;
                foreach ($wrappedCells[$i] as $line) {
                    $commands[] = ['op' => 'text', 'text' => $line, 'x' => $x + 6, 'y' => $lineY, 'size' => $fontSize, 'bold' => $i === 0];
                    $lineY -= ($fontSize + 4);
                }
            }
            $y -= $rowH;
        }
        $y -= 12;
    }

    private static function wrapForWidth(string $text, int $size, int $width): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '');
        if ($text === '') {
            return [''];
        }
        $max = max(18, (int)floor($width / (max(6, $size) * 0.48)));
        $rows = [];
        $words = preg_split('/\s+/u', $text) ?: [];
        $current = '';
        foreach ($words as $word) {
            $test = $current === '' ? $word : $current . ' ' . $word;
            if (self::length($test) > $max && $current !== '') {
                $rows[] = $current;
                $current = $word;
            } else {
                $current = $test;
            }
        }
        if ($current !== '') {
            $rows[] = $current;
        }
        return $rows ?: [''];
    }

    private static function rgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
            $hex = '2563eb';
        }
        return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    }

    private static function prepareImage(string $path): ?array
    {
        if (!is_file($path) || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $im = @imagecreatefromstring($bytes);
        if (!$im) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($im);
        }
        ob_start();
        imagejpeg($im, null, 88);
        $jpg = (string)ob_get_clean();
        imagedestroy($im);
        if ($jpg === '') {
            return null;
        }
        return ['width' => $w, 'height' => $h, 'data' => $jpg];
    }

    private static function buildRichPdf(array $pages, array $images): string
    {
        $objects = [];
        $fontRegular = 1;
        $fontBold = 2;
        $objects[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $next = 3;
        $imageObjectIds = [];
        foreach ($images as $name => $img) {
            $imageObjectIds[$name] = $next++;
        }
        $pageIds = [];
        $contentIds = [];
        foreach ($pages as $_) {
            $pageIds[] = $next++;
            $contentIds[] = $next++;
        }
        $pagesRoot = $next++;
        $catalog = $next++;

        foreach ($images as $name => $img) {
            $objects[$imageObjectIds[$name]] = '<< /Type /XObject /Subtype /Image /Width ' . (int)$img['width'] . ' /Height ' . (int)$img['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen((string)$img['data']) . " >>\nstream\n" . (string)$img['data'] . "\nendstream";
        }

        $xObject = '';
        foreach ($imageObjectIds as $name => $id) {
            $xObject .= '/' . $name . ' ' . $id . ' 0 R ';
        }
        $xObject = trim($xObject);

        foreach ($pages as $i => $commands) {
            $stream = '';
            foreach ($commands as $cmd) {
                $op = (string)($cmd['op'] ?? '');
                if ($op === 'text') {
                    $font = !empty($cmd['bold']) ? 'F2' : 'F1';
                    $stream .= "BT /{$font} " . (float)$cmd['size'] . ' Tf ' . sprintf('%.2F %.2F Td', (float)$cmd['x'], (float)$cmd['y']) . ' (' . self::encode((string)$cmd['text']) . ") Tj ET\n";
                } elseif ($op === 'line') {
                    $rgb = $cmd['rgb'] ?? [0,0,0];
                    $stream .= sprintf('%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S' . "\n", $rgb[0]/255, $rgb[1]/255, $rgb[2]/255, (float)$cmd['w'], (float)$cmd['x1'], (float)$cmd['y1'], (float)$cmd['x2'], (float)$cmd['y2']);
                } elseif ($op === 'rect') {
                    $rgb = $cmd['rgb'] ?? [219,228,240];
                    $stream .= sprintf('%.3F %.3F %.3F RG %.2F w %.2F %.2F %.2F %.2F re S' . "\n", $rgb[0]/255, $rgb[1]/255, $rgb[2]/255, 0.5, (float)$cmd['x'], (float)$cmd['y'], (float)$cmd['w'], (float)$cmd['h']);
                } elseif ($op === 'image') {
                    $stream .= 'q ' . sprintf('%.2F 0 0 %.2F %.2F %.2F cm', (float)$cmd['w'], (float)$cmd['h'], (float)$cmd['x'], (float)$cmd['y']) . ' /' . (string)$cmd['id'] . " Do Q\n";
                }
            }
            $objects[$contentIds[$i]] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $resources = '/Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >>';
            if ($xObject !== '') {
                $resources .= ' /XObject << ' . $xObject . ' >>';
            }
            $objects[$pageIds[$i]] = '<< /Type /Page /Parent ' . $pagesRoot . ' 0 R /MediaBox [0 0 595.28 841.89] /Resources << ' . $resources . ' >> /Contents ' . $contentIds[$i] . ' 0 R >>';
        }
        $kids = implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageIds));
        $objects[$pagesRoot] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageIds) . ' >>';
        $objects[$catalog] = '<< /Type /Catalog /Pages ' . $pagesRoot . ' 0 R >>';
        return self::finalPdf($objects, $catalog);
    }

    private static function finalPdf(array $objects, int $catalog): string
    {
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $max; $id++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
        }
        $pdf .= 'trailer << /Size ' . ($max + 1) . ' /Root ' . $catalog . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }
    private static function wrap(string $text,int $size,int $indent): array
    {
        $text=preg_replace('/\s+/u',' ',trim(strip_tags($text)))??'';
        if($text==='')return [''];
        $max=max(34,(int)((510-$indent)/(max(5,$size)*0.53)));
        $rows=[];
        foreach(preg_split('/\R/u',$text)?:[$text] as $paragraph){
            $words=preg_split('/\s+/u',trim($paragraph))?:[];$current='';
            foreach($words as $word){$test=$current===''?$word:$current.' '.$word;if(self::length($test)>$max&&$current!==''){$rows[]=$current;$current=$word;}else{$current=$test;}}
            if($current!==''||!$rows)$rows[]=$current;
        }
        return $rows;
    }

    private static function length(string $text): int
    {
        if(function_exists('mb_strlen'))return mb_strlen($text,'UTF-8');
        if(preg_match_all('/./us',$text,$matches)!==false)return count($matches[0]);
        return strlen($text);
    }

    private static function encode(string $text): string
    {
        $encoded=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);
        if($encoded===false)$encoded=preg_replace('/[^\x20-\x7E]/','?',$text)??$text;
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$encoded);
    }

    private static function build(array $pages): string
    {
        $objects=[];$fontRegular=1;$fontBold=2;
        $objects[$fontRegular]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBold]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $pageIds=[];$contentIds=[];$next=3;
        foreach($pages as $_){$pageIds[]=$next++;$contentIds[]=$next++;}
        $pagesRoot=$next++;$catalog=$next++;
        foreach($pages as $i=>$commands){
            $stream="BT\n";
            foreach($commands as $cmd){$font=$cmd['bold']?'F2':'F1';$stream.='/'.$font.' '.$cmd['size'].' Tf '.sprintf('%.2F %.2F Td',$cmd['x'],$cmd['y']).' ('.self::encode($cmd['text']).") Tj\n";$stream.='-'.sprintf('%.2F',$cmd['x']).' -'.sprintf('%.2F',$cmd['y'])." Td\n";}
            $stream.="ET\n";
            $objects[$contentIds[$i]]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
            $objects[$pageIds[$i]]='<< /Type /Page /Parent '.$pagesRoot.' 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontRegular.' 0 R /F2 '.$fontBold.' 0 R >> >> /Contents '.$contentIds[$i].' 0 R >>';
        }
        $kids=implode(' ',array_map(static fn(int $id):string=>$id.' 0 R',$pageIds));
        $objects[$pagesRoot]='<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';
        $objects[$catalog]='<< /Type /Catalog /Pages '.$pagesRoot.' 0 R >>';
        ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objects as $id=>$body){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$body."\nendobj\n";}
        $xref=strlen($pdf);$max=max(array_keys($objects));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
        for($id=1;$id<=$max;$id++)$pdf.=sprintf('%010d 00000 n ',$offsets[$id]??0)."\n";
        $pdf.='trailer << /Size '.($max+1).' /Root '.$catalog." 0 R >>\nstartxref\n".$xref."\n%%EOF";
        return $pdf;
    }
}
