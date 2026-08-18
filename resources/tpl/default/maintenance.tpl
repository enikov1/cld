<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#111">
    <title>{maintenance_title} — {site_name}</title>
    [has_favicon]
    <link rel="shortcut icon" href="{site_favicon_url|raw}" />
    [/has_favicon]
    [has_fonts_css]
    <link rel="stylesheet" href="{fonts_css|raw}">
    [/has_fonts_css]
    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: "Open Sans", Arial, sans-serif;
            color: #1f2937;
            background: #111 url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="4" height="4"><rect fill="%23181818" width="4" height="4"/></svg>') center top repeat;
        }
        .maintenance {
            width: min(100%, 520px);
            padding: 40px 32px 36px;
            text-align: center;
            background: #fff;
            border: 1px solid #e8ebf0;
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
        }
        .maintenance__logo {
            display: block;
            max-width: 180px;
            max-height: 56px;
            margin: 0 auto 20px;
            object-fit: contain;
        }
        .maintenance__site {
            margin: 0 0 8px;
            font-family: Oswald, sans-serif;
            font-size: 14px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #79c142;
        }
        .maintenance__icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f9e8;
            color: #79c142;
            font-size: 28px;
            line-height: 1;
        }
        .maintenance__title {
            margin: 0 0 12px;
            font-family: Oswald, sans-serif;
            font-size: 28px;
            line-height: 1.2;
            color: #1f2937;
        }
        .maintenance__text {
            margin: 0;
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
        }
        .maintenance__footer {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #eef0f4;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="maintenance">
        [has_logo]
        <img class="maintenance__logo" src="{site_logo_url|raw}" alt="{site_name}">
        [/has_logo]
        [not-has_logo]
        <p class="maintenance__site">{site_name}</p>
        [/not-has_logo]
        <div class="maintenance__icon" aria-hidden="true">&#9881;</div>
        <h1 class="maintenance__title">{maintenance_title}</h1>
        <p class="maintenance__text">{maintenance_message}</p>
        <div class="maintenance__footer">&copy; {year} {site_name}</div>
    </div>
</body>
</html>
