<?php
declare(strict_types=1);

final class PublicSiteRenderer
{
    public static function head(string $language, string $title, string $description = ''): string
    {
        $design = PublicSiteService::design();
        $property = (string)setting('property_name', 'Meine Ferienwohnungen');
        $version = (string)(config()['app_version'] ?? '2.2.0');
        $favicon = trim((string)($design['favicon_url'] ?? ''));
        $layout = in_array((string)($design['type_card_layout'] ?? 'portrait'), ['portrait','wide','compact'], true) ? (string)$design['type_card_layout'] : 'portrait';
        $metaTitle = $title ?: $property;
        $metaDescription = $description ?: $property;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $canonical = $host !== '' ? $scheme.'://'.$host.$uri : '';
        return '<!doctype html><html lang="'.e($language).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<meta name="theme-color" content="'.e((string)$design['primary']).'">'
            .'<meta name="description" content="'.e($metaDescription).'">'
            .'<meta property="og:type" content="website">'
            .'<meta property="og:title" content="'.e($metaTitle).'">'
            .'<meta property="og:description" content="'.e($metaDescription).'">'
            .'<meta name="twitter:card" content="summary_large_image">'
            .'<meta name="twitter:title" content="'.e($metaTitle).'">'
            .'<meta name="twitter:description" content="'.e($metaDescription).'">'
            .($canonical !== '' ? '<link rel="canonical" href="'.e($canonical).'"><meta property="og:url" content="'.e($canonical).'">' : '')
            .'<title>'.e($metaTitle).'</title>'.($favicon !== '' ? '<link rel="icon" href="'.e($favicon).'">' : '')
            .'<link rel="stylesheet" href="assets/app.css?v='.e($version).'"><link rel="stylesheet" href="assets/site.css?v='.e($version).'">'
            .'</head><body class="site-body site-buttons-'.e((string)($design['button_style'] ?? 'rounded')).(normalize_bool($design['card_shadow'] ?? 1) ? '' : ' site-no-card-shadow').' site-type-layout-'.e($layout).'" style="'.e(PublicSiteService::cssVariables($design)).'">';
    }

    public static function header(string $language, string $activeSlug = ''): string
    {
        $design = PublicSiteService::design();
        $property = (string)setting('property_name', 'Meine Ferienwohnungen');
        $nav = PublicSiteService::navigation($language, 'header');
        $languages = (array)setting('public_booking_languages', array_keys(PublicSiteService::languages()));
        $languages = array_values(array_intersect(array_keys(PublicSiteService::languages()), $languages));
        if (!$languages) $languages = array_keys(PublicSiteService::languages());
        $logo = trim((string)($design['logo_url'] ?? ''));
        $sticky = normalize_bool($design['sticky_header'] ?? 1) ? ' sticky' : '';
        $html = '<header class="site-header'.$sticky.'"><div class="site-header-inner"><a class="site-brand" href="index.php?lang='.e($language).'">';
        $html .= $logo !== '' ? '<img src="'.e($logo).'" alt="'.e($property).'">' : '<span class="site-brand-icon">🏡</span>';
        $html .= '<span>'.e($property).'</span></a><button class="site-menu-toggle" type="button" aria-expanded="false" aria-controls="siteNav">☰</button><nav class="site-nav" id="siteNav">';
        $hasBookingLink = false;
        $hasTypeLink = false;
        $hasTypeCalendarLink = false;
        foreach ($nav as $item) {
            $active = $activeSlug !== '' && $activeSlug === $item['slug'] ? ' active' : '';
            $html .= '<a class="'.$active.'" href="'.e($item['url']).'">'.e($item['label']).'</a>';
            $hasBookingLink = $hasBookingLink || in_array((string)$item['slug'], ['buchung','buchen','anfrage'], true);
            $hasTypeLink = $hasTypeLink || in_array((string)$item['slug'], ['wohnungstypen','apartments','unterkuenfte'], true);
            $hasTypeCalendarLink = $hasTypeCalendarLink || in_array((string)$item['slug'], ['typen-belegung','belegungsplan'], true);
        }
        $labels = PublicSiteService::labels($language);
        if (!$hasTypeLink) {
            $html .= '<a class="'.($activeSlug === 'wohnungstypen' ? ' active' : '').'" href="wohnungstypen.php?lang='.e($language).'">'.e((string)($labels['all_types'] ?? 'Wohnungstypen')).'</a>';
        }
        if (!$hasTypeCalendarLink) {
            $html .= '<a class="'.($activeSlug === 'typen-belegung' ? ' active' : '').'" href="typen-belegung.php?lang='.e($language).'">Belegungsplan</a>';
        }
        if (!$hasBookingLink && normalize_bool(setting('public_booking_enabled', true))) {
            $html .= '<a class="site-nav-booking'.($activeSlug === 'buchung' ? ' active' : '').'" href="buchung.php?lang='.e($language).'">'.e((string)($labels['public_booking_nav'] ?? $labels['request'] ?? 'Buchen')).'</a>';
        }
        $html .= '</nav><div class="site-header-tools"><select class="site-language" data-site-language aria-label="Sprache">';
        foreach ($languages as $code) $html .= '<option value="'.e($code).'" '.($code === $language ? 'selected' : '').'>'.e(PublicSiteService::languages()[$code]).'</option>';
        $html .= '</select>';
        if (normalize_bool($design['show_admin_link'] ?? 0)) $html .= '<a class="site-admin-link" href="verwaltung-login.php">⚙️</a>';
        return $html.'</div></div></header>';
    }

