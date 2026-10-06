'use strict';
(() => {
  const baseRenderPageV213 = renderPage;
  pageMeta.help = ['Hilfe & Abläufe','Arbeitsanleitung und Systemüberblick'];

  const topicByPage = {
    dashboard: 'dashboard-workflow',
    statistics: 'statistics',
    calendar: 'calendar',
    bookings: 'bookings',
    offers: 'offers',
    billing: 'billing',
    website: 'website-studio',
    houses: 'master-data',
    apartment_types: 'master-data',
    apartments: 'master-data',
    guests: 'guests',
    housekeeping: 'housekeeping',
    housekeeping_tasks: 'housekeeping',
    housekeeping_teams: 'roles',
    housekeeping_release: 'release',
    guest_portal_contents: 'guest-portal',
    portal_links: 'portals',
    breakfast: 'services',
    checkin: 'checkin',
    prices: 'prices',
    price_overview: 'prices',
    communication: 'communication',
    delete_center: 'delete-center',
    diagnostics: 'diagnostics',
    users: 'roles',
    roles: 'roles',
    settings: 'settings',
  };

  const h = value => esc(value == null ? '' : String(value));
  const pageLink = page => `<button type="button" class="btn small" data-page-link="${h(page)}">Öffnen</button>`;
  const tag = (text, kind='info') => `<span class="v213-tag v213-tag-${h(kind)}">${h(text)}</span>`;

  function section(id, title, subtitle, body, icon='📘') {
    return `<details class="v213-help-details sp124-help-details" id="${h(id)}" data-help-search="${h((title+' '+subtitle+' '+body.replace(/<[^>]+>/g,' ')).toLowerCase())}"><summary><span>${icon}</span><div><b>${h(title)}</b><small>${h(subtitle)}</small></div></summary><div class="v213-help-details-body sp124-help-body">${body}</div></details>`;
  }

  function steps(items) {
    return `<ol class="v213-number-list sp124-steps">${items.map(x=>`<li>${x}</li>`).join('')}</ol>`;
  }

  function cards(items) {
    return `<div class="grid two sp124-card-grid">${items.map(x=>`<article class="card"><h3>${x.icon||''} ${h(x.title)}</h3><p>${x.text}</p>${x.link||''}</article>`).join('')}</div>`;
  }

  function table(headers, rows) {
    return `<div class="table-scroll"><table class="v213-help-table"><thead><tr>${headers.map(hh=>`<th>${h(hh)}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${r.map(c=>`<td>${c}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
  }

  function warning(text) { return `<div class="alert warning"><b>Wichtig:</b> ${text}</div>`; }
  function ok(text) { return `<div class="alert success"><b>Produktreife-Regel:</b> ${text}</div>`; }

  const overviewBody = `
    <div class="v213-key-message"><span>🎯</span><div><b>StayPilot ist kein Spielsystem mehr, sondern ein PMS im Produktreife-Aufbau.</b><p>Die wichtigste Regel: erst stabilisieren, dann erweitern. Jede Aktion muss nachvollziehbar, geschützt und rückgängig erklärbar sein.</p></div></div>
    ${cards([
      {icon:'📌',title:'Kernprinzip',text:'Buchungen, Angebote, Zahlungen, Housekeeping, Kundenportal und Webseite dürfen keine widersprüchlichen Status zeigen.'},
      {icon:'🧾',title:'Abrechnung',text:'Nur intern abrechnungsrelevante Buchungen erzeugen interne Zahlungsziele, Rechnungen oder Erinnerungen.'},
      {icon:'🧹',title:'Betrieb',text:'Housekeeping läuft von Auftrag über Kontrolle bis finale Freigabe. Gäste sehen erst endgültig freigegebene Informationen.'},
      {icon:'🔐',title:'Sicherheit',text:'Schreibende Aktionen laufen über POST/CSRF, Rechte werden geprüft, kritische Vorgänge landen im Audit-Log.'},
    ])}
    <h3>Die tägliche Grundreihenfolge</h3>
    ${steps([
      'Dashboard öffnen und Hinweise auf angenommene Angebote, offene Zahlungen, Check-ins und Housekeeping prüfen.',
      'Neue Angebotsannahmen erst prüfen, dann konkrete Wohnung zuweisen und verbindlich bestätigen.',
      'Kalender und nicht zugeordnete Vorgänge kontrollieren.',
      'Housekeeping: Abreisen, offene Aufgaben, Kontrolle und Freigabe prüfen.',
      'Abrechnung: interne offene Zahlungsziele und überfällige Beträge prüfen.',
      'Diagnose nur als Produktreife-Kontrolle nutzen, nicht als Ersatz für den Tagesablauf.'
    ])}`;

  const masterDataBody = `
    <p>Stammdaten sind die Grundlage für alles: Kalender, Preis, öffentliche Webseite, Angebote und Housekeeping.</p>
    ${steps([
      '<b>Häuser</b> anlegen: Name, Adresse, interne Ordnung.',
      '<b>Wohnungstypen</b> anlegen: öffentlicher Name, Beschreibung, Kapazität, Bilder, Ausstattung, Regeln.',
      '<b>Apartments</b> anlegen: konkrete Nummern, Haus, Typ, interne Hinweise.',
      '<b>Preise & Saisons</b> pflegen: fehlende Preise blockieren später korrekte Angebote.',
      '<b>Extras</b> wie Frühstück, Halbpension, Parkplatz oder Endreinigung sauber definieren.'
    ])}
    ${warning('Öffentliche Wohnungstypen und interne Apartmentnummern müssen getrennt bleiben. Der Gast kann einen Typ anfragen; die konkrete Wohnung wird intern zugewiesen.')}`;

  const bookingSourceBody = `
    <p>Für Marktreife ist die Trennung zwischen Belegung und Abrechnung entscheidend.</p>
    ${table(['Buchungsart','Quelle','Abrechnungsart','Wirkung'],[
      ['Direktbuchung','Telefon, E-Mail, manuell','<b>intern abrechnen</b>','Kalender, Kundenportal, Zahlungsplan, Rechnung, Erinnerung'],
      ['Webseitenbuchung','StayPilot-Buchungsseite','<b>intern abrechnen</b>','wie Direktbuchung, inklusive interner Zahlungslogik'],
      ['Portalbuchung','Booking.com, Airbnb, Hotel-Spider','<b>extern abgerechnet</b>','Kalender und Housekeeping ja, keine interne offene Forderung'],
      ['Importbuchung','CSV, XML, iCal später','<b>extern abgerechnet</b>','Belegung ja, interne Abrechnung nein'],
      ['Personal/Eigentümer','intern, Technik, Sperrung','<b>keine Abrechnung</b>','blockiert Kalender, keine Rechnung und kein Zahlungsplan']
    ])}
    ${steps([
      'Neue Buchung anlegen oder importieren.',
      'Quelle prüfen: direkt, Webseite, Portal, Import, Personal oder Sperrung.',
      'Abrechnungsart kontrollieren.',
      'Bei externen Buchungen keine internen Zahlungsziele erzeugen.',
      'Bei Personal/Sperrung keine Rechnung und kein Kundenportal erzwingen.',
      'Assistent „Buchungsquellen & Abrechnungsart“ nutzen, wenn alte Daten falsch zugeordnet sind.'
    ])}
    ${ok('Jede Buchung muss eindeutig sagen: blockiert sie nur den Kalender oder gehört sie in die interne Abrechnung?')}`;

  const offersBody = `
    <p>Angebote sind eigenständige Vorgänge. Ein angenommenes Angebot ist noch keine endgültige Buchung.</p>
    ${steps([
      'Angebot im Bereich <b>Angebote</b> erstellen.',
      'Gast, Zeitraum, Personen, Wohnungstyp oder konkrete Wohnung wählen.',
      'Preis serverseitig berechnen lassen. Fehlende Preise müssen gepflegt werden.',
      'E-Mail, Texte, Angebotsseite und PDF prüfen.',
      'Angebot versenden. Der Gast kann den sicheren Link annehmen oder ablehnen.',
      'Angenommenes Angebot erscheint im Dashboard.',
      'Rezeption/Admin prüft Verfügbarkeit erneut und weist eine konkrete Wohnung zu.',
      'Erst dann verbindlich bestätigen und Kalender blockieren.'
    ])}
    ${warning('Versendete Angebote nicht still verändern. Bei Änderungen Revision oder neues Angebot erstellen.')}`;

  const bookingsBody = `
    <p>Die sichere Buchungsübernahme ist die Statuskette: Angebot → Prüfung → Wohnung → Zahlungslogik → Kundenportal → Housekeeping.</p>
    ${steps([
      'Angenommenes Angebot oder neue Buchung öffnen.',
      'Gast und Quelle prüfen.',
      'Abrechnungsart festlegen: intern, extern, keine Abrechnung oder Portal später.',
      'Konkrete Wohnung zuweisen, wenn die Buchung aktiv den Kalender blockieren soll.',
      'Bei interner Abrechnung Zahlungsplan erzeugen oder prüfen.',
      'Kundenportal-Link nur erzeugen, wenn der Gast ihn nutzen soll.',
      'Check-in-Datensatz erzeugen, wenn Online-Check-in vorgesehen ist.',
      'Housekeeping-Auftrag aus Abreise/Wechsel erzeugen oder bewusst deaktivieren.'
    ])}
    ${cards([
      {icon:'✅',title:'Aktive Direktbuchung',text:'braucht Gast, Wohnung, Zeitraum, Zahlungslogik, Kundenportal/Check-in je nach Prozess und Housekeeping.'},
      {icon:'🌐',title:'Externe Portalbuchung',text:'braucht Kalenderblockierung und Housekeeping, aber keine interne Forderung.'},
      {icon:'👥',title:'Personal/Sperrung',text:'braucht Kalenderblockierung, aber keine Gästeabrechnung und meist kein Kundenportal.'},
      {icon:'⚠️',title:'Unklarer Fall',text:'nicht automatisch bereinigen, sondern im Assistenten markieren und entscheiden.'}
    ])}`;

  const calendarBody = `
    <p>Der Kalender ist die operative Wahrheit für Belegung. Er darf aber nicht mit Abrechnung verwechselt werden.</p>
    ${steps([
      'Nach Datum, Haus, Typ oder Wohnung filtern.',
      'Nicht zugeordnete Buchungen im Typ-Pool prüfen.',
      'Direkt-/Webseitenbuchungen als intern abrechnungsrelevant erkennen.',
      'Portalbuchungen als extern markieren.',
      'Sperrungen und Personalbelegungen nicht in Umsatz zählen.',
      'Bei Konflikten keine Buchung überschreiben, sondern Reparatur-/Klärungsdialog nutzen.'
    ])}
    ${ok('Kalender blockiert Verfügbarkeit. Abrechnung entscheidet Geldfluss. Beides muss getrennt sichtbar sein.')}`;

  const billingBody = `
    <p>Abrechnung verarbeitet nur Buchungen, die intern abrechnungsrelevant sind.</p>
    ${table(['Fall','Abrechnung in StayPilot?','Behandlung'],[
      ['Direkt/Webseite','Ja','Zahlungsplan, Teilzahlungen, Rechnung, Quittung, Erinnerung'],
      ['Booking.com/Portal','Nein','später Portalabrechnung, Provision/Auszahlung separat'],
      ['CSV/XML Import','Nein, standardmäßig','extern abgerechnet; später Importabrechnung möglich'],
      ['Personal/Eigentümer','Nein','nur Belegung/Statistik, keine Forderung'],
      ['Storno/gelöscht','Nein','offene Zahlungsziele aufheben, Dokumente archivieren']
    ])}
    ${steps([
      'Interne offene Zahlungsziele prüfen.',
      'Überfällige interne Beträge bearbeiten.',
      'Zahlungen ohne aktive Buchung über Datenbereinigung prüfen.',
      'Externe Zahlungen nicht als interne offene Forderungen führen.',
      'Bei Differenzen Abrechnungsstatus-Assistent nutzen.',
      'Rechnungen/Quittungen nur für intern abrechnungsrelevante Fälle erzeugen.'
    ])}`;

  const housekeepingBody = `
    <p>Housekeeping ist ein eigener Betriebsprozess. Eine Wohnung ist erst nach finaler Freigabe für Gäste sichtbar.</p>
    ${steps([
      'Auftrag entsteht automatisch aus Abreise oder manuell.',
      'Leitung/Rezeption weist Aufgabe zu.',
      'Mitarbeiter nimmt an und erledigt Reinigung/Checkliste.',
      'Reinigung abgeschlossen melden.',
      'Kontrolle durchführen oder Nachreinigung anfordern.',
      'Bezugsbereit melden.',
      'Admin/Rezeption gibt final frei.',
      'Erst danach darf die Wohnung in der öffentlichen Gästeanzeige erscheinen.'
    ])}
    ${table(['Status','Bedeutung','Nächster Schritt'],[
      ['open / assigned','offen oder zugewiesen','annehmen/starten'],
      ['in_progress','in Arbeit','fertig melden'],
      ['cleaning_done','Reinigung fertig','Kontrolle'],
      ['inspection_required','Kontrolle offen','bestehen oder Nachreinigung'],
      ['ready_reported','bezugsbereit gemeldet','finale Freigabe'],
      ['released','freigegeben','abgeschlossen'],
      ['blocked','Mangel blockiert','Mangel klären']
    ])}
    ${warning('Alte offene Aufgaben nicht automatisch löschen. Entscheiden: offen lassen, zur Prüfung markieren, erledigen oder stornieren.')}`;

  const checkinBody = `
    <p>Check-in ist Teil der Gästedaten- und Dokumentenlogik. Er darf nicht offen bleiben, wenn die Buchung beendet oder gelöscht ist.</p>
    ${steps([
      'Check-in-Link nur für aktive passende Buchungen verwenden.',
      'Gastdaten, Mitreisende und Dokumente prüfen.',
      'Offene oder unvollständige Check-ins im Dashboard prüfen.',
      'Check-ins ohne aktive Buchung schließen oder archivieren.',
      'Meldeschein/Dokumente erzeugen, wenn Daten vollständig sind.',
      'Dokumente nie unkontrolliert öffentlich machen.'
    ])}`;

  const communicationBody = `
    <p>Kommunikation muss nachvollziehbar und wiederholbar sein.</p>
    ${steps([
      'E-Mail-Vorlage prüfen: Betreff, Sprache, Platzhalter, Signatur.',
      'Vor Versand echte Vorschau prüfen.',
      'Versandprotokoll nach Fehlern kontrollieren.',
      'WhatsApp nur als vorbereiteter Text/Fallback nutzen.',
      'SPF/DKIM/DMARC extern im DNS prüfen, wenn Mails im Spam landen.',
      'Keine Kundendaten in ungeschützte Links schreiben.'
    ])}`;

  const websiteBody = `
    <p>Der Website-/Studio-Bereich ist wichtig, aber darf den PMS-Kern nicht gefährden.</p>
    ${steps([
      'Classic Editor bleibt als Sicherheit vorhanden.',
      'Studio Editor für neue professionelle Seiten nutzen.',
      'Komplette Vorlagen zuerst als Entwurf speichern.',
      'Vorschau prüfen, bevor Seiten aktiv genutzt werden.',
      'Buchungs-/Anfrageblöcke mit echter Preis- und Abrechnungslogik verbinden.',
      'SEO, Impressum, Datenschutz und Kontakt vor Veröffentlichung prüfen.'
    ])}
    ${warning('Öffentliche Webseite kann Anfragen erzeugen. Sie muss deshalb mit Buchungsquelle „Webseite“ und Abrechnungsart „intern abrechnen“ zusammenpassen.')}`;

  const deleteCenterBody = `
    <p>Das Löschcenter ist kein Papierkorb-Spielzeug, sondern ein Produktreife-Werkzeug.</p>
    ${steps([
      'Erst prüfen, dann entscheiden.',
      'Logisch löschen statt hart löschen, wenn noch Bezüge vorhanden sind.',
      'Verwaiste Zahlungs-/Check-in-Daten nur mit Bestätigung endgültig entfernen.',
      'Assistenten nutzen: Kern-PMS, Abrechnung, Housekeeping, Status/Archiv, Buchungsquellen.',
      'Nach Bereinigung Dashboard, Buchungen, Abrechnung und Diagnose erneut laden.',
      'Audit-Log beachten: Kritische Aktionen müssen nachvollziehbar bleiben.'
    ])}`;

  const diagnosticsBody = `
    <p>Diagnose zeigt nicht nur Fehler, sondern Produktreife-Risiken.</p>
    ${cards([
      {icon:'🟢',title:'OK',text:'Technisch oder fachlich unauffällig.'},
      {icon:'🟡',title:'Hinweis',text:'Arbeitsbestand, externer Punkt oder manuell zu prüfen.'},
      {icon:'🟠',title:'Warnung',text:'Produktreife-Risiko, das bewusst geklärt werden sollte.'},
      {icon:'🔴',title:'Fehler',text:'Technischer oder fachlicher Stopper, zuerst beheben.'}
    ])}
    ${steps([
      'Nach jedem Upload VERSION.txt prüfen.',
      'Systemdiagnose ausführen.',
      'Release-Integrität und Rollback-Bereitschaft ansehen.',
      'Warnungen fachlich einordnen: echter Fehler oder Arbeitsbestand?',
      'Passenden Assistenten nutzen, wenn ein Hinweis bereinigt werden soll.',
      'Neue Diagnose nach Korrektur erneut senden oder prüfen.'
    ])}`;

  const backupBody = `
    <p>Für Kundenbetrieb ist Backup wichtiger als jede neue Funktion.</p>
    ${steps([
      'Vor Updates manuelles Backup erstellen.',
      'Backup-Verifikation prüfen: lesbar, SQL-Grundstruktur, Fremdschlüssel-Reaktivierung, Prüfsumme.',
      'Release-Manifest prüfen.',
      'Update-Rettung verfügbar halten.',
      'Restore nicht blind live ausführen; zuerst Staging/Prüfumgebung verwenden.',
      'Installationsdateien im Produktivbetrieb schützen.'
    ])}`;

  const rolesBody = `
    <p>Rollen entscheiden, wer sehen, ändern, löschen, abrechnen und freigeben darf.</p>
    ${table(['Rolle','Darf typischerweise','Darf nicht'],[
      ['Admin/Manager','System, Rechte, Diagnose, Abrechnung, Freigaben','–'],
      ['Rezeption','Buchungen, Angebote, Gäste, finale Freigabe','Systemrechte ohne Freigabe'],
      ['Housekeeping-Leitung','Aufgaben, Zuweisung, Kontrolle, bezugsbereit','Abrechnung/Rechnungen'],
      ['Reinigungskraft','eigene Aufgaben bearbeiten','fremde Daten, Abrechnung, finale Freigabe'],
      ['Readonly','ansehen','ändern/löschen/senden/freigeben'],
      ['Gast','eigener Link, eigene Daten','Admin- oder Teamdaten']
    ])}`;

  const customerInstallBody = `
    <p>Für Kundeninstallationen ist aktuell die sichere Variante: eigene App-Installation pro Kunde.</p>
    ${steps([
      'Eigenen Ordner/Pfad oder Subdomain anlegen.',
      'Eigene Datenbank für den Kunden anlegen.',
      'config/database.php nur für diesen Kunden setzen.',
      'Ersten Admin-Benutzer anlegen.',
      'Keine Demo-Daten im Kundenbetrieb lassen.',
      'Installer schützen.',
      'Backup prüfen.',
      'Diagnose ausführen und dokumentieren.'
    ])}
    ${ok('Pro Kunde eigene Dateien und eigene Datenbank sind für den Anfang sicherer als ein gemeinsames Mandantensystem.')}`;

  const troubleshootingBody = `
    ${table(['Problem','Wahrscheinliche Ursache','Sichere Lösung'],[
      ['Neue Version nicht sichtbar','ZIP falsch hochgeladen oder Cache','VERSION.txt prüfen, richtigen Ordner, Strg+F5'],
      ['Button ohne Funktion','JS-Datei fehlt oder alte Datei im Cache','Datei direkt per URL prüfen, Cache leeren'],
      ['Buchung erscheint in Abrechnung falsch','Quelle/Abrechnungsart falsch','Buchungsquellen-Assistent nutzen'],
      ['Zahlung ohne Buchung','alte Test-/Archivdaten','Datenbereinigung prüfen, bewusst löschen/markieren'],
      ['Housekeeping-Warnung bleibt','echte offene Aufgaben','Altaufgaben prüfen und entscheiden'],
      ['Mail landet im Spam','DNS/SPF/DKIM/DMARC','Hosting/DNS extern prüfen']
    ])}`;

  function renderAdminHelpV213() {
    content.innerHTML = `<div class="sp124-help" id="help-top">
      <section class="v213-help-hero sp124-hero">
        <div><span class="v213-help-kicker">STAYPILOT HILFE & ABLÄUFE V2.3.6.124</span><h2>Produktreife Arbeitsanleitung</h2><p>Diese Hilfe erklärt den tatsächlichen Arbeitsablauf: Stammdaten, Buchung, Abrechnung, Housekeeping, Kundenportal, Diagnose, Backups, Rechte und Kundeninstallationen.</p></div>
        <div class="v213-help-hero-actions"><button type="button" class="btn primary" data-v213-scroll="quickstart">Schnellstart</button><button type="button" class="btn" data-v213-print>Hilfe drucken</button></div>
      </section>
      <section class="card sp124-help-top" id="quickstart" data-help-search="schnellstart produktreife täglich dashboard diagnose">
        <div class="card-head"><div><h2>🚦 Schnellstart für den Alltag</h2><p>Die wichtigsten Schritte, damit StayPilot sauber betrieben wird.</p></div></div>
        ${overviewBody}
      </section>
      <nav class="v213-help-nav sp124-help-nav" aria-label="Hilfethemen">
        ${[
          ['master-data','Stammdaten'],['booking-source','Buchungsarten'],['offers','Angebote'],['bookings','Buchungen'],['calendar','Kalender'],['billing','Abrechnung'],['housekeeping','Housekeeping'],['checkin','Check-in'],['communication','Kommunikation'],['website-studio','Webseite'],['delete-center','Löschcenter'],['diagnostics','Diagnose'],['backup','Backup'],['roles','Rechte'],['customer-install','Kunden-App'],['troubleshooting','Probleme']
        ].map(([id,label])=>`<button type="button" data-v213-scroll="${id}">${h(label)}</button>`).join('')}
      </nav>
      <div class="v213-help-search card"><label for="v213HelpSearch"><b>Hilfethemen durchsuchen</b><small>Beispiel: Booking.com, Zahlungsplan, Housekeeping, Backup, Kundeninstallation</small></label><input id="v213HelpSearch" type="search" placeholder="Suchbegriff eingeben …" autocomplete="off"></div>
      <section class="v213-help-accordions sp124-accordions">
        ${section('master-data','Stammdaten richtig anlegen','Häuser, Wohnungstypen, Apartments, Preise und Extras als Grundlage.',masterDataBody,'🏠')}
        ${section('booking-source','Buchungsquelle und Abrechnungsart','Direkt, Webseite, Portal, Import, Personal und Sperrung sauber trennen.',bookingSourceBody,'🏷️')}
        ${section('offers','Angebote erstellen und übernehmen','Vom Entwurf bis zur Kundenzusage und internen Prüfung.',offersBody,'🧾')}
        ${section('bookings','Buchungen und Statuskette','Angebot → Buchung → Wohnung → Zahlung → Check-in → Housekeeping.',bookingsBody,'📘')}
        ${section('calendar','Kalender und Belegung','Belegung sichtbar machen, ohne Abrechnung zu vermischen.',calendarBody,'📅')}
        ${section('billing','Abrechnung und Zahlungen','Nur intern abrechnungsrelevante Buchungen erzeugen Forderungen.',billingBody,'💳')}
        ${section('housekeeping','Housekeeping und finale Freigabe','Reinigung, Kontrolle, bezugsbereit und Gastfreigabe.',housekeepingBody,'🧹')}
        ${section('checkin','Online-Check-in und Kundenportal','Gästedaten, Dokumente und Links sauber führen.',checkinBody,'📝')}
        ${section('communication','Kommunikation und Versand','E-Mail, WhatsApp-Fallback, Versandprotokoll und Spam-Hinweise.',communicationBody,'✉️')}
        ${section('website-studio','Webseite, Buchungsseite und Studio','Classic/Studio, Vorlagen, Vorschau und öffentliche Anfrage.',websiteBody,'🌐')}
        ${section('delete-center','Löschcenter und Datenbereinigung','Logisch löschen, archivieren, verwaiste Daten kontrolliert entfernen.',deleteCenterBody,'🗑️')}
        ${section('diagnostics','Diagnose richtig lesen','OK, Hinweise, Warnungen und Fehler produktreif einordnen.',diagnosticsBody,'🩺')}
        ${section('backup','Backup, Update und Restore','Vor Updates sichern, Backup prüfen und Wiederherstellung vorbereiten.',backupBody,'🛟')}
        ${section('roles','Rollen, Rechte und Portale','Admin, Rezeption, Housekeeping, Team, Readonly und Gast trennen.',rolesBody,'🔐')}
        ${section('customer-install','Kunden-App anlegen','Eigene Installation, eigener Pfad, eigene Datenbank, eigene Konfiguration.',customerInstallBody,'🏢')}
        ${section('troubleshooting','Häufige Probleme und sichere Lösung','Typische Fehlerbilder ohne Datenverlust beheben.',troubleshootingBody,'🧯')}
      </section>
      <section class="card sp124-next"><h2>📍 Marktreife-Leitlinie</h2><p>Neue Funktionen kommen erst nach stabiler Statuskette, sauberer Abrechnung, gesicherter Datenlogik, geprüften Rollen und verlässlichem Backup-/Update-Prozess.</p><div class="toolbar"><button class="btn" data-page-link="diagnostics">Diagnose öffnen</button><button class="btn" data-page-link="delete_center">Löschcenter öffnen</button><button class="btn" data-page-link="bookings">Buchungen öffnen</button><button class="btn" data-page-link="billing">Abrechnung öffnen</button></div></section>
    </div>`;
  }

  function installContextHelpV213() {
    const actions = document.querySelector('.top-actions');
    document.getElementById('adminContextHelpV213')?.remove();
    if (!actions) return;
    const button = document.createElement('button');
    button.type = 'button';
    button.id = 'adminContextHelpV213';
    button.className = 'btn v213-top-help';
    if (state.page === 'help') { button.textContent = '🖨 Hilfe drucken'; button.dataset.v213Print = ''; }
    else { button.textContent = '❓ Hilfe'; button.dataset.v213Help = topicByPage[state.page] || 'quickstart'; }
    actions.prepend(button);

    const topic = topicByPage[state.page];
    if (!topic || state.page === 'help') return;
    const messages = {
      dashboard:'Dashboard ist die tägliche Leitstelle: Angebotsannahmen, Housekeeping, Zahlungen und Check-ins zuerst hier prüfen.',
      offers:'Angebote bleiben bis zur internen Prüfung eigenständig. Kundenzusage ist noch keine endgültige Buchung.',
      bookings:'Buchung immer mit Quelle und Abrechnungsart prüfen: intern, extern, keine Abrechnung oder Portal später.',
      billing:'Abrechnung zeigt nur interne Forderungen. Externe Portalbuchungen dürfen keine internen offenen Zahlungen erzeugen.',
      housekeeping:'Housekeeping läuft von Auftrag über Kontrolle bis finale Freigabe. Alte Aufgaben bewusst entscheiden.',
      website:'Webseite und Studio erst veröffentlichen, wenn Anfrage-, Preis- und Buchungslogik passt.',
      diagnostics:'Diagnose zeigt technische und fachliche Produktreife-Hinweise. Warnungen nicht wegklicken, sondern einordnen.',
      delete_center:'Löschcenter und Assistenten nie blind verwenden. Erst prüfen, dann bewusst archivieren, markieren oder löschen.',
      users:'Rollen klein halten: Jeder Benutzer bekommt nur die Rechte, die er wirklich braucht.'
    };
    content.insertAdjacentHTML('afterbegin',`<div class="v213-context-help"><span class="v213-question">?</span><p>${h(messages[state.page] || 'Zu diesem Bereich ist eine kontextbezogene Hilfe verfügbar.')}</p><button type="button" class="btn small" data-v213-help="${h(topic)}">Hilfe öffnen</button></div>`);
  }

  renderPage = async function() {
    if (state.page === 'help') { renderAdminHelpV213(); installContextHelpV213(); return; }
    await baseRenderPageV213();
    installContextHelpV213();
  };

  document.addEventListener('click', event => {
    const help = event.target.closest('[data-v213-help]');
    if (help) { event.preventDefault(); state.helpTopic = help.dataset.v213Help || 'quickstart'; navigate('help'); requestAnimationFrame(()=>document.getElementById(state.helpTopic)?.scrollIntoView({behavior:'smooth',block:'start'})); return; }
    const scroll = event.target.closest('[data-v213-scroll]');
    if (scroll) { event.preventDefault(); const target = document.getElementById(scroll.dataset.v213Scroll); if (target) { if (target.tagName === 'DETAILS') target.open = true; target.scrollIntoView({behavior:'smooth',block:'start'}); } return; }
    const link = event.target.closest('[data-page-link]');
    if (link) { event.preventDefault(); navigate(link.dataset.pageLink); return; }
    const print = event.target.closest('[data-v213-print]');
    if (print) { event.preventDefault(); document.body.classList.add('v213-print-help'); window.print(); setTimeout(()=>document.body.classList.remove('v213-print-help'),500); }
  }, true);

  document.addEventListener('input', event => {
    if (event.target.id !== 'v213HelpSearch') return;
    const query = event.target.value.trim().toLowerCase();
    document.querySelectorAll('[data-help-search]').forEach(element => {
      const text = `${element.dataset.helpSearch || ''} ${element.textContent || ''}`.toLowerCase();
      element.hidden = Boolean(query) && !text.includes(query);
    });
  });
})();
