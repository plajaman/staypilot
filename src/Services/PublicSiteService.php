<?php
declare(strict_types=1);

/**
 * Gemeinsame Ausgabe- und Datenlogik für die öffentliche StayPilot-Webseite.
 *
 * Die Klasse trennt bewusst öffentliche Webseite, Angebotsseiten und interne
 * Buchungsverwaltung. Konkrete Apartmentnummern werden hier niemals ausgegeben.
 */
final class PublicSiteService
{
    public const BLOCK_TYPES = [
        'hero' => 'Großer Titelbereich',
        'booking_search' => 'Buchungssuche',
        'type_grid' => 'Wohnungstypen',
        'rich_text' => 'Formatierter Text',
        'image_text' => 'Bild und Text',
        'features' => 'Vorteile / Merkmale',
        'icon_cards' => 'Icon-Karten',
        'split_cards' => '2–3 Spalten Karten',
        'cta_banner' => 'Großer Aktionsbanner',
        'price_notice' => 'Preis-/Saison-Hinweis',
        'seasonal_notice' => 'Saison- oder Angebotsbox',
        'availability_teaser' => 'Verfügbarkeits-Hinweis',
        'trust_badges' => 'Vertrauens-Siegel',
        'stats' => 'Zahlen / Kennzahlen',
        'timeline' => 'Ablauf / Timeline',
        'tabs' => 'Reiter / Tabs',
        'faq' => 'Häufige Fragen',
        'accordion' => 'Akkordeon',
        'contact' => 'Kontaktbereich',
        'map' => 'Anfahrt / Karte',
        'team' => 'Team / Ansprechpartner',
        'custom_form' => 'Formular',
        'gallery' => 'Bildergalerie',
        'reviews' => 'Bewertungen',
        'download_links' => 'Downloads / Links',
        'video_embed' => 'Video / Medien',
        'custom_html' => 'HTML-Block',
        'spacer' => 'Abstand / Trennlinie',
    ];

    public static function languages(): array
    {
        return OfferService::LANGUAGES;
    }

    public static function language(?string $language): string
    {
        $language = strtolower(trim((string)$language));
        if (array_key_exists($language, self::languages())) return $language;
        $default = strtolower(trim((string)setting('public_booking_default_language', 'de')));
        return array_key_exists($default, self::languages()) ? $default : 'de';
    }

    public static function defaultDesign(): array
    {
        return [
            'logo_url' => '',
            'favicon_url' => '',
            'primary' => (string)setting('accent_color', '#2563eb'),
            'secondary' => '#0f766e',
            'background' => '#f4f7fb',
            'surface' => '#ffffff',
            'text' => '#172033',
            'muted' => '#62708a',
            'header_background' => '#101827',
            'header_text' => '#ffffff',
            'footer_background' => '#101827',
            'footer_text' => '#dbe5f3',
            'footer_heading' => '#ffffff',
            'footer_link' => '#dbe5f3',
            'footer_columns' => 3,
            'contact_background' => '#ffffff',
            'contact_text' => '#172033',
            'contact_accent' => '#2563eb',
            'contact_map_enabled' => 0,
            'contact_map_lat' => '',
            'contact_map_lng' => '',
            'contact_map_zoom' => 15,
            'contact_map_height' => 320,
            'contact_map_link' => '',
            'booking_search_background' => '#ffffff',
            'booking_search_surface' => '#ffffff',
            'booking_search_text' => '#172033',
            'booking_search_heading' => '#172033',
            'booking_search_button_bg' => '#2563eb',
            'booking_search_button_text' => '#ffffff',
            'booking_search_radius' => 18,
            'booking_search_shadow' => 1,
            'inquiry_show_summary' => 1,
            'inquiry_show_top_notice' => 1,
            'inquiry_phone_required' => 0,
            'inquiry_country_required' => 0,
            'inquiry_show_breakfast' => 1,
            'inquiry_show_half_board' => 1,
            'inquiry_show_contact_preference' => 1,
            'inquiry_show_arrival_time' => 1,
            'inquiry_show_location_request' => 1,
            'inquiry_show_special_occasion' => 1,
            'inquiry_privacy_required' => 1,
            'inquiry_marketing_consent' => 0,
            'custom_forms' => [
                ['active'=>1,'name'=>'Kontaktformular','recipient'=>'','success'=>'Vielen Dank. Ihre Nachricht wurde gesendet.','fields'=>"Name|text|1|\nE-Mail|email|1|\nNachricht|textarea|1|"],
                ['active'=>0,'name'=>'Formular 2','recipient'=>'','success'=>'Vielen Dank. Ihre Nachricht wurde gesendet.','fields'=>"Name|text|1|\nE-Mail|email|1|\nNachricht|textarea|1|"],
                ['active'=>0,'name'=>'Formular 3','recipient'=>'','success'=>'Vielen Dank. Ihre Nachricht wurde gesendet.','fields'=>"Name|text|1|\nE-Mail|email|1|\nNachricht|textarea|1|"],
            ],
            'cookie_enabled' => 1,
            'cookie_position' => 'bottom',
            'cookie_margin' => 18,
            'cookie_padding' => 18,
            'cookie_radius' => 18,
            'cookie_background' => '#ffffff',
            'cookie_text' => '#172033',
            'cookie_accent' => '#2563eb',
            'cookie_notice' => 'Wir nutzen notwendige Cookies. Weitere Dienste koennen Sie freiwillig erlauben.',
            'cookie_blocked_text' => 'Dieser Inhalt ist deaktiviert, weil die erforderliche Cookie-Kategorie nicht erlaubt wurde.',
            'cookie_services' => "StayPilot Sitzung|necessary|staypilot_session|Login, Sicherheit und Formularfunktion|1\nCookie-Auswahl|necessary|staypilot_cookie_consent_v1|Speichert Ihre Cookie-Entscheidung|1\nSprache und Komfort|preferences|staypilot-site-page,staypilot_cookie_consent_v1|Merkt sich hilfreiche Einstellungen|1\nStatistik|statistics|_ga,_gid,_pk_id|Optionale Reichweitenmessung|0\nMarketing / Externe Medien|marketing|_fbp,fr|Optionale externe Inhalte und Kampagnenmessung|0",
            'font' => 'system',
            'content_width' => 1180,
            'radius' => 18,
            'button_style' => 'rounded',
            'type_card_layout' => 'portrait',
            'type_slider_interval' => 4200,
            'type_detail_gallery_layout' => 'magazine',
            'customer_portal_layout' => 'modern',
            'card_shadow' => 1,
            'sticky_header' => 1,
            'show_admin_link' => 0,
            'phone' => '',
            'email' => '',
            'address' => '',
            'facebook_url' => '',
            'instagram_url' => '',
            'copyright' => '',
        ];
    }

