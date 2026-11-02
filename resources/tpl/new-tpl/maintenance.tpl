<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1b1b1f">
    <title>{maintenance_title} — {site_name}</title>
    [has_favicon]
    <link rel="shortcut icon" href="{site_favicon_url|raw}" />
    [/has_favicon]
    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #2b2b31;
            background: #1b1b1f;
        }
        .maintenance {
            width: min(100%, 520px);
            padding: 40px 32px 36px;
            text-align: center;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.32);
        }
        .maintenance__logo {
            display: block;
            max-width: 180px;
            max-height: 56px;
            margin: 0 auto 20px;
            object-fit: contain;
        }
        .maintenance__site {
            margin: 0 0 20px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6f7af4;
        }
        .maintenance__icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(111, 122, 244, 0.12);
            color: #6f7af4;
            font-size: 28px;
            line-height: 1;
        }
        .maintenance__title {
            margin: 0 0 12px;
            font-size: 27px;
            font-weight: 700;
            line-height: 1.2;
            color: #1f1f25;
        }
        .maintenance__text {
            margin: 0;
            color: #63636e;
            font-size: 16px;
            line-height: 1.6;
        }
        .maintenance__footer {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #ececf1;
            font-size: 13px;
            color: #9a9aa5;
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