    public static function footer(string $language): string
    {
        $design = PublicSiteService::design();
        $property = (string)setting('property_name', 'Meine Ferienwohnungen');
        $nav = PublicSiteService::navigation($language, 'footer');
        $labels = PublicSiteService::labels($language);
        $contact = [];
        if (trim((string)($design['address'] ?? '')) !== '') $contact[] = '<span>📍 '.nl2br(e((string)$design['address'])).'</span>';
        if (trim((string)($design['phone'] ?? '')) !== '') $contact[] = '<a href="tel:'.e((string)$design['phone']).'">☎ '.e((string)$design['phone']).'</a>';
        if (trim((string)($design['email'] ?? '')) !== '') $contact[] = '<a href="mailto:'.e((string)$design['email']).'">✉ '.e((string)$design['email']).'</a>';
        $social = [];
        if (trim((string)($design['facebook_url'] ?? '')) !== '') $social[] = '<a href="'.e((string)$design['facebook_url']).'" target="_blank" rel="noopener noreferrer">Facebook</a>';
        if (trim((string)($design['instagram_url'] ?? '')) !== '') $social[] = '<a href="'.e((string)$design['instagram_url']).'" target="_blank" rel="noopener noreferrer">Instagram</a>';
        $copyright = trim((string)($design['copyright'] ?? '')) ?: ('© '.date('Y').' '.$property);
        $columns = max(1, min(3, (int)($design['footer_columns'] ?? 3)));
        $html = '<footer class="site-footer footer-columns-'.$columns.'"><div class="site-footer-grid"><div><h3>'.e($property).'</h3><div class="site-footer-contact">'.implode('', $contact).'</div></div><div><h3>'.e($labels['navigation']).'</h3><div class="site-footer-links">';
        foreach ($nav as $item) $html .= '<a href="'.e($item['url']).'">'.e($item['label']).'</a>';
        $legalFallback = '';
        foreach (['impressum'=>'Impressum','datenschutz'=>'Datenschutz','bedingungen'=>'Bedingungen'] as $slug => $label) {
            $found = false;
            foreach ($nav as $item) { if (($item['slug'] ?? '') === $slug) { $found = true; break; } }
            if (!$found) $legalFallback .= '<a href="frontend-hinweise.php?slug='.e($slug).'&amp;lang='.e($language).'">'.e($label).'</a>';
        }
        $cookieLink = '<a href="cookie-center.php?lang='.e($language).'">Cookie-Center</a>';
        $html .= $legalFallback.$cookieLink.'</div></div>'.($social ? '<div><h3>'.e($labels['social_media']).'</h3><div class="site-footer-links">'.implode('', $social).'</div></div>' : '').'</div><div class="site-footer-bottom"><span>'.e($copyright).'</span><button class="site-footer-cookie" type="button" data-cookie-open>Cookie-Einstellungen</button></div></footer>';
        $version = e((string)(config()['app_version'] ?? '2.2.0'));
        $cookieConfig = self::cookieConfig($design, $language);
        $cookieAssets = normalize_bool($design['cookie_enabled'] ?? 1)
            ? '<button class="cookie-float" type="button" data-cookie-open aria-label="Cookie-Einstellungen">Cookie</button><script>window.stayPilotCookieConfig='.json_encode($cookieConfig, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';</script><script charset="utf-8" src="assets/cookie-center.js?v='.$version.'"></script>'
            : '';
        return $html.$cookieAssets.'<script charset="utf-8" src="assets/site.js?v='.$version.'"></script>';
    }

    public static function renderBlocks(array $page, string $language): string
    {
        $html = '';
        $bookingIncluded = false;
        foreach ($page['blocks'] ?? [] as $block) {
            $type = (string)$block['block_type'];
            $content = is_array($block['content'] ?? null) ? $block['content'] : [];
            $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
            if ($type === 'booking_search') {
                $bookingIncluded = true;
                $html .= self::bookingSearch($content, $language);
                continue;
            }
            $html .= match ($type) {
                'hero' => self::hero($content, $settings, $language),
                'type_grid' => self::typeGrid($content, $settings, $language),
                'rich_text' => self::richText($content, $settings, $language),
                'image_text' => self::imageText($content, $settings, $language),
                'features' => self::features($content, $settings, $language),
                'icon_cards' => self::cardItems($content, $settings, $language, 'site-icon-card-grid', 'icon'),
                'split_cards' => self::cardItems($content, $settings, $language, 'site-split-card-grid', 'split'),
                'cta_banner' => self::ctaBanner($content, $settings, $language),
                'price_notice' => self::noticeBlock($content, $settings, $language, 'price'),
                'seasonal_notice' => self::noticeBlock($content, $settings, $language, 'season'),
                'availability_teaser' => self::noticeBlock($content, $settings, $language, 'availability'),
                'trust_badges' => self::trustBadges($content, $settings, $language),
                'stats' => self::stats($content, $settings, $language),
                'timeline' => self::timeline($content, $settings, $language),
                'tabs' => self::tabs($content, $settings, $language),
                'faq' => self::faq($content, $settings, $language),
                'accordion' => self::faq($content, $settings, $language),
                'contact' => self::contact($content, $settings, $language),
                'map' => self::contact($content, $settings, $language),
                'team' => self::team($content, $settings, $language),
                'gallery' => self::gallery($content, $settings, $language),
                'reviews' => self::reviews($content, $settings, $language),
                'download_links' => self::downloadLinks($content, $settings, $language),
                'video_embed' => self::videoEmbed($content, $settings, $language),
                'custom_html' => self::customHtml($content, $settings, $language),
                'custom_form' => self::customForm($content, $settings, $language),
                'spacer' => self::spacer($settings),
                default => '',
            };
        }
        return $html.($bookingIncluded ? '<!--staypilot-booking-search-->' : '');
    }

    public static function hasBookingSearch(array $page): bool
    {
        foreach ($page['blocks'] ?? [] as $block) if (($block['block_type'] ?? '') === 'booking_search' && (int)($block['active'] ?? 1) === 1) return true;
        return false;
    }

    private static function sectionIntro(array $content): string
    {
        $title = trim((string)($content['title'] ?? ''));
        $subtitle = trim((string)($content['subtitle'] ?? ''));
        if ($title === '' && $subtitle === '') return '';
        return '<div class="site-section-head">'.($title !== '' ? '<h2>'.e($title).'</h2>' : '').($subtitle !== '' ? '<p>'.e($subtitle).'</p>' : '').'</div>';
    }

    private static function hero(array $content, array $settings, string $language): string
    {
        $image = trim((string)($settings['background_image'] ?? ''));
        $align = in_array($settings['alignment'] ?? '', ['left','center','right'], true) ? $settings['alignment'] : 'left';
        $height = in_array($settings['height'] ?? '', ['small','medium','large'], true) ? $settings['height'] : 'medium';
        $style = $image !== '' ? ' style="--hero-image:url(\''.e($image).'\')"' : '';
        $buttonUrl = trim((string)($content['button_url'] ?? ''));
        $rich = trim((string)($content['content_html'] ?? '')) !== '' ? '<div class="site-hero-rich site-prose">'.PublicSiteService::sanitizeRich((string)$content['content_html']).'</div>' : '';
        return '<section class="site-hero '.$height.' align-'.$align.'"'.$style.'><div class="site-hero-overlay"></div><div class="site-container site-hero-content">'.(trim((string)($content['eyebrow'] ?? '')) !== '' ? '<span class="site-eyebrow">'.e($content['eyebrow']).'</span>' : '').'<h1>'.e((string)($content['title'] ?? '')).'</h1><p>'.e((string)($content['subtitle'] ?? '')).'</p>'.$rich.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function bookingSearch(array $content, string $language): string
    {
        $l = PublicSiteService::labels($language);
        $enabled = (bool)setting('public_booking_enabled', true);
        $design = PublicSiteService::design();
        $shadowClass = normalize_bool($design['booking_search_shadow'] ?? 1) ? '' : ' no-shadow';
        return '<section class="site-section site-booking-section" id="booking-search"><div class="site-container">'.self::sectionIntro($content).'<div class="search-panel public-search-v216 site-search-panel'.$shadowClass.'"><div class="field"><label data-i18n="arrival">'.e($l['arrival']).'</label><input type="date" id="publicArrival" min="'.date('Y-m-d').'" value="'.date('Y-m-d', strtotime('+1 day')).'"><span class="date-confirm-hint muted small" id="publicArrivalHint"></span></div><div class="field"><label data-i18n="departure">'.e($l['departure']).'</label><input type="date" id="publicDeparture" min="'.date('Y-m-d', strtotime('+2 days')).'" value="'.date('Y-m-d', strtotime('+5 days')).'"><span class="date-confirm-hint muted small" id="publicDepartureHint"></span></div><div class="field"><label data-i18n="adults">'.e($l['adults']).'</label><select id="publicAdults">'.self::options(1, 12, 2).'</select></div><div class="field"><label data-i18n="children">'.e($l['children']).'</label><select id="publicChildren">'.self::options(0, 8, 0).'</select></div><div class="field"><label data-i18n="babies">'.e($l['babies']).'</label><select id="publicBabies">'.self::options(0, 4, 0).'</select></div><button class="btn primary" id="publicSearch" data-i18n="search">'.e($l['search']).'</button><div id="publicChildAges" class="public-child-age-search span-all" hidden></div></div><div id="publicMessage"></div><div class="public-grid site-public-results" id="publicResults"><div class="empty" data-i18n="choose_dates">'.e($l['choose_dates']).'</div></div>'.(!$enabled ? '<div class="alert warning" data-i18n="disabled">'.e($l['disabled']).'</div>' : '').'</div></section>';
    }

    private static function options(int $from, int $to, int $selected): string
    {
        $html = '';
        for ($i = $from; $i <= $to; $i++) $html .= '<option value="'.$i.'" '.($i === $selected ? 'selected' : '').'>'.$i.'</option>';
        return $html;
    }

    private static function typeGrid(array $content, array $settings, string $language): string
    {
        $types = PublicSiteService::typeCards($language, (int)($settings['limit'] ?? 0));
        $labels = PublicSiteService::labels($language);
        $design = PublicSiteService::design();
        $layout = in_array((string)($design['type_card_layout'] ?? 'portrait'), ['portrait','wide','compact'], true) ? (string)$design['type_card_layout'] : 'portrait';
        $interval = max(0, min(12000, (int)($design['type_slider_interval'] ?? 4200)));
        $currency = (string)setting('offer_currency', setting('currency', 'EUR'));
        $html = '<section class="site-section site-type-section"><div class="site-container">'.self::sectionIntro($content).'<div class="site-type-grid layout-'.$layout.'">';
        foreach ($types as $type) {
            $images = $type['images'] ?? [];
            $plainDescription = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($type['public_description_html'] ?? ''))) ?? '');
            $teaser = $plainDescription;
            if (mb_strlen($teaser) > 190) $teaser = mb_substr($teaser, 0, 187).'…';
            $url = 'wohnungstyp.php?id='.(int)$type['id'].'&lang='.rawurlencode($language);
            $price = (float)($type['standard_price'] ?? 0);
            $priceLabel = $price > 0 ? 'ab '.number_format($price, 2, ',', '.').' '.e($currency).' / Nacht' : e((string)($labels['price_request'] ?? 'Preis auf Anfrage'));
            $amenityHtml = '';
            if (normalize_bool($settings['show_amenities'] ?? 1)) {
                $amenityItems = [];
                foreach (array_slice($type['amenities'] ?? [], 0, 4) as $amenity) {
                    $amenityItems[] = '<span>'.e((string)($amenity['icon'] ?? '✓')).' '.e((string)($amenity['label'] ?? '')).'</span>';
                }
                $amenityHtml = $amenityItems ? '<div class="site-type-amenities">'.implode('', $amenityItems).'</div>' : '';
            }
            if ($images) {
                $imageHtml = '<a class="site-type-slider" data-site-type-slider data-site-slider-interval="'.e((string)$interval).'" href="'.e($url).'">';
                foreach ($images as $index => $image) {
                    $imageHtml .= '<span class="site-type-slide"><img src="'.e((string)($image['thumb_path'] ?: $image['file_path'])).'" alt="'.e((string)($image['alt_text'] ?: $type['public_name'])).'" loading="lazy"></span>';
                }
                $imageHtml .= '<span class="site-type-slider-badge">'.e((string)($type['code'] ?? '')).'</span>';
                if (count($images) > 1) {
                    $dots = '<span class="site-type-slider-dots">'.str_repeat('<i></i>', min(6, count($images))).'</span>';
                    $imageHtml .= '<span class="site-type-photo-count">📷 '.count($images).'</span>'.$dots;
                }
                $imageHtml .= '</a>';
            } else {
                $imageHtml = '<a class="site-type-image placeholder" href="'.e($url).'">🏡</a>';
            }
            $facts = '<div class="site-type-facts">'
                .'<span>👥 '.(int)$type['standard_occupancy'].'–'.(int)$type['max_occupancy'].' '.e((string)($labels['adults'] ?? 'Gäste')).'</span>'
                .'<span>🛏 '.(int)$type['bedrooms'].' '.e((string)($labels['bedrooms'] ?? 'Schlafzimmer')).'</span>'
                .'<span>🛌 '.(int)$type['beds'].' '.e((string)($labels['beds'] ?? 'Betten')).'</span>'
                .(!empty($type['living_area']) ? '<span>📐 '.e((string)$type['living_area']).' m²</span>' : '')
                .'</div>';
            $html .= '<article class="site-type-card site-type-card-'.$layout.'">'.$imageHtml.'<div class="site-type-card-body">'
                .'<div class="site-type-card-top"><div><span class="site-type-code">'.e((string)($type['code'] ?? '')).'</span><h3>'.e((string)$type['public_name']).'</h3></div><div class="site-type-price-chip">'.$priceLabel.'</div></div>'
                .$facts
                .(normalize_bool($settings['show_description'] ?? 1) && $teaser !== '' ? '<p class="site-type-teaser">'.e($teaser).'</p>' : '')
                .$amenityHtml
                .'<div class="site-type-foot-meta"><span>🔑 '.max(1,(int)($type['default_min_stay'] ?? 1)).' '.e((string)($labels['nights'] ?? 'Nächte')).' min.</span><span>ℹ️ '.e((string)($labels['nonbinding'] ?? 'Unverbindliche Anfrage')).'</span></div>'
                .'<div class="site-type-card-actions"><a class="site-btn secondary" href="'.e($url).'">'.e((string)($labels['type_details'] ?? 'Details')).'</a><a class="site-btn primary" href="buchung.php?lang='.e($language).'#booking-search">'.e((string)($labels['request'] ?? 'Anfragen')).'</a></div>'
                .'</div></article>';
        }
        return $html.'</div></div></section>';
    }


    private static function cardItems(array $content, array $settings, string $language, string $class, string $mode): string
    {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $html = '<section class="site-section site-block-'.$mode.'"><div class="site-container">'.self::sectionIntro($content).'<div class="'.$class.'">';
        foreach ($items as $item) {
            $icon = trim((string)($item['icon'] ?? '✓'));
            $title = trim((string)($item['title'] ?? ''));
            $text = trim((string)($item['text'] ?? ''));
            if ($title === '' && $text === '') continue;
            $html .= '<article><span>'.e($icon ?: '✓').'</span><h3>'.e($title).'</h3><p>'.e($text).'</p></article>';
        }
        return $html.'</div>'.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function ctaBanner(array $content, array $settings, string $language): string
    {
        $image = trim((string)($settings['image_url'] ?? $settings['background_image'] ?? ''));
        $style = $image !== '' ? ' style="--cta-bg:url(\''.e($image).'\')"' : '';
        return '<section class="site-section"><div class="site-container"><div class="site-cta-banner"'.$style.'><div>'.self::sectionIntro($content).PublicSiteService::sanitizeRich((string)($content['content_html'] ?? '')).'</div>'.self::blockButton($content, $settings, $language).'</div></div></section>';
    }

    private static function noticeBlock(array $content, array $settings, string $language, string $kind): string
    {
        $icon = match($kind){'price'=>'💶','season'=>'☀️','availability'=>'📅',default=>'ℹ️'};
        $html = '<section class="site-section compact"><div class="site-container"><div class="site-notice-block '.$kind.'"><span>'.$icon.'</span><div>'.self::sectionIntro($content).PublicSiteService::sanitizeRich((string)($content['content_html'] ?? '')).self::blockButton($content, $settings, $language).'</div></div></div></section>';
        return $html;
    }

    private static function trustBadges(array $content, array $settings, string $language): string
    {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $html = '<section class="site-section compact"><div class="site-container">'.self::sectionIntro($content).'<div class="site-trust-badges">';
        foreach ($items as $item) $html .= '<span><i>'.e((string)($item['icon'] ?? '✓')).'</i><b>'.e((string)($item['title'] ?? '')).'</b><small>'.e((string)($item['text'] ?? '')).'</small></span>';
        return $html.'</div>'.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function stats(array $content, array $settings, string $language): string
    {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $html = '<section class="site-section"><div class="site-container">'.self::sectionIntro($content).'<div class="site-stat-grid">';
        foreach ($items as $item) $html .= '<article><strong>'.e((string)($item['icon'] ?? '0')).'</strong><b>'.e((string)($item['title'] ?? '')).'</b><span>'.e((string)($item['text'] ?? '')).'</span></article>';
        return $html.'</div>'.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function timeline(array $content, array $settings, string $language): string
    {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $html = '<section class="site-section"><div class="site-container">'.self::sectionIntro($content).'<div class="site-timeline">';
        foreach ($items as $index => $item) $html .= '<article><span>'.e((string)($item['icon'] ?? (string)($index+1))).'</span><div><h3>'.e((string)($item['title'] ?? '')).'</h3><p>'.e((string)($item['text'] ?? '')).'</p></div></article>';
        return $html.'</div>'.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function tabs(array $content, array $settings, string $language): string
    {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $id = 'tabs_'.substr(hash('sha256', json_encode($items)), 0, 8);
        $html = '<section class="site-section"><div class="site-container">'.self::sectionIntro($content).'<div class="site-tabs" data-site-tabs id="'.e($id).'"><div class="site-tab-buttons">';
        foreach ($items as $index => $item) $html .= '<button type="button" class="'.($index===0?'active':'').'" data-site-tab="'.$index.'">'.e((string)($item['title'] ?? ('Tab '.($index+1)))).'</button>';
        $html .= '</div><div class="site-tab-panels">';
        foreach ($items as $index => $item) $html .= '<article '.($index===0?'':'hidden').'><p>'.e((string)($item['text'] ?? '')).'</p></article>';
        return $html.'</div></div>'.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function team(array $content, array $settings, string $language): string
    {
        return self::cardItems($content, $settings, $language, 'site-team-grid', 'team');
    }

    private static function downloadLinks(array $content, array $settings, string $language): string
    {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $html = '<section class="site-section"><div class="site-container">'.self::sectionIntro($content).'<div class="site-download-list">';
        foreach ($items as $item) {
            $title = trim((string)($item['title'] ?? 'Download'));
            $url = trim((string)($item['text'] ?? '#'));
            $html .= '<a href="'.e($url ?: '#').'" target="_blank" rel="noopener noreferrer"><span>'.e((string)($item['icon'] ?? '📄')).'</span><b>'.e($title).'</b><small>'.e($url).'</small></a>';
        }
        return $html.'</div>'.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function videoEmbed(array $content, array $settings, string $language): string
    {
        $url = trim((string)($content['button_url'] ?? $settings['video_url'] ?? ''));
        $embed = '';
        if ($url !== '') {
            if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([a-zA-Z0-9_-]+)~', $url, $m)) $embed = 'https://www.youtube-nocookie.com/embed/'.$m[1];
            elseif (preg_match('~vimeo\.com/(\d+)~', $url, $m)) $embed = 'https://player.vimeo.com/video/'.$m[1];
        }
        $media = $embed !== '' ? '<iframe src="'.e($embed).'" loading="lazy" allowfullscreen></iframe>' : '<div class="site-video-placeholder">▶ Video-Link im Button-Link eintragen</div>';
        return '<section class="site-section"><div class="site-container">'.self::sectionIntro($content).'<div class="site-video-box">'.$media.'</div>'.PublicSiteService::sanitizeRich((string)($content['content_html'] ?? '')).self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function richText(array $content, array $settings, string $language): string
    {
        return '<section class="site-section"><div class="site-container site-prose">'.self::sectionIntro($content).PublicSiteService::sanitizeRich((string)($content['content_html'] ?? '')).self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function imageText(array $content, array $settings, string $language): string
    {
        $image = trim((string)($settings['image_url'] ?? ''));
        $position = ($settings['image_position'] ?? 'right') === 'left' ? ' image-left' : ' image-right';
        return '<section class="site-section"><div class="site-container site-image-text'.$position.'"><div class="site-image-text-content">'.self::sectionIntro($content).PublicSiteService::sanitizeRich((string)($content['content_html'] ?? '')).self::blockButton($content, $settings, $language).'</div>'.($image !== '' ? '<img src="'.e($image).'" alt="'.e((string)($content['image_alt'] ?? '')).'" loading="lazy">' : '<div class="site-image-placeholder">🖼️</div>').'</div></section>';
    }

    private static function features(array $content, array $settings, string $language): string
    {
        $html = '<section class="site-section"><div class="site-container">'.self::sectionIntro($content).'<div class="site-feature-grid">';
        foreach ($content['items'] ?? [] as $item) $html .= '<article><span>'.e((string)($item['icon'] ?? '✓')).'</span><h3>'.e((string)($item['title'] ?? '')).'</h3><p>'.e((string)($item['text'] ?? '')).'</p></article>';
        return $html.'</div>'.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function faq(array $content, array $settings, string $language): string
    {
        $html = '<section class="site-section"><div class="site-container site-faq">'.self::sectionIntro($content);
        foreach ($content['items'] ?? [] as $item) $html .= '<details><summary>'.e((string)($item['title'] ?? '')).'</summary><p>'.e((string)($item['text'] ?? '')).'</p></details>';
        return $html.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function contact(array $content, array $settings, string $language): string
    {
        $design = PublicSiteService::design();
        $lat = trim((string)($design['contact_map_lat'] ?? ''));
        $lng = trim((string)($design['contact_map_lng'] ?? ''));
        $zoom = max(1, min(19, (int)($design['contact_map_zoom'] ?? 15)));
        $height = max(180, min(640, (int)($design['contact_map_height'] ?? 320)));
        $map = '';
        if (normalize_bool($design['contact_map_enabled'] ?? 0) && is_numeric($lat) && is_numeric($lng)) {
            $delta = 0.018 / max(1, ($zoom - 10));
            $bbox = [(float)$lng - $delta, (float)$lat - $delta, (float)$lng + $delta, (float)$lat + $delta];
            $src = 'https://www.openstreetmap.org/export/embed.html?bbox='.rawurlencode(implode(',', $bbox)).'&layer=mapnik&marker='.rawurlencode($lat.','.$lng);
            $map = '<iframe class="site-contact-map" title="Karte" loading="lazy" style="height:'.$height.'px" src="'.e($src).'"></iframe>';
        }
        $link = trim((string)($design['contact_map_link'] ?? ''));
        $mapLink = $link !== '' ? '<a class="site-contact-map-link" href="'.e($link).'" target="_blank" rel="noopener noreferrer">Karte öffnen</a>' : '';
        return '<section class="site-section"><div class="site-container site-contact-card"><div>'.self::sectionIntro($content).PublicSiteService::sanitizeRich((string)($content['content_html'] ?? '')).self::blockButton($content, $settings, $language).'</div><div class="site-contact-values">'.(!empty($design['address']) ? '<p>📍 '.nl2br(e((string)$design['address'])).'</p>' : '').(!empty($design['phone']) ? '<p><a href="tel:'.e((string)$design['phone']).'">☎ '.e((string)$design['phone']).'</a></p>' : '').(!empty($design['email']) ? '<p><a href="mailto:'.e((string)$design['email']).'">✉ '.e((string)$design['email']).'</a></p>' : '').$map.$mapLink.'</div></div></section>';
    }

    private static function gallery(array $content, array $settings, string $language): string
    {
        $html = '<section class="site-section"><div class="site-container">'.self::sectionIntro($content).'<div class="site-gallery">';
        foreach ($settings['images'] ?? [] as $url) $html .= '<img src="'.e((string)$url).'" alt="'.e((string)($content['image_alt'] ?? '')).'" loading="lazy">';
        return $html.'</div>'.self::blockButton($content, $settings, $language).'</div></section>';
    }


    private static function reviews(array $content, array $settings, string $language): string
    {
        $items = is_array($content['items'] ?? null) ? $content['items'] : [];
        $visible = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $name = trim((string)($item['title'] ?? ''));
            $text = trim((string)($item['text'] ?? ''));
            if ($name === '' && $text === '') continue;
            $parts = array_map('trim', explode('|', $text));
            $reviewText = $parts[0] ?? $text;
            $origin = $parts[1] ?? '';
            $period = $parts[2] ?? '';
            $stars = max(1, min(5, (int)preg_replace('/\D+/', '', (string)($item['icon'] ?? '5'))));
            $visible[] = ['name'=>$name, 'text'=>$reviewText, 'origin'=>$origin, 'period'=>$period, 'stars'=>$stars];
        }
        $limit = max(0, min(30, (int)($settings['limit'] ?? 0)));
        if ($limit > 0) $visible = array_slice($visible, 0, $limit);
        $html = '<section class="site-section site-reviews-section"><div class="site-container">'.self::sectionIntro($content).'<div class="site-review-grid">';
        foreach ($visible as $item) {
            $html .= '<article class="site-review-card"><div class="site-review-stars" aria-label="'.e((string)$item['stars']).' von 5 Sternen">'.str_repeat('★', $item['stars']).str_repeat('☆', 5 - $item['stars']).'</div><p>“'.e($item['text']).'”</p><footer><b>'.e($item['name']).'</b>'.($item['origin'] !== '' ? '<span>'.e($item['origin']).'</span>' : '').($item['period'] !== '' ? '<small>'.e($item['period']).'</small>' : '').'</footer></article>';
        }
        if (!$visible) $html .= '<div class="site-review-empty">Noch keine Bewertungen eingetragen.</div>';
        return $html.'</div>'.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function customHtml(array $content, array $settings, string $language): string
    {
        $html = PublicSiteService::sanitizeRich((string)($content['content_html'] ?? ''));
        if (trim($html) === '' && trim((string)($content['title'] ?? '')) === '') return '';
        return '<section class="site-section site-custom-html-section"><div class="site-container site-prose">'.self::sectionIntro($content).$html.self::blockButton($content, $settings, $language).'</div></section>';
    }

    private static function customForm(array $content, array $settings, string $language): string
    {
        $design = PublicSiteService::design();
        $index = max(1, min(3, (int)($settings['form_index'] ?? 1))) - 1;
        $forms = is_array($design['custom_forms'] ?? null) ? $design['custom_forms'] : [];
        $form = is_array($forms[$index] ?? null) ? $forms[$index] : [];
        if (!normalize_bool($form['active'] ?? 0)) return '';
        $fields = [];
        foreach (preg_split('/\R/', (string)($form['fields'] ?? '')) ?: [] as $n => $line) {
            $parts = array_map('trim', explode('|', $line));
            $label = $parts[0] ?? '';
            if ($label === '') continue;
            $type = in_array($parts[1] ?? 'text', ['text','email','tel','date','number','textarea','select','checkbox'], true) ? $parts[1] : 'text';
            $required = normalize_bool($parts[2] ?? 0);
            $help = $parts[3] ?? '';
            $options = array_values(array_filter(array_map('trim', explode(',', $parts[4] ?? ''))));
            $name = 'field_'.$n;
            $req = $required ? ' required' : '';
            $input = '';
            if ($type === 'textarea') $input = '<textarea name="'.e($name).'" rows="5"'.$req.'></textarea>';
            elseif ($type === 'select') {
                $input = '<select name="'.e($name).'"'.$req.'><option value="">Bitte wählen</option>';
                foreach ($options as $option) $input .= '<option value="'.e($option).'">'.e($option).'</option>';
                $input .= '</select>';
            } elseif ($type === 'checkbox') $input = '<label class="site-custom-check"><input type="checkbox" name="'.e($name).'" value="Ja"'.$req.'> '.e($label).'</label>';
            else $input = '<input type="'.e($type).'" name="'.e($name).'"'.$req.'>';
            $fields[] = '<input type="hidden" name="labels['.e($name).']" value="'.e($label).'">'.($type === 'checkbox' ? '<div class="site-custom-field">'.$input.($help !== '' ? '<small>'.e($help).'</small>' : '').'</div>' : '<label class="site-custom-field"><span>'.e($label).($required ? ' *' : '').'</span>'.$input.($help !== '' ? '<small>'.e($help).'</small>' : '').'</label>');
        }
        if (!$fields) return '';
        $title = trim((string)($content['title'] ?? '')) ?: (string)($form['name'] ?? 'Formular');
        $subtitle = trim((string)($content['subtitle'] ?? ''));
        return '<section class="site-section"><div class="site-container site-custom-form-wrap"><div>'.self::sectionIntro(['title'=>$title,'subtitle'=>$subtitle]).PublicSiteService::sanitizeRich((string)($content['content_html'] ?? '')).'</div><form class="site-custom-form" method="post" action="api/custom-form.php"><input type="hidden" name="language" value="'.e($language).'"><input type="hidden" name="form_index" value="'.($index+1).'"><input type="hidden" name="website" value="">'.implode('', $fields).'<button class="site-btn primary" type="submit">Senden</button></form></div></section>';
    }

    private static function spacer(array $settings): string
    {
        $height = max(10, min(240, (int)($settings['height'] ?? 40)));
        return '<div class="site-spacer '.(normalize_bool($settings['line'] ?? 0) ? 'with-line' : '').'" style="height:'.$height.'px"></div>';
    }

    private static function cookieConfig(array $design, string $language): array
    {
        $services = [];
        foreach (preg_split('/\R/', (string)($design['cookie_services'] ?? '')) ?: [] as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (($parts[0] ?? '') === '') continue;
            $services[] = [
                'name' => (string)$parts[0],
                'category' => in_array($parts[1] ?? 'preferences', ['necessary','preferences','statistics','marketing'], true) ? (string)$parts[1] : 'preferences',
                'cookies' => array_values(array_filter(array_map('trim', explode(',', (string)($parts[2] ?? ''))))),
                'description' => (string)($parts[3] ?? ''),
                'active' => normalize_bool($parts[4] ?? 1),
            ];
        }
        return [
            'language' => $language,
            'enabled' => normalize_bool($design['cookie_enabled'] ?? 1),
            'position' => (string)($design['cookie_position'] ?? 'bottom'),
            'margin' => max(0, min(80, (int)($design['cookie_margin'] ?? 18))),
            'padding' => max(8, min(60, (int)($design['cookie_padding'] ?? 18))),
            'radius' => max(0, min(40, (int)($design['cookie_radius'] ?? 18))),
            'background' => (string)($design['cookie_background'] ?? '#ffffff'),
            'text' => (string)($design['cookie_text'] ?? '#172033'),
            'accent' => (string)($design['cookie_accent'] ?? '#2563eb'),
            'notice' => (string)($design['cookie_notice'] ?? ''),
            'blockedText' => (string)($design['cookie_blocked_text'] ?? ''),
            'services' => $services,
        ];
    }

    private static function blockButton(array $content, array $settings, string $language): string
    {
        $url = trim((string)($content['button_url'] ?? ''));
        if ($url === '') return '';
        $label = trim((string)($content['button_label'] ?? '')) ?: (string)(PublicSiteService::labels($language)['details'] ?? 'Details');
        $size = (string)($settings['button_size'] ?? 'normal');
        if (!in_array($size, ['small', 'normal', 'large'], true)) {
            $size = 'normal';
        }
        $align = (string)($settings['button_align'] ?? 'left');
        if (!in_array($align, ['left', 'center', 'right'], true)) {
            $align = 'left';
        }
        $style = '--block-button-radius:'.max(0, min(40, (int)($settings['button_radius'] ?? 18))).'px;';
        foreach (['button_bg'=>'--block-button-bg','button_text'=>'--block-button-text'] as $key => $var) {
            $value = trim((string)($settings[$key] ?? ''));
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) $style .= $var.':'.$value.';';
        }
        return '<div class="site-block-button-row align-'.$align.'"><a class="site-btn site-block-button size-'.$size.'" style="'.e($style).'" href="'.e($url).'">'.e($label).'</a></div>';
    }
}