    public static function defaultLabels(): array
    {
        return [
            'de' => [
                'language'=>'Sprache','administration'=>'Verwaltung','arrival'=>'Anreise','departure'=>'Abreise','adults'=>'Erwachsene','children'=>'Kinder','babies'=>'Babys','search'=>'Verfügbarkeit prüfen','choose_dates'=>'Wählen Sie Ihren Reisezeitraum.','disabled'=>'Öffentliche Buchungsanfragen sind derzeit deaktiviert.','loading'=>'Verfügbarkeit und Preise werden geprüft …','none'=>'Für diesen Zeitraum ist kein passender Wohnungstyp verfügbar.','nights'=>'Nächte','bedrooms'=>'Schlafzimmer','beds'=>'Betten','sqm'=>'m²','available'=>'verfügbar','from_total'=>'Gesamtpreis','price_request'=>'Preis auf Anfrage','request'=>'Unverbindlich anfragen','regular'=>'Regelbelegung','maximum'=>'maximal','capacity_warning'=>'Die Regelbelegung wird überschritten. Eine Anfrage ist nach ausdrücklicher Bestätigung möglich.','capacity_max_warning'=>'Die Maximalbelegung wird überschritten. Diese Ausnahme muss begründet und bestätigt werden.','cancellation'=>'Stornobedingungen','details'=>'Details ansehen','inquiry_title'=>'Unverbindliche Anfrage','summary_type'=>'Wohnungstyp','summary_period'=>'Zeitraum','summary_price'=>'Preis','name'=>'Name','email'=>'E-Mail','phone'=>'Telefon','country'=>'Land','child_ages'=>'Alter der Kinder','age_child'=>'Alter Kind','tax_hint'=>'Das Alter wird für die Touristensteuer benötigt. Steuerpflicht derzeit ab {age} Jahren.','breakfast'=>'Frühstück gewünscht','half_board'=>'Halbpension gewünscht','wish'=>'Ihr Wunsch / Ihre Nachricht','wish_placeholder'=>'Zum Beispiel Lagewunsch, ruhige Wohnung, Nähe zum Aufzug oder besondere Hinweise.','capacity_confirm'=>'Ich habe den Belegungshinweis gelesen und möchte diese Personenzahl anfragen.','capacity_reason'=>'Begründung für die Überschreitung','capacity_reason_placeholder'=>'Bitte kurz beschreiben, warum diese Belegung gewünscht wird.','nonbinding'=>'Die Anfrage ist unverbindlich. Sie wählen den Wohnungstyp; die interne Zuteilung erfolgt später.','cancel'=>'Abbrechen','send'=>'Anfrage senden','success_ref'=>'Ihre Referenz','saved'=>'Anfrage gespeichert.','gallery'=>'Bildergalerie','close'=>'Schließen','required'=>'Bitte alle Pflichtfelder ausfüllen.','child_age_required'=>'Bitte geben Sie das Alter aller Kinder an.','price_missing_hint'=>'Für diesen Zeitraum fehlt noch ein Saisonpreis. Die Anfrage bleibt möglich, aber wird intern geprüft.','inquiry_privacy_label'=>'Ich habe die Datenschutzhinweise gelesen und stimme der Verarbeitung meiner Angaben zur Bearbeitung dieser Anfrage zu.','inquiry_marketing_label'=>'Ich möchte gelegentlich Informationen zu Angeboten und Neuigkeiten erhalten.','inquiry_contact_preference'=>'Bevorzugter Kontakt','inquiry_contact_any'=>'E-Mail oder Telefon','inquiry_contact_email'=>'E-Mail','inquiry_contact_phone'=>'Telefon','inquiry_arrival_time'=>'Voraussichtliche Anreisezeit','inquiry_location_request'=>'Lagewunsch','inquiry_location_placeholder'=>'z. B. ruhig, obere Etage, Nähe Pool, nicht zur Straße','inquiry_special_occasion'=>'Besonderer Anlass / Hinweis','inquiry_special_placeholder'=>'z. B. Geburtstag, Kinderbett, gesundheitlicher Hinweis','inquiry_form_hint'=>'Ihre Angaben werden ausschließlich zur Bearbeitung dieser unverbindlichen Anfrage verwendet.','inquiry_success_text'=>'Vielen Dank. Ihre unverbindliche Anfrage wurde gespeichert. Wir prüfen Verfügbarkeit und Preis und melden uns bei Ihnen.','type_details'=>'Wohnungstyp ansehen','back_home'=>'Zur Startseite','all_types'=>'Alle Wohnungstypen','menu'=>'Menü','navigation'=>'Navigation','social_media'=>'Soziale Medien','amenities'=>'Ausstattung','not_found'=>'Die gewünschte Seite wurde nicht gefunden oder ist noch nicht veröffentlicht.','type_not_found'=>'Der Wohnungstyp wurde nicht gefunden.',
            ],
            'en' => [
                'language'=>'Language','administration'=>'Administration','arrival'=>'Arrival','departure'=>'Departure','adults'=>'Adults','children'=>'Children','babies'=>'Babies','search'=>'Check availability','choose_dates'=>'Choose your travel dates.','disabled'=>'Public booking requests are currently disabled.','loading'=>'Checking availability and prices …','none'=>'No suitable accommodation type is available for these dates.','nights'=>'nights','bedrooms'=>'bedrooms','beds'=>'beds','sqm'=>'m²','available'=>'available','from_total'=>'Total price','price_request'=>'Price on request','request'=>'Send request','regular'=>'standard occupancy','maximum'=>'maximum','capacity_warning'=>'The standard occupancy is exceeded. A request is possible after explicit confirmation.','capacity_max_warning'=>'The maximum occupancy is exceeded. This exception must be explained and confirmed.','cancellation'=>'Cancellation terms','details'=>'View details','inquiry_title'=>'Non-binding request','summary_type'=>'Accommodation type','summary_period'=>'Period','summary_price'=>'Price','name'=>'Name','email'=>'Email','phone'=>'Phone','country'=>'Country','child_ages'=>'Children’s ages','age_child'=>'Age child','tax_hint'=>'Ages are needed for tourist tax. Tax currently applies from age {age}.','breakfast'=>'Breakfast requested','half_board'=>'Half board requested','wish'=>'Your request / message','wish_placeholder'=>'For example preferred location, quiet unit, lift access or special notes.','capacity_confirm'=>'I have read the occupancy notice and wish to request this number of guests.','capacity_reason'=>'Reason for exceeding occupancy','capacity_reason_placeholder'=>'Please briefly explain why this occupancy is requested.','nonbinding'=>'The request is non-binding. You choose the accommodation type; internal allocation takes place later.','cancel'=>'Cancel','send'=>'Send request','success_ref'=>'Your reference','saved'=>'Request saved.','gallery'=>'Image gallery','close'=>'Close','required'=>'Please complete all required fields.','child_age_required'=>'Please enter the age of every child.','type_details'=>'View accommodation','back_home'=>'Back to home','all_types'=>'All accommodation types','menu'=>'Menu','navigation'=>'Navigation','social_media'=>'Social media','amenities'=>'Facilities','not_found'=>'The requested page was not found or is not yet published.','type_not_found'=>'The accommodation type was not found.',
            ],
            'es' => [
                'language'=>'Idioma','administration'=>'Administración','arrival'=>'Llegada','departure'=>'Salida','adults'=>'Adultos','children'=>'Niños','babies'=>'Bebés','search'=>'Comprobar disponibilidad','choose_dates'=>'Elija el periodo de viaje.','disabled'=>'Las solicitudes públicas están desactivadas.','loading'=>'Comprobando disponibilidad y precios …','none'=>'No hay un tipo de alojamiento adecuado disponible para estas fechas.','nights'=>'noches','bedrooms'=>'dormitorios','beds'=>'camas','sqm'=>'m²','available'=>'disponibles','from_total'=>'Precio total','price_request'=>'Precio bajo petición','request'=>'Solicitar','regular'=>'ocupación habitual','maximum'=>'máximo','capacity_warning'=>'Se supera la ocupación habitual. Es posible solicitarlo tras una confirmación expresa.','capacity_max_warning'=>'Se supera la ocupación máxima. Esta excepción debe justificarse y confirmarse.','cancellation'=>'Condiciones de cancelación','details'=>'Ver detalles','inquiry_title'=>'Solicitud sin compromiso','summary_type'=>'Tipo de alojamiento','summary_period'=>'Periodo','summary_price'=>'Precio','name'=>'Nombre','email'=>'Correo electrónico','phone'=>'Teléfono','country'=>'País','child_ages'=>'Edades de los niños','age_child'=>'Edad niño','tax_hint'=>'La edad es necesaria para la tasa turística. Actualmente se aplica desde los {age} años.','breakfast'=>'Desayuno solicitado','half_board'=>'Media pensión solicitada','wish'=>'Su deseo / mensaje','wish_placeholder'=>'Por ejemplo ubicación preferida, apartamento tranquilo, cerca del ascensor o indicaciones especiales.','capacity_confirm'=>'He leído el aviso de ocupación y deseo solicitar este número de personas.','capacity_reason'=>'Motivo de la ocupación superior','capacity_reason_placeholder'=>'Explique brevemente por qué desea esta ocupación.','nonbinding'=>'La solicitud no es vinculante. Usted elige el tipo de alojamiento; la asignación interna se realiza después.','cancel'=>'Cancelar','send'=>'Enviar solicitud','success_ref'=>'Su referencia','saved'=>'Solicitud guardada.','gallery'=>'Galería de imágenes','close'=>'Cerrar','required'=>'Complete todos los campos obligatorios.','child_age_required'=>'Indique la edad de todos los niños.','type_details'=>'Ver alojamiento','back_home'=>'Volver al inicio','all_types'=>'Todos los alojamientos','menu'=>'Menú','navigation'=>'Navegación','social_media'=>'Redes sociales','amenities'=>'Equipamiento','not_found'=>'La página solicitada no se encontró o todavía no está publicada.','type_not_found'=>'No se encontró el tipo de alojamiento.',
            ],
            'fr' => [
                'language'=>'Langue','administration'=>'Administration','arrival'=>'Arrivée','departure'=>'Départ','adults'=>'Adultes','children'=>'Enfants','babies'=>'Bébés','search'=>'Vérifier la disponibilité','choose_dates'=>'Choisissez votre période de séjour.','disabled'=>'Les demandes publiques sont désactivées.','loading'=>'Vérification des disponibilités et des prix …','none'=>'Aucun type de logement adapté n’est disponible à ces dates.','nights'=>'nuits','bedrooms'=>'chambres','beds'=>'lits','sqm'=>'m²','available'=>'disponibles','from_total'=>'Prix total','price_request'=>'Prix sur demande','request'=>'Faire une demande','regular'=>'occupation standard','maximum'=>'maximum','capacity_warning'=>'L’occupation standard est dépassée. Une demande est possible après confirmation explicite.','capacity_max_warning'=>'L’occupation maximale est dépassée. Cette exception doit être motivée et confirmée.','cancellation'=>'Conditions d’annulation','details'=>'Voir les détails','inquiry_title'=>'Demande sans engagement','summary_type'=>'Type de logement','summary_period'=>'Période','summary_price'=>'Prix','name'=>'Nom','email'=>'E-mail','phone'=>'Téléphone','country'=>'Pays','child_ages'=>'Âge des enfants','age_child'=>'Âge enfant','tax_hint'=>'L’âge est nécessaire pour la taxe de séjour. Elle s’applique actuellement à partir de {age} ans.','breakfast'=>'Petit-déjeuner souhaité','half_board'=>'Demi-pension souhaitée','wish'=>'Votre souhait / message','wish_placeholder'=>'Par exemple emplacement souhaité, logement calme, proximité de l’ascenseur ou remarque particulière.','capacity_confirm'=>'J’ai lu l’avertissement d’occupation et souhaite demander ce nombre de personnes.','capacity_reason'=>'Motif du dépassement','capacity_reason_placeholder'=>'Veuillez expliquer brièvement cette demande d’occupation.','nonbinding'=>'La demande est sans engagement. Vous choisissez le type de logement ; l’attribution interne a lieu ensuite.','cancel'=>'Annuler','send'=>'Envoyer la demande','success_ref'=>'Votre référence','saved'=>'Demande enregistrée.','gallery'=>'Galerie d’images','close'=>'Fermer','required'=>'Veuillez remplir tous les champs obligatoires.','child_age_required'=>'Veuillez indiquer l’âge de chaque enfant.','type_details'=>'Voir le logement','back_home'=>'Retour à l’accueil','all_types'=>'Tous les logements','menu'=>'Menu','navigation'=>'Navigation','social_media'=>'Réseaux sociaux','amenities'=>'Équipements','not_found'=>'La page demandée est introuvable ou n’est pas encore publiée.','type_not_found'=>'Le type de logement est introuvable.',
            ],
            'it' => [
                'language'=>'Lingua','administration'=>'Amministrazione','arrival'=>'Arrivo','departure'=>'Partenza','adults'=>'Adulti','children'=>'Bambini','babies'=>'Neonati','search'=>'Verifica disponibilità','choose_dates'=>'Scegliete il periodo di viaggio.','disabled'=>'Le richieste pubbliche sono disattivate.','loading'=>'Verifica disponibilità e prezzi …','none'=>'Nessun tipo di alloggio adatto è disponibile per queste date.','nights'=>'notti','bedrooms'=>'camere','beds'=>'letti','sqm'=>'m²','available'=>'disponibili','from_total'=>'Prezzo totale','price_request'=>'Prezzo su richiesta','request'=>'Invia richiesta','regular'=>'occupazione standard','maximum'=>'massimo','capacity_warning'=>'L’occupazione standard viene superata. La richiesta è possibile dopo conferma esplicita.','capacity_max_warning'=>'L’occupazione massima viene superata. L’eccezione deve essere motivata e confermata.','cancellation'=>'Condizioni di cancellazione','details'=>'Vedi dettagli','inquiry_title'=>'Richiesta non vincolante','summary_type'=>'Tipo di alloggio','summary_period'=>'Periodo','summary_price'=>'Prezzo','name'=>'Nome','email'=>'E-mail','phone'=>'Telefono','country'=>'Paese','child_ages'=>'Età dei bambini','age_child'=>'Età bambino','tax_hint'=>'L’età è necessaria per la tassa di soggiorno. Attualmente si applica dai {age} anni.','breakfast'=>'Colazione richiesta','half_board'=>'Mezza pensione richiesta','wish'=>'Richiesta / messaggio','wish_placeholder'=>'Ad esempio posizione preferita, appartamento tranquillo, vicino all’ascensore o indicazioni particolari.','capacity_confirm'=>'Ho letto l’avviso sull’occupazione e desidero richiedere questo numero di persone.','capacity_reason'=>'Motivo del superamento','capacity_reason_placeholder'=>'Spiegare brevemente il motivo di questa occupazione.','nonbinding'=>'La richiesta non è vincolante. Scegliete il tipo di alloggio; l’assegnazione interna avviene in seguito.','cancel'=>'Annulla','send'=>'Invia richiesta','success_ref'=>'Riferimento','saved'=>'Richiesta salvata.','gallery'=>'Galleria immagini','close'=>'Chiudi','required'=>'Compilare tutti i campi obbligatori.','child_age_required'=>'Indicare l’età di tutti i bambini.','type_details'=>'Vedi alloggio','back_home'=>'Torna alla home','all_types'=>'Tutti gli alloggi','menu'=>'Menu','navigation'=>'Navigazione','social_media'=>'Social media','amenities'=>'Dotazioni','not_found'=>'La pagina richiesta non è stata trovata o non è ancora pubblicata.','type_not_found'=>'Il tipo di alloggio non è stato trovato.',
            ],
            'pt' => [
                'language'=>'Idioma','administration'=>'Administração','arrival'=>'Chegada','departure'=>'Partida','adults'=>'Adultos','children'=>'Crianças','babies'=>'Bebés','search'=>'Verificar disponibilidade','choose_dates'=>'Escolha o período da viagem.','disabled'=>'Os pedidos públicos estão desativados.','loading'=>'A verificar disponibilidade e preços …','none'=>'Não existe um tipo de alojamento adequado disponível para estas datas.','nights'=>'noites','bedrooms'=>'quartos','beds'=>'camas','sqm'=>'m²','available'=>'disponíveis','from_total'=>'Preço total','price_request'=>'Preço sob consulta','request'=>'Enviar pedido','regular'=>'ocupação habitual','maximum'=>'máximo','capacity_warning'=>'A ocupação habitual é excedida. O pedido é possível após confirmação explícita.','capacity_max_warning'=>'A ocupação máxima é excedida. Esta exceção deve ser justificada e confirmada.','cancellation'=>'Condições de cancelamento','details'=>'Ver detalhes','inquiry_title'=>'Pedido não vinculativo','summary_type'=>'Tipo de alojamento','summary_period'=>'Período','summary_price'=>'Preço','name'=>'Nome','email'=>'E-mail','phone'=>'Telefone','country'=>'País','child_ages'=>'Idades das crianças','age_child'=>'Idade criança','tax_hint'=>'A idade é necessária para a taxa turística. Atualmente aplica-se a partir dos {age} anos.','breakfast'=>'Pequeno-almoço pretendido','half_board'=>'Meia pensão pretendida','wish'=>'Pedido / mensagem','wish_placeholder'=>'Por exemplo localização preferida, apartamento sossegado, perto do elevador ou indicação especial.','capacity_confirm'=>'Li o aviso de ocupação e pretendo pedir este número de pessoas.','capacity_reason'=>'Motivo para exceder a ocupação','capacity_reason_placeholder'=>'Explique brevemente por que pretende esta ocupação.','nonbinding'=>'O pedido não é vinculativo. Escolhe o tipo de alojamento; a atribuição interna é feita mais tarde.','cancel'=>'Cancelar','send'=>'Enviar pedido','success_ref'=>'Referência','saved'=>'Pedido guardado.','gallery'=>'Galeria de imagens','close'=>'Fechar','required'=>'Preencha todos os campos obrigatórios.','child_age_required'=>'Indique a idade de todas as crianças.','type_details'=>'Ver alojamento','back_home'=>'Voltar ao início','all_types'=>'Todos os alojamentos','menu'=>'Menu','navigation'=>'Navegação','social_media'=>'Redes sociais','amenities'=>'Comodidades','not_found'=>'A página solicitada não foi encontrada ou ainda não está publicada.','type_not_found'=>'O tipo de alojamento não foi encontrado.',
            ],
            'ca' => [
                'language'=>'Idioma','administration'=>'Administració','arrival'=>'Arribada','departure'=>'Sortida','adults'=>'Adults','children'=>'Nens','babies'=>'Nadons','search'=>'Comprovar disponibilitat','choose_dates'=>'Trieu el període del viatge.','disabled'=>'Les sol·licituds públiques estan desactivades.','loading'=>'Comprovant disponibilitat i preus …','none'=>'No hi ha cap tipus d’allotjament adequat disponible per a aquestes dates.','nights'=>'nits','bedrooms'=>'dormitoris','beds'=>'llits','sqm'=>'m²','available'=>'disponibles','from_total'=>'Preu total','price_request'=>'Preu a consultar','request'=>'Enviar sol·licitud','regular'=>'ocupació habitual','maximum'=>'màxim','capacity_warning'=>'Se supera l’ocupació habitual. Es pot sol·licitar després d’una confirmació expressa.','capacity_max_warning'=>'Se supera l’ocupació màxima. Aquesta excepció s’ha de justificar i confirmar.','cancellation'=>'Condicions de cancel·lació','details'=>'Veure detalls','inquiry_title'=>'Sol·licitud no vinculant','summary_type'=>'Tipus d’allotjament','summary_period'=>'Període','summary_price'=>'Preu','name'=>'Nom','email'=>'Correu electrònic','phone'=>'Telèfon','country'=>'País','child_ages'=>'Edats dels nens','age_child'=>'Edat nen','tax_hint'=>'L’edat és necessària per a la taxa turística. Actualment s’aplica a partir dels {age} anys.','breakfast'=>'Esmorzar sol·licitat','half_board'=>'Mitja pensió sol·licitada','wish'=>'Desig / missatge','wish_placeholder'=>'Per exemple ubicació preferida, apartament tranquil, a prop de l’ascensor o indicació especial.','capacity_confirm'=>'He llegit l’avís d’ocupació i vull sol·licitar aquest nombre de persones.','capacity_reason'=>'Motiu de l’excés d’ocupació','capacity_reason_placeholder'=>'Expliqueu breument el motiu d’aquesta ocupació.','nonbinding'=>'La sol·licitud no és vinculant. Trieu el tipus d’allotjament; l’assignació interna es fa més endavant.','cancel'=>'Cancel·lar','send'=>'Enviar sol·licitud','success_ref'=>'Referència','saved'=>'Sol·licitud desada.','gallery'=>'Galeria d’imatges','close'=>'Tancar','required'=>'Empleneu tots els camps obligatoris.','child_age_required'=>'Indiqueu l’edat de tots els infants.','type_details'=>'Veure allotjament','back_home'=>'Tornar a l’inici','all_types'=>'Tots els allotjaments','menu'=>'Menú','navigation'=>'Navegació','social_media'=>'Xarxes socials','amenities'=>'Equipament','not_found'=>'La pàgina sol·licitada no s’ha trobat o encara no està publicada.','type_not_found'=>'No s’ha trobat el tipus d’allotjament.',
            ],
        ];
    }

