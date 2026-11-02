<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f2f6f8">

    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <title>{meta.title}</title>
    <meta name="description" content="{meta.description}">
    <link rel="canonical" href="{meta.canonical}">
    [meta.robots]
    <meta name="robots" content="{meta.robots|raw}">
    [/meta.robots]
    [meta.prev]
    <link rel="prev" href="{meta.prev|raw}">
    [/meta.prev]
    [meta.next]
    <link rel="next" href="{meta.next|raw}">
    [/meta.next]
    {meta.og|raw} {meta.twitter|raw}
    [seo_jsonld]
    <script type="application/ld+json">{seo_jsonld|raw}</script>
    [/seo_jsonld]
    [seo_google_verification]
    <meta name="google-site-verification" content="{seo_google_verification}">
    [/seo_google_verification]
    [seo_yandex_verification]
    <meta name="yandex-verification" content="{seo_yandex_verification}">
    [/seo_yandex_verification]

    <style>
        @font-face {
            font-family: 'FontAwesome';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('{THEME|raw}/assets/fonts/fontawesome-webfont.woff2') format('woff2');
        }
    </style>
    <link rel="preload" href="{THEME|raw}/assets/fonts/inter-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{THEME|raw}/assets/fonts/inter-500.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{THEME|raw}/assets/fonts/inter-600.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{THEME|raw}/assets/fonts/inter-700.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{THEME|raw}/assets/styles.css?v=1" as="style">
    <link rel="preload" href="{THEME|raw}/assets/engine.css?v=1" as="style">
    <link rel="stylesheet" href="{THEME|raw}/assets/styles.css?v=1">
    <link rel="stylesheet" href="{THEME|raw}/assets/engine.css?v=1">
    [theme.stylesheets]
    [loop theme.stylesheets]
    <link rel="stylesheet" href="{item|raw}">
    [/loop]
    [/theme.stylesheets]

    [site.has_background]
    <style id="site-branding">
        :root {
            --site-bg-header-offset: {site.background_header_offset}px;
            --site-bg-color: {site.background_color};
        }

        body.has-site-bg {
            background-color: var(--site-bg-color);
            background-image: url('{site.background|raw}');
            background-position: center top;
            background-repeat: no-repeat;
        }

        body.has-site-bg .block.center {
            margin-top: var(--site-bg-header-offset);
        }

        @media (max-width: 768px) {
            body.has-site-bg.site-bg-hide-mobile {
                background-image: none;
            }

            body.has-site-bg.site-bg-hide-mobile .block.center {
                margin-top: 0;
            }
        }
    </style>
    [/site.has_background]

    <meta name="csrf-token" content="{csrf_token|raw}">
</head>

<body class="{site.body_class|raw}" data-auth-panel="{auth_panel|raw}"[auth.logged_in] data-logged-in="1"[/auth.logged_in]>
    <div class="wrap">
        <div class="block center fx-col">
            {header|raw}
            <div class="notify-slot">{notifications_dropdown|raw}</div>
            [ad_header_code]
            <div class="ad-header">{ad_header_code|raw}</div>
            [/ad_header_code]

            [popular_list]
            <div class="karusel">
                <div class="owl-carousel" id="owl-top">
                    [loop popular_list]
                    <a class="top-carou img-box" href="{item.url|raw}" title="{item.title}">
                        <img src="{item.poster_url|raw}" alt="{item.title}" loading="lazy">
                        [item.episode_badge]<div class="badge_tr">{item.episode_badge}</div>[/item.episode_badge]
                        <div class="tc-title">{item.title}</div>
                    </a>
                    [/loop]
                </div>
            </div>
            [/popular_list]

            <div class="cols clearfix" id="cols">
                {content|raw}
                {sidebar|raw}
            </div>

            {footer|raw}
        </div>
    </div>

    {auth_overlay|raw}
    <div class="bookmark-toast" id="bookmarkToast" hidden role="status" aria-live="polite"></div>
    <script type="application/json" id="site-config">{site_config_json|raw}</script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
        // The ported reference libs.js is a DLE-era build that calls jQuery.fn.andSelf,
        // removed in jQuery 3.0. Restore the alias before the deferred theme scripts run.
        if (window.jQuery && !jQuery.fn.andSelf) {
            jQuery.fn.andSelf = jQuery.fn.addBack;
        }
    </script>
    [theme.scripts]
    [loop theme.scripts]
    <script src="{item|raw}" defer></script>
    [/loop]
    [/theme.scripts]
    [not-theme.scripts]
    [theme.js]
    <script src="{theme.js|raw}" defer></script>
    [/theme.js]
    [/not-theme.scripts]
    [seo_counters_code]{seo_counters_code|raw}[/seo_counters_code]
</body>

</html>
