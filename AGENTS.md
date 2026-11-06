# AGENTS.md

## Project

Omro — универсальный движок онлайн-каталога контента (Laravel 12, PHP 8.3) с кастомным
шаблонным движком (`App\Support\TplRenderer`). Шаблоны и ассеты тем лежат в
`resources/tpl/<theme>/`; Blade для страниц не используется. Админка — React SPA
(`admin-ui/`, билд в `public/_admin_ui`).

## Themes

- Theme is a folder under `resources/tpl/` containing `layout.tpl`; the folder name must match `[a-zA-Z0-9_-]+`.
- Switch themes only through `ThemeManager::setActive('<name>')` (it validates the folder).
- The active theme is a DB setting (`active_theme`), not a `.env` value. If the stored theme
  does not exist, `ThemeManager::activeName()` falls back to the first available theme.
- Git ships only the standard theme `default` (see `.gitignore`); user themes stay local.

### Theme structure (standard theme)

```
resources/tpl/default/
├── layout.tpl        # required, at theme root
├── maintenance.tpl   # standalone (full document)
├── pages/            # all page templates (home, catalog, search, series/show, ...)
├── partials/
├── errors/
├── modules/          # JS module sources (home-carousels)
└── assets/
```

- Body templates are referenced from controllers as `pages/<name>.tpl`.
  `TplRenderer::resolveTemplateFile()` also falls back to `pages/<name>.tpl` when a
  root-level file is missing (compat with older themes).
- `errors/*.tpl` are **body fragments** injected as `{content}`; `maintenance.tpl` is a
  standalone document (`MaintenancePageRenderer`).

### CSS/JS auto-inclusion (`ThemeManager::assetVars`)

- Engine assets are linked first in every theme: `/omrotpl/omrotpl.css` (structural styles,
  theme overrides them) and `/omrotpl/omrotpl.js` (functional layer, `defer`) with `?v=filemtime`.
- Theme stylesheets (in this order, when present): `fonts.css`, `style.formated.css`,
  `new-dark.css`, `site.css`, `auth.css`, `theme-overrides.css`. `libs.js` and `site.js`
  auto-include with `defer` if the files exist.
- The functional JS of the engine lives in `resources/omrotpl/omrotpl.js` (source) and is built
  to `public/omrotpl/omrotpl.js(.min)` by `npm run build:theme`. It is NOT tied to theme classes:
  modules bind to data-attributes and the `data-omro-*` contract (see the omrotpl-contract
  article in TPL-DOC); legacy hooks of the standard theme (`.js-*`, `.ls-*` classes, element ids)
  still work.

**Do not name a ported stylesheet `assets/site.css`** — it would be injected after the theme's own
manually linked CSS and invert the cascade.

### Asset serving

- Public URL: `/theme-assets/{theme}/assets/{path}` (nested paths supported).
- Allowed extensions: `css js svg png jpg jpeg gif webp woff woff2`. `.ico`, `.ttf`, `.eot` return 404.
- Responses carry `Cache-Control: immutable, max-age=31536000`.
- `TplController::injectFreshAssetVersions()` rewrites `?v=<digits>` on theme `.css`/`.js` URLs
  and on `/omrotpl/omrotpl.*` URLs to the file's `filemtime` on every response, so cached pages
  never serve stale assets.
- `{site.logo}` resolves only from `assets/logo.svg`.

### Minified assets

- `npm run build:theme` runs `scripts/build-theme-assets.mjs`: it (1) builds `public/omrotpl/`
  from `resources/omrotpl/` (contract layer + engine JS source), (2) bundles `THEME_MODULE_BUNDLES`,
  (3) minifies every `.css`/`.js` under `resources/tpl/*/assets/`.
- Run it from the repository root. `*.min.*` artifacts are tracked in git — commit them together
  with their sources.
- When `app.debug=false` the server serves `*.min.css` / `*.min.js` if they exist, so stale `.min`
  files can hide source edits. Rebuild at the end of a rework.

### Template syntax (`App\Support\TplRenderer`)

Processing order: `{include}` → `[loop]` → `[not-key]` → `[key]` → `{var}`.

- Supported: `{include file="partials/x.tpl"}` (depth ≤ 5), `[loop list]…[/loop]`,
  `[key]…[/key]` and `[not-key]…[/not-key]` with top-level `[else]`, `{var}`, `{var|raw}`,
  `{var|date:'d.m.Y'}`.
- **Not supported**: DLE tags (`{custom}`, `[xfvalue_*]`, `[group=5]`) — emitted literally.
- `isEmptyValue()` treats `0`, `0.0`, `''` and `[]` as empty, so `[series.rating]` hides a zero rating.
- Full reference for humans: admin → TPL-DOC (`TplGuide` v2.0, machine-readable data in
  `TplDocumentation` + `TplSampleValues`).

### Page cache (`App\Support\TplCache`)

Rendered HTML is cached per theme/URL/body-template. The key uses the **body template's** mtime, so
editing `layout.tpl` or a partial does not invalidate it. In development call
`TplCache::bumpGlobalVersion()` after such edits, otherwise pages stay stale (the admin template
editor does it automatically):

```powershell
& "C:\OSPanel6\modules\PHP-8.3\php.exe" -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); App\Support\TplCache::bumpGlobalVersion();'
```

## Engine admin

- Engine updater: `App\Services\EngineUpdater` + `/api/admin/system/update/check|run`
  (git-based, works with any git hosting). `VERSION` file at repo root.
- KP player parse hosts allowlist: `config/importer.php` (`kp_parse_hosts`).
- Content types: `config/series.php` (`content_types`) + `App\Support\ContentTypes`
  (tpl flags `[type-N]`, dashboard breakdown `content_types` in `/api/admin/stats`).

### Known pre-existing gaps

- Player tabs (`[data-player-tabs]`) are inert: `initPlayerTabs()` requires a `[data-trailer-box]`
  ancestor that no template renders.
- The embedded player script logs `Error: Don't find site option`.