    public static function design(): array
    {
        $saved = setting('site_design_json', []);
        if (!is_array($saved)) $saved = [];
        return array_replace(self::defaultDesign(), $saved);
    }

    public static function labels(string $language): array
    {
        $language = self::language($language);
        $defaults = self::defaultLabels();
        $saved = setting('site_labels_json', []);
        if (!is_array($saved)) $saved = [];
        return array_replace($defaults['de'], $defaults[$language] ?? [], is_array($saved[$language] ?? null) ? $saved[$language] : []);
    }

    public static function pageBySlug(string $slug, string $language, bool $includeDraft = false): ?array
    {
        $slug = trim(strtolower($slug));
        $sql = 'SELECT * FROM site_pages WHERE slug=?';
        if (!$includeDraft) $sql .= " AND status='published'";
        $sql .= ' LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute([$slug]);
        $page = $stmt->fetch();
        return $page ? self::hydratePage($page, $language) : null;
    }

    public static function pageById(int $id, string $language, bool $includeDraft = false): ?array
    {
        $sql = 'SELECT * FROM site_pages WHERE id=?';
        if (!$includeDraft) $sql .= " AND status='published'";
        $sql .= ' LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute([$id]);
        $page = $stmt->fetch();
        return $page ? self::hydratePage($page, $language) : null;
    }

