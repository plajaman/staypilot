'use strict';
/* StayPilot V2.3.6.100 – echte unterschiedliche komplette Website-Vorlagen mit Unterseiten, Vorschau und Entwurfsanlage. */
(() => {
  if (typeof window === 'undefined') return;
  const VERSION = '2.3.6.100';
  const langs = ['de','en','es','pt','fr','it','ca'];
  const esc = v => String(v ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const slugify = v => String(v || 'studio-entwurf').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,56) || 'studio-entwurf';
  const stamp = () => Date.now().toString().slice(-7);
  const notify = (msg, type='success') => typeof toast === 'function' ? toast(msg, type) : alert(msg);
  const callApi = async (action, data) => {
    if (typeof api !== 'function') throw new Error('API nicht geladen. Bitte Seite neu laden.');
    return api(action, { method:'POST', data });
  };

  const pageTranslations = (title, description) => {
    const out = {};
    langs.forEach(lang => out[lang] = { title, navigation_label:title, seo_title:title, seo_description:description || 'StayPilot Studio Entwurfsseite.' });
    return out;
  };
  const blockTranslations = content => {
    const out = {};
    langs.forEach(lang => out[lang] = { title:content.title || '', content:{...content} });
    return out;
  };
  const block = (type, order, content, settings={}) => ({ block_type:type, sort_order:order, active:1, settings, translations:blockTranslations(content) });
  const item = (icon,title,text) => ({ icon, title, text });
  const btnAsk = { button_label:'Unverbindlich anfragen', button_url:'buchung.php#booking-search' };
  const btnCheck = { button_label:'Verfügbarkeit prüfen', button_url:'buchung.php#booking-search' };

  function hero(title, subtitle, eyebrow, variant='booking', align='left') {
    return block('hero',10,{ eyebrow, title, subtitle, content_html:`<p>${subtitle}</p>`, ...btnAsk },{ height:'xlarge', alignment:align, variant });
  }
  function gallery(title='Große Bildergalerie', subtitle='Haus, Wohnung, Balkon, Pool und Umgebung als starke visuelle Bühne.') {
    return block('gallery',20,{title, subtitle, content_html:'<p>Bilder können später aus der Medienbibliothek ersetzt, sortiert und beschriftet werden.</p>'},{columns:5,variant:'mosaic',ratio:'booking'});
  }
  function amenities(title='Beliebte Ausstattung', family=false) {
    const base = family ? [
      item('👨‍👩‍👧‍👦','Familienfreundlich','Mehr Platz, kurze Wege und praktische Ausstattung.'), item('🛏️','Separate Schlafbereiche','Ideal für Eltern und Kinder.'), item('🍳','Küche','Selbstversorgung leicht gemacht.'), item('🏖️','Strandnähe','Kurze Wege zum Meer.'), item('🧺','Wäsche','Bettwäsche und Handtücher nach Leistung.'), item('🧸','Kinderwünsche','Hinweise können in der Anfrage angegeben werden.')
    ] : [
      item('🏠','Apartments','Eigene Ferienwohnungen mit klarer Ausstattung.'), item('📶','WLAN inklusive','Internet je nach Haus und Verfügbarkeit.'), item('☕','Frühstück optional','Optionen können im Angebot ergänzt werden.'), item('🏊','Pool','Gemeinschaftsbereiche je nach Anlage.'), item('🌿','Balkon / Terrasse','Viele Wohnungen mit Außenbereich.'), item('🅿️','Parken','Parkoptionen je nach Haus.')
    ];
    return block('icon_cards',30,{title,subtitle:'Schnell erfassbar mit Icons.',items:base},{columns:3,variant:family?'family':'booking'});
  }
  function priceNotice(title='Preis, Zahlung & Storno') {
    return block('price_notice',45,{title,subtitle:'Transparent vor der Anfrage.',content_html:'<ul><li>Sie zahlen jetzt noch nichts.</li><li>Verfügbarkeit wird intern geprüft.</li><li>Anzahlung und Restzahlung werden im Angebot klar angezeigt.</li><li>Storno- und Zahlungsbedingungen sind sichtbar.</li></ul>',...btnAsk},{variant:'accent'});
  }
  function searchBox() { return block('booking_search',25,{title:'Reisedaten wählen',subtitle:'Anreise, Abreise und Personen eintragen.'},{variant:'soft'}); }
  function typeCards(title='Wohnungstypen entdecken') { return block('type_grid',35,{title,subtitle:'Alle Kategorien als professionelle Angebotskarten.',content_html:'<p>Bild, Ausstattung, Preis-/Anfragehinweis und Verfügbarkeit werden klar dargestellt.</p>'},{layout:'offer_cards',show_price:1,show_availability:1,show_amenities:1}); }
  function comparison(title='Vergleich & Preistabelle') {
    return block('split_cards',40,{title,subtitle:'Wohnungstypen, Gäste, Ausstattung, Preis und Anfrage nebeneinander.',items:[item('👥','2–4 Personen','Kompakte Kategorie mit guter Ausstattung.'),item('👨‍👩‍👧‍👦','4–6 Personen','Familienfreundliche Kategorie mit mehr Platz.'),item('🌊','Lagewunsch','Meerblick, ruhig oder hausnah als Wunsch möglich.'),item('💶','Preis auf Anfrage','Preis wird aus Saison, Personen und Extras berechnet.')]},{columns:4,variant:'table'});
  }
  function mapBlock() { return block('map',80,{title:'Lage & Umgebung',subtitle:'Karte, Adresse, Strand, Zentrum und Anfahrt.',content_html:'<p>OSM-Karte, Adresse und Umgebungsinfos werden in den Website-Einstellungen gepflegt.</p>'},{variant:'soft'}); }
  function reviews(title='Gästestimmen & Vertrauen') { return block('reviews',70,{title,subtitle:'Soziale Sicherheit für die Entscheidung.',items:[item('⭐','Sehr gute Lage','Strand, Zentrum und Restaurants gut erreichbar.'),item('⭐','Praktische Ausstattung','Gut geeignet für Urlaub, Familie und längere Aufenthalte.'),item('⭐','Persönliche Betreuung','Direkte Hilfe bei Fragen vor und während der Reise.')]},{variant:'soft'}); }
  function faq(title='Häufige Fragen') { return block('accordion',90,{title,subtitle:'Antworten vor der Anfrage.',items:[{title:'Kann ich eine bestimmte Wohnung wählen?',text:'Wünsche können angegeben werden; die Rezeption prüft intern.'},{title:'Zahle ich sofort?',text:'Nein, die Anfrage ist zunächst unverbindlich.'},{title:'Wie schnell erhalte ich Antwort?',text:'Nach Prüfung erhalten Sie Angebot, Rückfrage oder Alternative.'},{title:'Sind Haustiere möglich?',text:'Bitte vorab anfragen.'}]},{variant:'soft'}); }
  function process() { return block('timeline',60,{title:'So läuft es ab',subtitle:'Einfacher Ablauf von Anfrage bis Bestätigung.',items:[item('1','Reisedaten senden','Zeitraum, Personen und Wunschtyp angeben.'),item('2','Interne Prüfung','Verfügbarkeit und passende Wohnung werden geprüft.'),item('3','Angebot erhalten','Preis, Zahlungsplan und Hinweise werden gesendet.'),item('4','Bestätigen','Nach Annahme wird intern zugewiesen und bestätigt.')]},{variant:'soft'}); }
  function inquiryForm() { return block('custom_form',55,{title:'Unverbindliche Preisanfrage',subtitle:'Reisedaten, Wünsche und Kontakt senden.',content_html:'<p>Das Formular bleibt in StayPilot bearbeitbar: Felder, Platzhalter, Datenschutz und Erfolgstext.</p>',button_label:'Anfrage senden'},{variant:'soft'}); }
  function cta(title='Noch Fragen zur passenden Wohnung?') { return block('cta_banner',100,{title,subtitle:'Senden Sie eine unverbindliche Anfrage.',content_html:'<p>Wir helfen bei Zeitraum, Kategorie, Lagewunsch und Belegung.</p>',...btnAsk},{variant:'premium'}); }

  const pageSets = {
    // Booking-Style: sachlich, vergleichbar, starke Galerie und Preisbox.
    booking_overview: [hero('GoettenMar Platja d’Aro','Unterkunftsseite mit Galerie, Lage, Ausstattung, Verfügbarkeit und persönlicher Anfrage.','Booking-Logik','booking','left'), gallery('Bilder & Highlights'), amenities('Beliebte Ausstattung'), searchBox(), priceNotice(), reviews(), mapBlock(), cta()],
    booking_detail: [hero('Apartment mit klarer Ausstattung','Alle wichtigen Informationen wie auf einer professionellen Unterkunftsseite.','Unterkunftsdetail','booking','left'), gallery('Fotogalerie der Unterkunft'), amenities('Ausstattung im Überblick'), comparison('Optionen und Kategorien'), priceNotice(), faq('Hausregeln & Hinweise'), reviews(), cta()],
    booking_prices: [hero('Verfügbarkeit & Preise','Reisedaten prüfen, Optionen vergleichen und unverbindlich anfragen.','Preisübersicht','booking','left'), searchBox(), comparison('Wohnungstypen vergleichen'), priceNotice('Zahlung, Anzahlung und Storno'), process(), inquiryForm()],
    booking_location: [hero('Lage & Umgebung','Strand, Zentrum, Anfahrt und wichtige Orte klar erklären.','Lage','booking','left'), mapBlock(), block('split_cards',35,{title:'Umgebung schnell erklärt',subtitle:'Was Gäste vor der Buchung wissen möchten.',items:[item('🏖️','Strand','Entfernung und Wegbeschreibung.'),item('🍽️','Restaurants','Empfehlungen in der Nähe.'),item('🛒','Supermarkt','Einkaufen für Selbstversorger.'),item('🚗','Anreise','Parken, Flughäfen und Transfer.')]},{columns:4,variant:'soft'}), cta()],
    booking_inquiry: [hero('Unverbindlich anfragen','Reisedaten senden, passende Wohnung prüfen lassen und Angebot erhalten.','Anfrage','booking','left'), searchBox(), inquiryForm(), process(), priceNotice(), faq(), cta()],

    // Marketing: emotional, Nutzenargumente, Landingpage-Führung.
    marketing_home: [hero('Mehr Urlaub. Weniger Umwege.','Direkt anfragen, persönlich beraten werden und die passende Ferienwohnung finden.','High-End Marketing','premium','center'), block('image_text',20,{eyebrow:'Direkt beim Gastgeber',title:'Warum direkt buchen?',subtitle:'Persönliche Antwort statt anonymer Plattform.',content_html:'<p>Diese Seite verkauft über Emotion, Vertrauen, Nutzenversprechen und eine klare Anfrageführung.</p>',...btnAsk},{layout:'image_left',variant:'premium'}), block('icon_cards',30,{title:'Ihre Direktbucher-Vorteile',subtitle:'Marketing-Argumente klar sichtbar.',items:[item('💬','Persönliche Beratung','Wir prüfen Zeitraum, Typ und Alternativen.'),item('💶','Transparente Konditionen','Preis, Extras und Zahlung klar erklärt.'),item('🧭','Lokale Erfahrung','Tipps zu Strand, Restaurants und Umgebung.'),item('🤝','Direkter Kontakt','Kein anonymes Portal.')]},{columns:4,variant:'premium'}), typeCards('Apartments für Ihren Urlaub'), reviews('Warum Gäste wiederkommen'), cta('Jetzt Wunschurlaub anfragen')],
    marketing_why: [hero('Warum direkt bei uns anfragen?','Direkter Kontakt, bessere Klärung, passende Wohnung und klare Konditionen.','Direktbucher-Vorteile','premium','center'), process(), priceNotice('Keine Sofortzahlung – erst prüfen'), reviews(), faq('Fragen zur Direktanfrage'), cta('Direkte Anfrage senden')],
    marketing_experience: [hero('Costa Brava erleben','Strand, Spaziergänge, Restaurants und Apartments als emotionale Urlaubsstory.','Erlebnis & Umgebung','premium','center'), block('image_text',20,{eyebrow:'Urlaubsgefühl',title:'Wohnen nah am Meer',subtitle:'Eine Seite für Atmosphäre, Bilder und Erlebnisse.',content_html:'<p>Beschreiben Sie Strand, Promenade, Gastronomie, Ausflüge und besondere Vorteile Ihres Standorts.</p>',...btnAsk},{layout:'image_right',variant:'premium'}), mapBlock(), reviews(), cta('Urlaubserlebnis anfragen')],
    marketing_offers: [hero('Saisonangebote & Direktanfrage','Aktionen, Frühbucher, Langzeitaufenthalt oder Familienwochen hervorheben.','Angebote','premium','center'), block('cta_banner',25,{title:'Saisonangebot hervorheben',subtitle:'Ideal für Frühbucher, Familienwochen oder längere Aufenthalte.',content_html:'<p>Ein starker Marketing-Abschnitt für Aktionen und direkte Anfragen.</p>',button_label:'Angebot anfragen',button_url:'buchung.php#booking-search'},{variant:'marketing'}), comparison('Beispielhafte Angebotsbereiche'), inquiryForm(), cta()],

    // Portal: mehrere Häuser/Typen, Übersicht und Filterlogik.
    portal_home: [hero('Apartment-Portal','Viele Wohnungstypen, klare Suche und schnelle Anfrage in einem Portal.','Portal','portal','left'), searchBox(), typeCards('Alle Wohnungstypen'), comparison('Typen schnell vergleichen'), amenities('Ausstattung quer über alle Typen'), mapBlock(), cta()],
    portal_types: [hero('Wohnungstypen Übersicht','Kategorien vergleichen und passenden Typ wählen.','Typenportal','portal','left'), typeCards('Wohnungstypen als Angebotskarten'), comparison('Typenvergleich'), amenities('Ausstattung nach Kategorien'), priceNotice(), cta()],
    portal_faq: [hero('Fragen zum Aufenthalt','Alles Wichtige zu Anfrage, Zahlung, Ausstattung und Anreise.','FAQ','portal','left'), faq(), process(), priceNotice(), mapBlock()],

    // Inquiry: Ablauf und Formular stehen im Vordergrund.
    inquiry_home: [hero('Ihre Anfrage ist der erste Schritt','Wir prüfen die passende Wohnung, bevor etwas verbindlich wird.','Anfrage-Fokus','inquiry','left'), searchBox(), inquiryForm(), process(), priceNotice(), faq(), cta()],
    inquiry_flow: [hero('So funktioniert die Anfrage','Vom Wunschzeitraum bis zur geprüften Bestätigung.', 'Ablauf','inquiry','left'), process(), block('icon_cards',35,{title:'Warum erst prüfen?',subtitle:'Sicherer Ablauf für Gast und Rezeption.',items:[item('🏡','Passende Wohnung','Zuteilung erfolgt intern.'),item('🔁','Alternativen','Bei Engpässen werden Alternativen vorgeschlagen.'),item('📩','Rückfrage möglich','Offene Punkte werden geklärt.'),item('✅','Bestätigung','Erst danach wird verbindlich bestätigt.')]},{columns:4,variant:'soft'}), inquiryForm(), cta()],

    // Family: familienorientiert, weniger Tabelle, mehr Vertrauen und Umgebung.
    family_home: [hero('Familienurlaub an der Costa Brava','Apartments mit Platz, Küche, Strandnähe und persönlicher Anfrage.','Familienurlaub','family','left'), amenities('Für Familien wichtig', true), block('split_cards',40,{title:'Warum Familien diese Struktur verstehen',subtitle:'Klare Infos vor der Anfrage.',items:[item('🏖️','Strandnah','Kurze Wege sind für Familien wichtig.'),item('🍳','Selbstversorgung','Küche und flexible Mahlzeiten.'),item('🧸','Kinderangaben','Alter und Wünsche direkt im Formular.'),item('🛏️','Schlafaufteilung','Betten und Zimmer klar beschreiben.')]},{columns:4,variant:'family'}), mapBlock(), reviews(), inquiryForm(), cta('Familienreise anfragen')],
    family_apartments: [hero('Familien-Apartments vergleichen','Größe, Schlafplätze, Ausstattung und Lage einfach vergleichen.','Familien-Apartments','family','left'), typeCards('Familienfreundliche Typen'), comparison('Typen für Familien'), amenities('Familienausstattung', true), priceNotice(), cta()],

    // Premium calm: fewer cards, more atmosphere.
    premium_home: [hero('Premium Ferienwohnung','Ruhige hochwertige Darstellung mit Atmosphäre, Bildern und persönlichem Kontakt.','Premium','premium','center'), gallery('Bilder & Atmosphäre'), block('image_text',30,{eyebrow:'Wertige Präsentation',title:'Wohnen mit Ruhe und Komfort',subtitle:'Mehr Bild, weniger Tabelle – für hochwertige Ferienwohnungen.',content_html:'<p>Diese Vorlage wirkt ruhiger, edler und stärker imageorientiert.</p>',...btnAsk},{layout:'image_left',variant:'premium'}), amenities('Komfort & Ausstattung'), reviews(), cta('Premium-Aufenthalt anfragen')],
    premium_booking: [hero('Buchung & persönliche Anfrage','Preis, Zahlung und Storno klar, aber ruhig dargestellt.','Premium Anfrage','premium','center'), priceNotice(), inquiryForm(), process(), faq(), cta()]
  };

  const templates = {
    booking_full:{badge:'Booking-Logik',theme:'booking',title:'Booking-ähnliche komplette Unterkunftswebseite',subtitle:'Sachliche Unterkunftslogik: Galerie, Detailseite, Preise, Lage, Hausregeln, Bewertungen und Anfrage.',pages:[['Übersicht','booking_overview'],['Unterkunftsdetail','booking_detail'],['Verfügbarkeit & Preise','booking_prices'],['Lage & Umgebung','booking_location'],['Preisanfrage','booking_inquiry']]},
    marketing_highend:{badge:'Marketing High-End',theme:'marketing',title:'High-End Marketing-Webseite',subtitle:'Emotionale Verkaufsseite: Nutzen, Vertrauen, Direktbucher-Vorteile, Saisonangebot und klare Anfrage.',pages:[['Marketing-Startseite','marketing_home'],['Warum direkt buchen','marketing_why'],['Urlaubserlebnis','marketing_experience'],['Angebote & Saison','marketing_offers'],['Anfrage','booking_inquiry']]},
    apartment_portal:{badge:'Apartment-Portal',theme:'portal',title:'Apartment-Portal für viele Typen',subtitle:'Für Betriebe mit mehreren Häusern und Typen: Suche, Übersicht, Vergleich, FAQ und Anfrage.',pages:[['Portal Startseite','portal_home'],['Wohnungstypen','portal_types'],['Verfügbarkeit & Preise','booking_prices'],['FAQ & Hinweise','portal_faq'],['Kontakt & Lage','booking_location']]},
    inquiry_site:{badge:'Anfrage-Fokus',theme:'inquiry',title:'Anfrage-orientierte Webseite',subtitle:'Nicht sofort buchen, sondern sauber anfragen, prüfen, Angebot senden und bestätigen.',pages:[['Anfrage Startseite','inquiry_home'],['So funktioniert es','inquiry_flow'],['Wohnungstypen','portal_types'],['Preisanfrage','booking_inquiry'],['Kontakt','booking_location']]},
    family_costa:{badge:'Familienurlaub',theme:'family',title:'Familienurlaub Costa Brava',subtitle:'Zielgruppen-Vorlage für Familien: Platz, Strandnähe, Kinderwünsche, Ausstattung und Anfrage.',pages:[['Familienurlaub Start','family_home'],['Familien-Apartments','family_apartments'],['Strand & Umgebung','booking_location'],['FAQ für Familien','portal_faq'],['Preise & Anfrage','booking_prices']]},
    premium_site:{badge:'Premium ruhig',theme:'premium',title:'Premium Ferienwohnung',subtitle:'Ruhige hochwertige Darstellung für einzelne Premium-Unterkunft mit Atmosphäre und persönlicher Anfrage.',pages:[['Premium Startseite','premium_home'],['Bilder & Atmosphäre','booking_detail'],['Buchung & Anfrage','premium_booking'],['Lage & Kontakt','booking_location']]}
  };

  function ensureStyle(){
    if (document.getElementById('sp100WebsiteTemplatesStyle')) return;
    document.getElementById('sp99WebsiteTemplatesPanel')?.remove();
    const css = document.createElement('style');
    css.id = 'sp100WebsiteTemplatesStyle';
    css.textContent = `
      #sp99WebsiteTemplatesPanel{display:none!important}.sp100-template-zone{margin:12px 0 16px;padding:18px;border:2px solid #2563eb;border-radius:24px;background:linear-gradient(135deg,#eff6ff,#fff 70%);box-shadow:0 20px 55px rgba(37,99,235,.16)}.sp100-template-zone h3{margin:0;color:#0f172a;font-size:22px}.sp100-template-zone p{color:#475569;margin:6px 0 14px}.sp100-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:14px}.sp100-card{background:#fff;border:1px solid #dbeafe;border-radius:20px;padding:16px;display:grid;gap:10px;position:relative;overflow:hidden}.sp100-card:before{content:"";position:absolute;inset:0 0 auto;height:7px}.sp100-card.booking:before{background:linear-gradient(90deg,#1d4ed8,#60a5fa)}.sp100-card.marketing:before{background:linear-gradient(90deg,#111827,#7c3aed,#f97316)}.sp100-card.portal:before{background:linear-gradient(90deg,#0f766e,#2563eb)}.sp100-card.inquiry:before{background:linear-gradient(90deg,#0f172a,#06b6d4)}.sp100-card.family:before{background:linear-gradient(90deg,#166534,#84cc16)}.sp100-card.premium:before{background:linear-gradient(90deg,#111827,#d4af37)}.sp100-card b{font-size:17px}.sp100-card small{color:#64748b;line-height:1.35}.sp100-badge{width:max-content;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-weight:900;font-size:12px;padding:6px 10px}.sp100-page-list{font-size:12px;color:#334155;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:14px;padding:9px;line-height:1.45}.sp100-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}.sp100-actions button,.sp100-top-actions button{border:1px solid #cbd5e1;background:#fff;border-radius:13px;padding:11px;font-weight:900;cursor:pointer}.sp100-actions .primary,.sp100-top-actions .primary{background:#2563eb;color:#fff;border-color:#2563eb}.sp100-top-actions{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 14px}.sp100-modal{position:fixed;inset:0;z-index:100003;background:rgba(15,23,42,.72);display:flex;align-items:center;justify-content:center;padding:18px}.sp100-inner{width:min(1280px,97vw);max-height:94vh;overflow:auto;border-radius:24px;background:#fff;box-shadow:0 35px 110px rgba(0,0,0,.38)}.sp100-head{position:sticky;top:0;z-index:3;background:#fff;border-bottom:1px solid #e2e8f0;padding:16px;display:flex;justify-content:space-between;align-items:center;gap:12px}.sp100-head h2{margin:0;font-size:24px}.sp100-head button{border:1px solid #cbd5e1;background:#fff;border-radius:12px;padding:9px 12px;font-weight:900;cursor:pointer}.sp100-body{padding:16px}.sp100-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.sp100-tabs button{border:1px solid #cbd5e1;background:#fff;border-radius:999px;padding:8px 12px;font-weight:900;cursor:pointer}.sp100-tabs button.active{background:#2563eb;border-color:#2563eb;color:#fff}.sp100-page{display:none}.sp100-page.active{display:block}.sp100-preview{border:1px solid #dbeafe;border-radius:20px;overflow:hidden;background:#f8fafc}.sp100-hero{min-height:360px;padding:48px;display:flex;align-items:flex-end;color:#fff}.sp100-hero.booking{background:linear-gradient(135deg,#172554,#1d4ed8)}.sp100-hero.marketing{background:radial-gradient(circle at 20% 20%,#f97316 0,#7c3aed 38%,#111827 78%)}.sp100-hero.portal{background:linear-gradient(135deg,#0f766e,#2563eb)}.sp100-hero.inquiry{background:linear-gradient(135deg,#1e293b,#0891b2)}.sp100-hero.family{background:linear-gradient(135deg,#166534,#84cc16)}.sp100-hero.premium{background:linear-gradient(135deg,#111827,#374151 60%,#d4af37)}.sp100-hero h1{font-size:46px;line-height:1.04;margin:0 0 10px}.sp100-hero p{font-size:19px;max-width:820px}.sp100-section{padding:30px;background:#fff;border-top:1px solid #e5e7eb}.sp100-layout{display:grid;grid-template-columns:2fr 1fr;gap:20px}.sp100-gallery{display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px}.sp100-img{min-height:150px;border-radius:16px;background:linear-gradient(135deg,#dbeafe,#94a3b8)}.sp100-cardgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}.sp100-mini{border:1px solid #e2e8f0;border-radius:16px;padding:14px;background:#fff}.sp100-side{border:1px solid #bfdbfe;border-radius:20px;background:#eff6ff;padding:18px;position:sticky;top:90px}.sp100-table{width:100%;border-collapse:collapse;border:1px solid #dbeafe;border-radius:14px;overflow:hidden}.sp100-table th{background:#1d4ed8;color:#fff;text-align:left}.sp100-table th,.sp100-table td{padding:10px;border-bottom:1px solid #e2e8f0}.sp100-bottom{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.sp100-bottom button{border:1px solid #cbd5e1;background:#fff;border-radius:13px;padding:10px 14px;font-weight:900;cursor:pointer}.sp100-bottom .primary{background:#2563eb;color:#fff;border-color:#2563eb}@media(max-width:900px){.sp100-layout{grid-template-columns:1fr}.sp100-gallery{grid-template-columns:1fr}.sp100-hero h1{font-size:32px}.sp100-hero{padding:32px}}
    `;
    document.head.appendChild(css);
  }

  function pagesText(tpl){ return tpl.pages.map(p => p[0]).join(' · '); }
  function templateCardsHtml(){
    return Object.entries(templates).map(([key,t]) => `<article class="sp100-card ${esc(t.theme)}"><span class="sp100-badge">${esc(t.badge)}</span><b>${esc(t.title)}</b><small>${esc(t.subtitle)}</small><div class="sp100-page-list"><b>${t.pages.length} unterschiedliche Unterseiten:</b><br>${esc(pagesText(t))}</div><div class="sp100-actions"><button data-sp100-preview="${esc(key)}">Vorschau öffnen</button><button class="primary" data-sp100-save="${esc(key)}">Komplett als Entwurf</button></div></article>`).join('');
  }
  function injectPanel(){
    if (!location.hash.includes('website')) return;
    const strip = document.querySelector('.sp87-action-strip,.sp91-action-strip');
    if (!strip) return;
    ensureStyle();
    document.getElementById('sp99WebsiteTemplatesPanel')?.remove();
    if (document.getElementById('sp100WebsiteTemplatesPanel')) return;
    const panel = document.createElement('section');
    panel.id = 'sp100WebsiteTemplatesPanel';
    panel.className = 'sp100-template-zone';
    panel.innerHTML = `<h3>🌐 Komplette Website laden – echte unterschiedliche Vorlagen V2.3.6.100</h3><p>Jede Vorlage enthält eigene Unterseiten, andere Struktur und andere Marketing-Logik. Erst Vorschau ansehen, dann als Entwurf speichern. Die aktive Webseite bleibt unverändert.</p><div class="sp100-top-actions"><button data-sp100-scroll>Vorlagen anzeigen</button><button class="primary" data-sp100-preview="booking_full">Booking-ähnliche Vorlage ansehen</button><button data-sp100-preview="marketing_highend">High-End-Marketing ansehen</button></div><div class="sp100-grid">${templateCardsHtml()}</div>`;
    strip.insertAdjacentElement('afterend', panel);
  }

  function previewHtml(pageName, pageKind, theme){
    const blocks = pageSets[pageKind] || pageSets.booking_overview;
    const content = b => b.translations.de.content || {};
    const first = content(blocks[0]);
    const titles = blocks.map(b => content(b).title).filter(Boolean);
    const isPrice = /price|preise|vergleich/i.test(pageKind);
    const isInquiry = /inquiry|anfrage/i.test(pageKind);
    const isLocation = /location|lage|contact/i.test(pageKind);
    const isMarketing = theme === 'marketing';
    const table = `<table class="sp100-table"><tr><th>Typ</th><th>Gäste</th><th>Vorteil</th><th>Aktion</th></tr><tr><td>2 BM</td><td>2–4</td><td>kompakt und strandnah</td><td>Anfragen</td></tr><tr><td>5 PM</td><td>4–6</td><td>familienfreundlich</td><td>Anfragen</td></tr></table>`;
    const cards = titles.slice(1,9).map((t,i)=>`<div class="sp100-mini"><b>${['🏡','📸','💶','📶','🗺️','⭐','✅','📩'][i%8]} ${esc(t)}</b><p>${isMarketing?'Marketing-Abschnitt mit Nutzenargument.':'Bearbeitbarer Abschnitt dieser Unterseite.'}</p></div>`).join('');
    const middle = isPrice ? table : isLocation ? `<div class="sp100-cardgrid"><div class="sp100-mini">📍 Adresse & Karte</div><div class="sp100-mini">🏖️ Strandentfernung</div><div class="sp100-mini">🍽️ Restaurants</div><div class="sp100-mini">🅿️ Parken</div></div>` : isMarketing ? `<div class="sp100-cardgrid"><div class="sp100-mini">💬 Persönliche Beratung</div><div class="sp100-mini">💶 Klare Konditionen</div><div class="sp100-mini">🧭 Lokale Erfahrung</div><div class="sp100-mini">🤝 Direktkontakt</div></div>` : `<div class="sp100-gallery"><div class="sp100-img"></div><div class="sp100-img"></div><div class="sp100-img"></div></div>`;
    return `<div class="sp100-preview"><div class="sp100-hero ${esc(theme)}"><div><small>STAYPILOT WEBSITE-PAKET · ${esc(pageName)}</small><h1>${esc(first.title || pageName)}</h1><p>${esc(first.subtitle || 'Komplette Unterseite als Vorschau.')}</p></div></div><div class="sp100-section"><div class="sp100-layout"><main><h2>${isPrice?'Preise, Optionen und Vergleich':isLocation?'Lage, Kontakt und Umgebung':isInquiry?'Anfrage und Ablauf':'Inhalt dieser Unterseite'}</h2>${middle}</main><aside><div class="sp100-side"><h2>${isInquiry?'Anfrageformular':'Preis-/Anfragebox'}</h2><p>Sie zahlen jetzt noch nichts. Verfügbarkeit wird intern geprüft.</p><button class="btn primary">Unverbindlich anfragen</button></div></aside></div></div><div class="sp100-section"><h2>Bearbeitbare Abschnitte</h2><div class="sp100-cardgrid">${cards}</div></div></div>`;
  }

  function openPreview(key){
    const tpl = templates[key]; if (!tpl) return;
    ensureStyle();
    const tabs = tpl.pages.map(([name],i)=>`<button data-sp100-tab="${i}" class="${i===0?'active':''}">${esc(name)}</button>`).join('');
    const pages = tpl.pages.map(([name,kind],i)=>`<section class="sp100-page ${i===0?'active':''}" data-sp100-page="${i}"><h3>${esc(name)}</h3>${previewHtml(name,kind,tpl.theme)}</section>`).join('');
    const overlay = document.createElement('div');
    overlay.className = 'sp100-modal';
    overlay.innerHTML = `<div class="sp100-inner"><div class="sp100-head"><div><h2>${esc(tpl.title)}</h2><small>${esc(tpl.subtitle)} · ${tpl.pages.length} echte Unterseiten</small></div><button data-sp100-close>Schließen</button></div><div class="sp100-body"><div class="sp100-tabs">${tabs}</div>${pages}<div class="sp100-bottom"><button data-sp100-close>Nur ansehen</button><button class="primary" data-sp100-save="${esc(key)}">Komplett als Entwurf speichern</button></div></div></div>`;
    document.body.appendChild(overlay);
  }

  async function createPage(title, prefix, sort) {
    const slug = slugify(prefix + '-' + title) + '-' + stamp();
    const out = await callApi('save_site_page_v218', { id:0, title_fallback:title, slug, status:'draft', show_header:0, show_footer:0, sort_order:sort, translations:pageTranslations(title, 'Entwurfsseite aus kompletter StayPilot Website-Vorlage V2.3.6.100.') });
    return Number(out.id || 0);
  }
  async function saveDraft(key){
    const tpl = templates[key]; if (!tpl) return;
    if (!confirm(`Die komplette Vorlage "${tpl.title}" mit ${tpl.pages.length} unterschiedlichen Unterseiten als Entwurf speichern? Die aktive Webseite wird nicht überschrieben.`)) return;
    const prefix = `Entwurf ${tpl.badge}`;
    for (let i=0;i<tpl.pages.length;i++) {
      const [pageName, kind] = tpl.pages[i];
      const pageId = await createPage(`${prefix} · ${pageName}`, prefix, 400+i);
      const blocks = pageSets[kind] || pageSets.booking_overview;
      for (let n=0;n<blocks.length;n++) {
        const b = blocks[n];
        await callApi('save_site_block_v218', { id:0, page_id:pageId, block_type:b.block_type, sort_order:b.sort_order+n, active:1, settings:b.settings || {}, translations:b.translations });
      }
    }
    notify(`Vorlage "${tpl.title}" wurde als kompletter Entwurf mit ${tpl.pages.length} Unterseiten gespeichert.`);
    document.querySelectorAll('.sp100-modal').forEach(m=>m.remove());
    if (typeof renderPage === 'function') setTimeout(() => renderPage(), 400);
  }

  document.addEventListener('click', ev => {
    const close = ev.target.closest?.('[data-sp100-close]');
    if (close) { ev.preventDefault(); close.closest('.sp100-modal')?.remove(); return; }
    const tab = ev.target.closest?.('[data-sp100-tab]');
    if (tab) { ev.preventDefault(); const root=tab.closest('.sp100-modal'); root?.querySelectorAll('[data-sp100-tab]').forEach(b=>b.classList.toggle('active', b===tab)); root?.querySelectorAll('[data-sp100-page]').forEach(p=>p.classList.toggle('active', p.dataset.sp100Page===tab.dataset.sp100Tab)); return; }
    const prev = ev.target.closest?.('[data-sp100-preview]');
    if (prev) { ev.preventDefault(); openPreview(prev.dataset.sp100Preview); return; }
    const save = ev.target.closest?.('[data-sp100-save]');
    if (save) { ev.preventDefault(); saveDraft(save.dataset.sp100Save).catch(e => notify(e.message || 'Vorlage konnte nicht gespeichert werden.', 'error')); return; }
    const scroll = ev.target.closest?.('[data-sp100-scroll]');
    if (scroll) { ev.preventDefault(); document.getElementById('sp100WebsiteTemplatesPanel')?.scrollIntoView({behavior:'smooth',block:'start'}); }
  }, true);

  const mo = new MutationObserver(() => injectPanel());
  mo.observe(document.documentElement, { childList:true, subtree:true });
  window.addEventListener('hashchange', () => setTimeout(injectPanel, 250));
  setTimeout(injectPanel, 450);
})();
