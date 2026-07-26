<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111">

    [site.has_favicon]
    <link rel="shortcut icon" href="{site.favicon|raw}" />
    [/site.has_favicon]

    <title>{meta.title}</title>
    <meta name="description" content="{meta.description}">
    <link rel="canonical" href="{meta.canonical}"> [meta.robots]
    <meta name="robots" content="{meta.robots|raw}"> [/meta.robots] [meta.prev]
    <link rel="prev" href="{meta.prev|raw}"> [/meta.prev] [meta.next]
    <link rel="next" href="{meta.next|raw}"> [/meta.next] {meta.og|raw} {meta.twitter|raw} [seo_jsonld]
    <script type="application/ld+json">
        {seo_jsonld|raw}
    </script>
    [/seo_jsonld] [seo_google_verification]
    <meta name="google-site-verification" content="{seo_google_verification}"> [/seo_google_verification] [seo_yandex_verification]
    <meta name="yandex-verification" content="{seo_yandex_verification}"> [/seo_yandex_verification]

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Oswald:wght@600;700;800&display=swap" rel="stylesheet"> [theme.stylesheets] [loop theme.stylesheets]
    <link rel="stylesheet" href="{item|raw}"> [/loop] [/theme.stylesheets] [site.has_background]
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
        
        body.has-site-bg.dt {
            background-color: var(--site-bg-color);
        }
        
        body.has-site-bg .wrap-main {
            margin-top: var(--site-bg-header-offset);
        }
        
        @media (max-width: 768px) {
            body.has-site-bg.site-bg-hide-mobile {
                background-image: none;
            }
            
            body.has-site-bg.site-bg-hide-mobile .wrap-main {
                margin-top: 0;
            }
        }
    </style>
    [/site.has_background]

    <meta name="csrf-token" content="{csrf_token|raw}">
</head>

<body class="{site.body_class|raw}" data-auth-panel="{auth_panel|raw}"[auth.logged_in] data-logged-in="1"[/auth.logged_in]>
    <div class="wrap">
        <div class="wrap-center wrap-main">
            {header|raw} {notifications_dropdown|raw}
            <div class="content">
                [speedbar_block]
                <div class="speedbar">{speedbar_block|raw}</div>
                [/speedbar_block] {content|raw}
            </div>
            {footer|raw}
        </div>
    </div>
    {auth_overlay|raw}
    <div class="bookmark-toast" id="bookmarkToast" hidden role="status" aria-live="polite"></div>
    <script type="application/json" id="site-config">
        {site_config_json|raw}
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
    [seo_counters_code] {seo_counters_code|raw} [/seo_counters_code]
</body>

</html>