    public static function home(string $language, bool $includeDraft = false): ?array
    {
        $sql = "SELECT * FROM site_pages WHERE system_key='home'";
        if (!$includeDraft) $sql .= " AND status='published'";
        $sql .= ' LIMIT 1';
        $page = db()->query($sql)->fetch();
        return $page ? self::hydratePage($page, $language) : null;
    }

    public static function allPages(string $language = 'de', bool $includeDraft = true): array
    {
        $sql = 'SELECT * FROM site_pages';
        if (!$includeDraft) $sql .= " WHERE status='published'";
        $sql .= ' ORDER BY sort_order,title_fallback,slug';
        $rows = db()->query($sql)->fetchAll();
        return array_map(static fn(array $row): array => self::hydratePage($row, $language), $rows);
    }

    private static function hydratePage(array $page, string $language): array
    {
        $language = self::language($language);
        $stmt = db()->prepare("SELECT * FROM site_page_translations WHERE page_id=? AND language IN (?, 'de')");
        $stmt->execute([(int)$page['id'], $language]);
        $translations = [];
        foreach ($stmt->fetchAll() as $row) $translations[(string)$row['language']] = $row;
        $translation = self::mergeTranslationRow($translations['de'] ?? [], $translations[$language] ?? []);
        $page['translation'] = $translation;
        $page['title'] = trim((string)($translation['title'] ?? '')) ?: (string)$page['title_fallback'];
        $page['navigation_label'] = trim((string)($translation['navigation_label'] ?? '')) ?: $page['title'];
        $page['blocks'] = self::pageBlocks((int)$page['id'], $language);
        return $page;
    }

