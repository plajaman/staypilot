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

## V2.3.6.137 – Smart Arrival Phase 3

- Smart-Arrival-Exportvorbereitung ergänzt.
- CSV/JSON-Vorbereitungsdateien aus vorhandenen Online-Check-in-/Reisendendaten.
- Exportprotokoll mit Status und manueller Gemeldet-Markierung vorbereitet.
- Keine automatische Behördenübermittlung, kein zweiter Meldeschein, kein paralleles Housekeeping.

## V2.3.6.136 – Smart Arrival Phase 2

- MRZ-Helfer im bestehenden Online-Check-in ergänzt.
- MRZ-Zeilen werden lokal im Browser ausgewertet und füllen vorhandene Reisendenfelder vor.
- OCR-Vorbereitung als optionaler Hinweis ergänzt, ohne externe Cloud und ohne neue Schnittstelle.
- Neue Einstellungen: MRZ-Helfer und OCR-Vorbereitung separat ein-/ausschaltbar.
- Kein neuer Meldeschein, keine neue Housekeeping-Logik, keine parallele Dokumentenablage.

# StayPilot V2.3.6.134

- Kommunikationscenter weiter ausgebaut.
- Neue E-Mails werden im Dashboard per Popup gemeldet und führen direkt ins Kommunikationscenter.
- E-Mail-Editor vergrößert und deutlicher als Arbeitsfläche gestaltet.
- Mehrsprachige Standard-E-Mail-Vorlagen ergänzt.
- Keine Mailserver-Löschung, keine Datenbankänderung.

# V2.3.6.134 – Kommunikationscenter: Editor, Vorlagen, IMAP-Aktionen

- E-Mail-Editor deutlich erweitert: größere Schreibfläche, Schnellplatzhalter, mehr Vorlagen und sichtbare Platzhalter-Leiste.
- Zusätzliche Mailvorlagen: Buchungsbestätigung, Angebots-Nachfassen, Zahlungserinnerung, Check-in, Anreiseinfo, freie Antwort, Storno/Absage.
- Platzhaltergruppen erweitert: Gast, Buchung, Unterkunft, Zahlung, Links/Dokumente und Betrieb.
- Posteingang erhält bewusste IMAP-Aktionen: als gelesen markieren, verschieben, archivieren, in Papierkorb verschieben.
- IMAP-Ordner können ausgelesen und als Zielordner genutzt werden.
- Mailaktionen sind POST/CSRF-geschützt und werden protokolliert.
- Keine automatische Mailserver-Löschung; Papierkorb-Aktion benötigt Bestätigung `MAIL`.
