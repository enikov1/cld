# AGENTS.md

## Project

Laravel 12 application with a custom template engine (`App\Support\TplRenderer`).
Templates and assets for each theme live in `resources/tpl/<theme>/`; no Blade is used for pages.

## Themes

- Theme is a folder under `resources/tpl/` containing `layout.tpl`; the folder name must match `[a-zA-Z0-9_-]+`.
- Switch themes only through `ThemeManager::setActive('<name>')` (it validates the folder).
  Never leave a work-in-progress theme active after verification.
- The active theme is a setting, not a `.env` value.

### CSS/JS auto-inclusion (`ThemeManager::assetVars`)

These files are linked automatically when present: `fonts.css`, `style.formated.css`, `new-dark.css`,
`site.css`, `auth.css`, `theme-overrides.css`. `libs.js` and `site.js` are auto-included with `defer`.

**Do not name a ported stylesheet `assets/site.css`** — it would be injected after the theme's own
manually linked CSS and invert the cascade.

### Asset serving

- Public URL: `/theme-assets/{theme}/assets/{path}` (nested paths supported).
- Allowed extensions: `css js svg png jpg jpeg gif webp woff woff2`. `.ico`, `.ttf`, `.eot` return 404.
- Responses carry `Cache-Control: immutable, max-age=31536000`.
- `TplController::injectFreshAssetVersions()` rewrites `?v=<digits>` on theme `.css`/`.js` URLs to the
  file's `filemtime` on every response, so a literal `?v=1` in `layout.tpl` is safe — no manual bumping.
- `{site.logo}` resolves only from `assets/logo.svg`.

### Minified assets

- `npm run build:theme` runs `scripts/build-theme-assets.mjs`: it bundles `THEME_MODULE_BUNDLES` and
  minifies every `.css`/`.js` under `resources/tpl/*/assets/`.
- Run it from the repository root (the script resolves `themesRoot` from its own location).
- `*.min.*` artifacts are tracked in git — commit them together with their sources.
- When `app.debug=false` the server serves `*.min.css` / `*.min.js` if they exist, so stale `.min`
  files can hide source edits. Delete theme `.min.*` files when reworking a theme, rebuild at the end.

### Template syntax (`App\Support\TplRenderer`)

- Supported: `[loop list]…[/loop]`, `[key]…[/key]`, `[not-key]…[/not-key]`, `{var}`, `{var|raw}`.
- **Not supported**: `[else]`, `{include}`, DLE tags (`{custom}`, `[xfvalue_*]`, `[group=5]`).
  They are emitted literally.
- `isEmptyValue()` treats `0`, `0.0`, `''` and `[]` as empty, so `[series.rating]` hides a zero rating.
- `partials/header.tpl`, `footer.tpl`, `sidebar.tpl`, `notifications_dropdown.tpl`, `auth_overlay.tpl`
  are rendered by PHP and injected as `{header|raw}` etc. — they are not includes.

### Error and maintenance pages

- `errors/*.tpl` are **body fragments**: `ErrorPageRenderer` injects them as `{content}` into
  `layout.tpl`. No `<!doctype>`, `<html>`, `<head>` or `{header}`.
- `maintenance.tpl` is a **standalone document** (`MaintenancePageRenderer`): full `<!doctype html>`.

### Page cache (`App\Support\TplCache`)

Rendered HTML is cached per theme/URL/body-template. The key uses the **body template's** mtime, so
editing `layout.tpl` or a partial does not invalidate it. In development call
`TplCache::bumpGlobalVersion()` after such edits, otherwise pages stay stale:

```powershell
& "C:\OSPanel6\modules\PHP-8.3\php.exe" -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); App\Support\TplCache::bumpGlobalVersion();'
```

## Theme `new-tpl` (port of the `russserials` reference)

- Source of truth for the design: `Тестовый шаблон для референса/russserials` (committed reference).
  Reference `style/styles.css`, `style/engine.css`, `js/libs.js` are copied flat into `assets/`
  (`../fonts/` → `fonts/`, `../images/` → `images/`, `../dleimages/` → `dleimages/`).
- `assets/site.js` is the engine script and must stay unchanged.
- The reference `libs.js` is a DLE-era build that calls `jQuery.fn.andSelf` (removed in jQuery 3).
  `layout.tpl` defines `jQuery.fn.andSelf = jQuery.fn.addBack` before the deferred theme scripts.
  (The same error exists in `russia-default`; only `new-tpl` is shimmed.)
- The reference stylesheet styles **every** `button:not(.color-btn)` as a brown 40px plate, so the
  first rule of `theme-overrides.css` resets `button.dontusebuttonclass` (specificity 0,1,1).
  Every `<button>` in the theme therefore needs `dontusebuttonclass` **or** `color-btn`, and all
  widget button rules must be written as `button.<class>` after that reset.
- `[notifications_dropdown]` sits in `<div class="notify-slot">` (`display: contents`);
  `.series-dropdown` is `position: fixed` because `site.js` positions it inline.
- Flex order is explicit: `.header` 1, `.f-menu` 2, `.ad-header` 3, `.karusel` 4, `.cols` 5,
  `.footer` 6.
- `.fpseries` is rendered from `[loop schedule] → [loop item.episodes]`; the reference stylesheet
  already styles it.

### Known pre-existing gaps (not caused by the theme)

- Player tabs (`[data-player-tabs]`) are inert in both `russia-default` and `new-tpl`:
  `site.js:initPlayerTabs()` requires a `[data-trailer-box]` ancestor that no template renders.
- The embedded player script logs `Error: Don't find site option` on both themes.