    public static function pageBlocks(int $pageId, string $language): array
    {
        $language = self::language($language);
        $stmt = db()->prepare('SELECT * FROM site_page_blocks WHERE page_id=? AND active=1 ORDER BY sort_order,id');
        $stmt->execute([$pageId]);
        $rows = $stmt->fetchAll();
        $tr = db()->prepare("SELECT * FROM site_page_block_translations WHERE block_id=? AND language IN (?, 'de')");
        foreach ($rows as &$row) {
            $tr->execute([(int)$row['id'], $language]);
            $translations = [];
            foreach ($tr->fetchAll() as $translationRow) $translations[(string)$translationRow['language']] = $translationRow;
            $translation = self::mergeTranslationRow($translations['de'] ?? [], $translations[$language] ?? []);
            $fallbackContent = json_decode((string)($translations['de']['content_json'] ?? ''), true) ?: [];
            $localizedContent = json_decode((string)($translations[$language]['content_json'] ?? ''), true) ?: [];
            $row['settings'] = json_decode((string)($row['settings_json'] ?? ''), true) ?: [];
            $row['content'] = self::mergeTranslationContent($fallbackContent, $localizedContent);
            $row['translation'] = $translation;
        }
        unset($row);
        return $rows;
    }

    private static function mergeTranslationRow(array $fallback, array $localized): array
    {
        $merged = $fallback;
        foreach ($localized as $key => $value) {
            if (is_string($value) && trim($value) === '' && array_key_exists($key, $fallback)) continue;
            if ($value === null && array_key_exists($key, $fallback)) continue;
            $merged[$key] = $value;
        }
        return $merged;
    }

