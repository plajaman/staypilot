## V2.3.6.140 – Smart Arrival Phase 6

- Optionaler Handy-Link `papier-scan.php?token=...` zum Fotografieren/Upload von handschriftlichen Papier-Meldescheinen ergänzt.
- Keine zwingende Maßnahme: normaler Online-Check-in, Rezeptionserfassung und bestehender Meldeschein-Ablauf bleiben unverändert nutzbar.
- Papierfoto wird ausschließlich als vorhandener Check-in-Upload gespeichert; es entsteht kein zweiter Meldeschein und kein paralleler Datenablauf.
- Neue Einstellungen: Papier-Scan-Link aktivieren/deaktivieren und öffentliche Token-Seite aktivieren/deaktivieren.
- Admin kann den optionalen Handy-Link aus der bestehenden Smart-Arrival-Ansicht erzeugen, öffnen oder kopieren.

## V2.3.6.139 – Smart Arrival Phase 5

- Rezeption kann von Hand ausgefuellte Meldescheine in bestehende Reisenden-/Meldeschein-Daten uebernehmen.
- Neue Erfassungshistorie protokolliert nur die Quelle Papierformular; kein zweiter Meldeschein.
- Bestehende booking_checkins, booking_travellers und vorhandener Meldeschein-Ablauf bleiben fuehrend.
- Optional per Einstellung: Handerfassung und Papierformular-Foto/Scan.
- Keine neue Housekeeping-Logik, keine automatische Behoerdenuebermittlung.

## V2.3.6.138 – Smart Arrival Phase 4

- Anreise-Ampel/Vollstaendigkeitspruefung fuer die naechsten Anreisen ergaenzt.
- Nutzt vorhandene Online-Check-in-, Reisenden- und Meldeschein-Daten; kein zweiter Meldeschein.
- Erinnerungs-Hinweise sind nur Anzeige; Versand bleibt im bestehenden Check-in-Mail-/WhatsApp-Ablauf.
- Housekeeping wird nicht erweitert und nicht gekoppelt.

## V2.3.6.136 – Smart Arrival Phase 2

- MRZ-Helfer im bestehenden Online-Check-in ergänzt.
- MRZ-Zeilen werden lokal im Browser ausgewertet und füllen vorhandene Reisendenfelder vor.
- OCR-Vorbereitung als optionaler Hinweis ergänzt, ohne externe Cloud und ohne neue Schnittstelle.
- Neue Einstellungen: MRZ-Helfer und OCR-Vorbereitung separat ein-/ausschaltbar.
- Kein neuer Meldeschein, keine neue Housekeeping-Logik, keine parallele Dokumentenablage.

# V2.3.6.134 – Kommunikationscenter: Vorlagenverwaltung und direkte IMAP-Aktionen

- E-Mail-Vorlagenverwaltung im Kommunikationscenter ergänzt.
- Vorlagen können erstellt, bearbeitet und gelöscht werden.
- Papierkorb-Aktion verschiebt ohne Texteingabe-Abfrage in den IMAP-Papierkorb.
- Schreibeditor nutzt gespeicherte Vorlagen zusätzlich zu Standardvorlagen.
- Mail-Aktionsbuttons robuster angebunden.
- Keine Datenbankänderung, keine Mailserver-Automatiklöschung.

# V2.3.6.134 – Kommunikationscenter: Vorlagenverwaltung und direkte IMAP-Aktionen

- E-Mail-Editor deutlich erweitert: größere Schreibfläche, Schnellplatzhalter, mehr Vorlagen und sichtbare Platzhalter-Leiste.
- Zusätzliche Mailvorlagen: Buchungsbestätigung, Angebots-Nachfassen, Zahlungserinnerung, Check-in, Anreiseinfo, freie Antwort, Storno/Absage.
- Platzhaltergruppen erweitert: Gast, Buchung, Unterkunft, Zahlung, Links/Dokumente und Betrieb.
- Posteingang erhält bewusste IMAP-Aktionen: als gelesen markieren, verschieben, archivieren, in Papierkorb verschieben.
- IMAP-Ordner können ausgelesen und als Zielordner genutzt werden.
- Mailaktionen sind POST/CSRF-geschützt und werden protokolliert.
- Keine automatische Mailserver-Löschung; Papierkorb-Aktion benötigt Bestätigung `MAIL`.

## V2.3.6.135 – Smart Arrival Phase 1
- Bestehenden Online-Check-in um Smart-Arrival-Schicht erweitert.
- Keine parallelen Meldescheine oder Housekeeping-Abläufe angelegt.
- Einstellungen für Dokumentfoto, Datenschutz, Meldeschein-Autofüllung und optionale Housekeeping-Anzeige ergänzt.
