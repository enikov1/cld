<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#111">
    <style id="site-critical">
        html { background-color: #f1f3f5; }
        .wrap { padding-top: 0 !important; }
        [site.has_background]
        :root {
            --site-bg-color: {site.background_color};
            --site-bg-image: url('{site.background|raw}');
        }
        html { background-color: var(--site-bg-color); }
        body.has-site-bg,
        body.has-site-bg.dt {
            background-color: var(--site-bg-color);
            background-image: url('{site.background|raw}');
            background-position: center top;
            background-repeat: no-repeat;
            background-size: 100% auto;
        }
        body.has-site-bg .wrap {
            padding-top: 200px !important;
        }
        body.has-site-bg .wrap-center.wrap-main {
            margin-top: 0;
        }
        @media (max-width: 768px) {
            body.has-site-bg.site-bg-hide-mobile .wrap {
                padding-top: 0 !important;
            }
            body.has-site-bg.site-bg-hide-mobile,
            body.has-site-bg.site-bg-hide-mobile.dt {
                background-image: none;
            }
        }
        [/site.has_background]
    </style>

    [site.has_favicon]
    <link rel="shortcut icon" href="{site.favicon|raw}" />
    [/site.has_favicon]
    [site.has_background]
    <link rel="preload" as="image" href="{site.background|raw}">
    [/site.has_background]

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

    [theme.font_preloads]
    [loop theme.font_preloads]
    <link rel="preload" as="font" type="font/woff2" href="{item|raw}">
    [/loop]
    [/theme.font_preloads]
    [theme.stylesheets] [loop theme.stylesheets]
    <link rel="stylesheet" href="{item|raw}"> [/loop] [/theme.stylesheets]
    [theme.deferred_stylesheets]
    [loop theme.deferred_stylesheets]
    <link rel="stylesheet" href="{item|raw}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{item|raw}"></noscript>
    [/loop]
    [/theme.deferred_stylesheets]

    <meta name="csrf-token" content="{csrf_token|raw}">
</head>

<body class="{site.body_class|raw}" data-auth-panel="{auth_panel|raw}"[auth.logged_in] data-logged-in="1"[/auth.logged_in]>
    <script>
        (function () { try { if (localStorage.getItem('darkTheme') === '1') document.body.classList.add('dt'); } catch (e) {} })();
    </script>
    <div class="wrap">
        <div class="wrap-center wrap-main">
            {header|raw} {notifications_dropdown|raw}
            [ad_header_code]
            <div class="ad-header">{ad_header_code|raw}</div>
            [/ad_header_code]
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