    private static function mergeTranslationContent(array $fallback, array $localized): array
    {
        $merged = $fallback;
        foreach ($localized as $key => $value) {
            if (is_string($value) && trim($value) === '' && array_key_exists($key, $fallback)) continue;
            if (is_array($value) && $value === [] && array_key_exists($key, $fallback)) continue;
            if ($value === null && array_key_exists($key, $fallback)) continue;
            $merged[$key] = $value;
        }
        return $merged;
    }

    public static function navigation(string $language, string $position = 'header'): array
    {
        $column = $position === 'footer' ? 'show_footer' : 'show_header';
        $stmt = db()->query("SELECT * FROM site_pages WHERE status='published' AND {$column}=1 ORDER BY sort_order,title_fallback");
        $rows = [];
        foreach ($stmt->fetchAll() as $page) {
            $hydrated = self::hydratePage($page, $language);
            $rows[] = [
                'id'=>(int)$page['id'],
                'slug'=>(string)$page['slug'],
                'system_key'=>(string)($page['system_key'] ?? ''),
                'label'=>(string)$hydrated['navigation_label'],
                'url'=>self::pageUrl($page, $language),
            ];
        }
        return $rows;
    }

    public static function pageUrl(array $page, string $language): string
    {
        $language = self::language($language);
        if (($page['system_key'] ?? '') === 'home') return 'index.php?lang=' . rawurlencode($language);
        return 'seite.php?slug=' . rawurlencode((string)$page['slug']) . '&lang=' . rawurlencode($language);
    }

    public static function typeCards(string $language, int $limit = 0): array
    {
        $sql = "SELECT t.* FROM apartment_types t WHERE t.active=1 AND t.public_active=1 ORDER BY t.sort_order,t.name";
        if ($limit > 0) $sql .= ' LIMIT ' . min(50, max(1, $limit));
        $types = db()->query($sql)->fetchAll();
        $translationStmt = db()->prepare("SELECT * FROM offer_apartment_type_translations WHERE apartment_type_id=? AND language IN (?, 'de')");
        $imageStmt = db()->prepare('SELECT file_path,thumb_path,alt_text_json,title_text_json,caption_text_json,is_cover,sort_order FROM apartment_type_images WHERE apartment_type_id=? ORDER BY is_cover DESC,sort_order,id LIMIT 8');
        $amenityStmt = db()->prepare("SELECT c.icon,COALESCE(tr.label,de.label,c.code) label FROM apartment_type_amenities ta JOIN amenity_catalog c ON c.id=ta.amenity_id AND c.active=1 LEFT JOIN amenity_translations tr ON tr.amenity_id=c.id AND tr.language=? LEFT JOIN amenity_translations de ON de.amenity_id=c.id AND de.language='de' WHERE ta.apartment_type_id=? ORDER BY ta.sort_order,c.sort_order LIMIT 6");
        foreach ($types as &$type) {
            $translationStmt->execute([(int)$type['id'], $language]);
            $typeTranslations=[];foreach($translationStmt->fetchAll() as $translationRow)$typeTranslations[(string)$translationRow['language']]=$translationRow;
            $tr = self::mergeTranslationRow($typeTranslations['de']??[], $typeTranslations[$language]??[]);
            $imageStmt->execute([(int)$type['id']]);
            $images = $imageStmt->fetchAll();
            foreach ($images as &$image) {
                $alts = json_decode((string)($image['alt_text_json'] ?? ''), true) ?: [];
                $titles = json_decode((string)($image['title_text_json'] ?? ''), true) ?: [];
                $captions = json_decode((string)($image['caption_text_json'] ?? ''), true) ?: [];
                $image['alt_text'] = (string)($alts[$language] ?? $alts['de'] ?? $tr['image_alt'] ?? '');
                $image['title'] = (string)($titles[$language] ?? $titles['de'] ?? '');
                $image['caption'] = (string)($captions[$language] ?? $captions['de'] ?? '');
                unset($image['alt_text_json'], $image['title_text_json'], $image['caption_text_json']);
            }
            unset($image);
            $cover = $images[0] ?? null;
            $amenityStmt->execute([$language, (int)$type['id']]);
            $type['public_name'] = trim((string)($tr['name'] ?? '')) ?: (string)$type['name'];
            $type['public_description_html'] = (string)($tr['public_description_html'] ?? '');
            $type['seo_title'] = (string)($tr['seo_title'] ?? '');
            $type['seo_description'] = (string)($tr['seo_description'] ?? '');
            $type['cover'] = $cover;
            $type['images'] = $images;
            $type['amenities'] = $amenityStmt->fetchAll();
        }
        unset($type);
        return $types;
    }

    public static function typeDetail(int $typeId, string $language): ?array
    {
        $stmt = db()->prepare('SELECT * FROM apartment_types WHERE id=? AND active=1 AND public_active=1 LIMIT 1');
        $stmt->execute([$typeId]);
        $type = $stmt->fetch();
        if (!$type) return null;
        $trStmt = db()->prepare("SELECT * FROM offer_apartment_type_translations WHERE apartment_type_id=? AND language IN (?, 'de')");
        $trStmt->execute([$typeId, $language]);
        $typeTranslations=[];foreach($trStmt->fetchAll() as $translationRow)$typeTranslations[(string)$translationRow['language']]=$translationRow;
        $tr = self::mergeTranslationRow($typeTranslations['de']??[], $typeTranslations[$language]??[]);
        $type['public_name'] = trim((string)($tr['name'] ?? '')) ?: (string)$type['name'];
        $type['public_description_html'] = (string)($tr['public_description_html'] ?? '');
        $type['seo_title'] = (string)($tr['seo_title'] ?? '');
        $type['seo_description'] = (string)($tr['seo_description'] ?? '');
        $type['request_hint'] = (string)($tr['request_hint'] ?? '');
        $type['image_alt'] = (string)($tr['image_alt'] ?? '');
        $amenityStmt = db()->prepare("SELECT c.icon,COALESCE(tr.label,de.label,c.code) label FROM apartment_type_amenities ta JOIN amenity_catalog c ON c.id=ta.amenity_id AND c.active=1 LEFT JOIN amenity_translations tr ON tr.amenity_id=c.id AND tr.language=? LEFT JOIN amenity_translations de ON de.amenity_id=c.id AND de.language='de' WHERE ta.apartment_type_id=? ORDER BY ta.sort_order,c.sort_order");
        $amenityStmt->execute([$language, $typeId]);
        $type['amenities'] = $amenityStmt->fetchAll();
        $imageStmt = db()->prepare('SELECT file_path,thumb_path,alt_text_json,title_text_json,caption_text_json,is_cover,sort_order FROM apartment_type_images WHERE apartment_type_id=? ORDER BY is_cover DESC,sort_order,id');
        $imageStmt->execute([$typeId]);
        $images = $imageStmt->fetchAll();
        foreach ($images as &$image) {
            $alts = json_decode((string)($image['alt_text_json'] ?? ''), true) ?: [];
            $titles = json_decode((string)($image['title_text_json'] ?? ''), true) ?: [];
            $captions = json_decode((string)($image['caption_text_json'] ?? ''), true) ?: [];
            $image['alt_text'] = (string)($alts[$language] ?? $alts['de'] ?? $type['image_alt']);
            $image['title'] = (string)($titles[$language] ?? $titles['de'] ?? '');
            $image['caption'] = (string)($captions[$language] ?? $captions['de'] ?? '');
            unset($image['alt_text_json'], $image['title_text_json'], $image['caption_text_json']);
        }
        unset($image);
        $type['images'] = $images;
        $type['cancellation_text'] = BookingPolicyService::cancellationText(BookingPolicyService::cancellationSnapshot($typeId), $language);
        return $type;
    }

    public static function sanitizeRich(string $html): string
    {
        $html = mb_substr(trim($html), 0, 100000);
        if ($html === '') return '';
        if (!class_exists('DOMDocument')) {
            // Ohne DOM-Erweiterung bleiben nur erlaubte Strukturtags erhalten.
            // Sämtliche Attribute werden entfernt, damit auch in dieser
            // Notfallvariante keine event-Handler oder javascript:-Links
            // eingeschleust werden können.
            $clean = strip_tags($html, '<div><p><br><h1><h2><h3><h4><blockquote><hr><ul><ol><li><strong><b><em><i><u><a><span><table><thead><tbody><tr><th><td><figure><figcaption><img>');
            $clean = preg_replace('/<(\/?)(div|p|br|h1|h2|h3|h4|blockquote|hr|ul|ol|li|strong|b|em|i|u|a|span|table|thead|tbody|tr|th|td|figure|figcaption)\b[^>]*>/i', '<$1$2>', $clean) ?? '';
            $clean = preg_replace('/<img\b[^>]*>/i', '', $clean) ?? '';
            return trim($clean);
        }
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="sp-site-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
        $allowed = ['div','p','br','h1','h2','h3','h4','blockquote','hr','ul','ol','li','strong','b','em','i','u','a','span','table','thead','tbody','tr','th','td','figure','figcaption','img'];
        $nodes = $dom->getElementsByTagName('*');
        for ($i=$nodes->length-1; $i>=0; $i--) {
            $node = $nodes->item($i);
            if (!$node || $node->getAttribute('id') === 'sp-site-root') continue;
            if (!in_array(strtolower($node->nodeName), $allowed, true)) {
                $parent = $node->parentNode;
                if ($parent) {
                    while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                    $parent->removeChild($node);
                }
                continue;
            }
            if ($node->hasAttributes()) {
                for ($a=$node->attributes->length-1; $a>=0; $a--) {
                    $attr = $node->attributes->item($a);
                    if (!$attr) continue;
                    $name = strtolower($attr->name);
                    if ($node->nodeName === 'a' && in_array($name, ['href','target','rel'], true)) continue;
                    if ($node->nodeName === 'img' && in_array($name, ['src','alt','loading','width','height'], true)) continue;
                    if ($name === 'class') {
                        $safeClasses = [];
                        foreach (preg_split('/\s+/', trim((string)$attr->value)) ?: [] as $className) {
                            if (preg_match('/^(sp-|site-)[a-zA-Z0-9_-]{1,60}$/', $className)) $safeClasses[] = $className;
                        }
                        if ($safeClasses) { $node->setAttribute('class', implode(' ', array_unique($safeClasses))); continue; }
                    }
                    $node->removeAttribute($attr->name);
                }
            }
            if ($node->nodeName === 'a') {
                $href = trim($node->getAttribute('href'));
                if ($href !== '' && !preg_match('#^(https?://|mailto:|tel:|/|\?|#|[a-zA-Z0-9._/-]+(?:\?[^<>\"\']*)?(?:#[^<>\"\']*)?)$#i', $href)) $node->removeAttribute('href');
                if ($node->getAttribute('target') === '_blank') $node->setAttribute('rel', 'noopener noreferrer');
            }
            if ($node->nodeName === 'img') {
                $src = trim($node->getAttribute('src'));
                if ($src === '' || !preg_match('#^(https?://|/|[a-zA-Z0-9._/-]+(?:\?[^<>\"\']*)?(?:#[^<>\"\']*)?)$#i', $src)) {
                    $node->parentNode?->removeChild($node);
                    continue;
                }
                $node->setAttribute('loading', $node->getAttribute('loading') === 'eager' ? 'eager' : 'lazy');
                foreach (['width','height'] as $dimension) {
                    $value = $node->getAttribute($dimension);
                    if ($value !== '' && !preg_match('/^[0-9]{1,4}$/', $value)) $node->removeAttribute($dimension);
                }
            }
        }
        $root = $dom->getElementById('sp-site-root');
        $out = '';
        if ($root) foreach ($root->childNodes as $child) $out .= $dom->saveHTML($child);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return trim($out);
    }

    public static function cssVariables(array $design): string
    {
        $font = match ((string)($design['font'] ?? 'system')) {
            'serif' => 'Georgia, Cambria, "Times New Roman", serif',
            'modern' => 'Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
            'rounded' => '"Trebuchet MS", "Segoe UI", ui-sans-serif, system-ui, sans-serif',
            'editorial' => 'Cambria, Georgia, "Times New Roman", serif',
            'mono' => '"Segoe UI Mono", "Roboto Mono", Consolas, monospace',
            default => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
        };
        return '--site-primary:'.self::safeColor($design['primary'] ?? '#2563eb').';'
            .'--site-secondary:'.self::safeColor($design['secondary'] ?? '#0f766e').';'
            .'--site-bg:'.self::safeColor($design['background'] ?? '#f4f7fb').';'
            .'--site-surface:'.self::safeColor($design['surface'] ?? '#ffffff').';'
            .'--site-text:'.self::safeColor($design['text'] ?? '#172033').';'
            .'--site-muted:'.self::safeColor($design['muted'] ?? '#62708a').';'
            .'--site-header-bg:'.self::safeColor($design['header_background'] ?? '#101827').';'
            .'--site-header-text:'.self::safeColor($design['header_text'] ?? '#ffffff').';'
            .'--site-footer-bg:'.self::safeColor($design['footer_background'] ?? '#101827').';'
            .'--site-footer-text:'.self::safeColor($design['footer_text'] ?? '#dbe5f3').';'
            .'--site-footer-heading:'.self::safeColor($design['footer_heading'] ?? '#ffffff').';'
            .'--site-footer-link:'.self::safeColor($design['footer_link'] ?? '#dbe5f3').';'
            .'--site-contact-bg:'.self::safeColor($design['contact_background'] ?? '#ffffff').';'
            .'--site-contact-text:'.self::safeColor($design['contact_text'] ?? '#172033').';'
            .'--site-contact-accent:'.self::safeColor($design['contact_accent'] ?? '#2563eb').';'
            .'--site-search-bg:'.self::safeColor($design['booking_search_background'] ?? '#ffffff').';'
            .'--site-search-surface:'.self::safeColor($design['booking_search_surface'] ?? '#ffffff').';'
            .'--site-search-text:'.self::safeColor($design['booking_search_text'] ?? '#172033').';'
            .'--site-search-heading:'.self::safeColor($design['booking_search_heading'] ?? '#172033').';'
            .'--site-search-button-bg:'.self::safeColor($design['booking_search_button_bg'] ?? '#2563eb').';'
            .'--site-search-button-text:'.self::safeColor($design['booking_search_button_text'] ?? '#ffffff').';'
            .'--site-search-radius:'.max(0,min(40,(int)($design['booking_search_radius'] ?? 18))).'px;'
            .'--site-width:'.max(760,min(1600,(int)($design['content_width'] ?? 1180))).'px;'
            .'--site-radius:'.max(0,min(40,(int)($design['radius'] ?? 18))).'px;'
            .'--site-font:'.$font.';';
    }

    private static function safeColor(mixed $value): string
    {
        $value = trim((string)$value);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#2563eb';
    }
}
