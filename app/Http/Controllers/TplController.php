<?php

namespace App\Http\Controllers;

use App\Support\AdminPath;
use App\Support\NavMenuBuilder;
use App\Support\Speedbar;
use App\Support\SiteConfig;
use App\Support\ThemeManager;
use App\Support\TplCache;
use App\Support\TplRenderer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

abstract class TplController extends Controller
{
    protected function renderer(): TplRenderer
    {
        return new TplRenderer(ThemeManager::activeBaseDir());
    }

    protected function renderPartial(string $tpl, array $vars = []): string
    {
        return $this->renderer()->render($tpl, array_merge($this->commonVars(), $vars));
    }

    protected function themePartialExists(string $tpl): bool
    {
        $tpl = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $tpl), DIRECTORY_SEPARATOR);
        if (!str_ends_with(strtolower($tpl), '.tpl')) {
            $tpl .= '.tpl';
        }

        return is_file(ThemeManager::activeBaseDir() . DIRECTORY_SEPARATOR . $tpl);
    }

    /**
     * Attach speedbar HTML block and JSON-LD breadcrumbs to template vars.
     *
     * @param array<string,mixed> $vars
     * @param array<string,mixed>|list<array<string,mixed>>|null $extraJsonLd
     */
    protected function applySpeedbar(Speedbar $speedbar, array &$vars, ?array $extraJsonLd = null): void
    {
        if ($speedbar->isEmpty()) {
            $vars['speedbar'] = ['items' => []];
            $vars['speedbar_block'] = '';
            return;
        }

        $items = $speedbar->items();
        foreach ($items as $i => $item) {
            $items[$i]['is_last'] = $i === count($items) - 1;
        }

        $vars['speedbar'] = ['items' => $items];
        $vars['speedbar_block'] = $this->renderPartial('partials/speedbar.tpl', [
            'speedbar' => ['items' => $items],
        ]);
        $vars['seo_jsonld'] = $speedbar->toJsonLd($extraJsonLd);
    }

    /**
     * @return array<string,mixed>
     */
    protected function commonVars(): array
    {
        $user = Auth::user();
        $formErrors = $this->flattenValidationErrors(session('errors'));
        $authPanel = (string)session('auth_panel', '');
        $queryAuth = (string)request()->query('auth', '');

        if ($authPanel === '' && in_array($queryAuth, ['login', 'register', 'forgot', 'reset'], true)) {
            $authPanel = $queryAuth;
        }

        if ($authPanel === '' && old('name') !== null && old('name') !== '') {
            $authPanel = 'register';
        } elseif ($authPanel === '' && count($formErrors) > 0 && old('email') !== null) {
            $authPanel = 'login';
        }

        return array_merge([
            'csrf_token' => csrf_token(),
            'search_query' => (string)request()->query('q', ''),
            'site_config_json' => json_encode($this->siteConfigForJs(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'auth' => [
                'logged_in' => (bool)$user,
                'is_admin' => (bool)($user?->isAdmin()),
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
            ],
            'admin_url' => AdminPath::base(),
            'auth_panel' => $authPanel,
            'auth_email' => (string)old('email', request()->query('email', '')),
            'auth_notice' => session('auth_notice'),
            'reset_token' => (string)old('token', request()->query('token', '')),
            'comment_notice' => session('comment_notice'),
            'auth_errors_list' => $formErrors,
            'form_errors_list' => $formErrors,
            'THEME' => ThemeManager::webPath(),
            'theme' => ThemeManager::assetVars(),
            'site' => array_merge([
                'name' => \App\Models\SiteSetting::get('site_name', config('app.name', 'LordSerial')),
                'tagline' => \App\Models\SiteSetting::get('site_tagline', 'Сериалы онлайн'),
                'footer_text' => \App\Models\SiteSetting::get('footer_text', 'Сериалы онлайн в HD качестве'),
                'year' => (string)date('Y'),
            ], \App\Support\SiteBranding::siteVars()),
            'has_notifications' => SiteConfig::bool('notifications_enabled'),
            'favourites_enabled' => SiteConfig::bool('favourites_enabled'),
        ], NavMenuBuilder::forTpl(), SiteConfig::forTpl());
    }

    /**
     * @return array<string, mixed>
     */
    protected function siteConfigForJs(): array
    {
        $payload = SiteConfig::forJs();
        $theme = ThemeManager::assetVars();

        if (!empty($theme['home_carousels_js'])) {
            $payload['home_carousels_js'] = $theme['home_carousels_js'];
        }
        if (!empty($theme['home_carousels_css'])) {
            $payload['home_carousels_css'] = $theme['home_carousels_css'];
        }

        $payload['admin_url'] = AdminPath::base();
        if (SiteConfig::bool('notifications_enabled')) {
            $payload['vapid_public_key'] = \App\Services\WebPushService::publicKey() ?? '';
        }

        return $payload;
    }

    /**
     * @return list<array{message: string}>
     */
    protected function flattenValidationErrors(mixed $bag): array
    {
        if (!$bag instanceof \Illuminate\Support\ViewErrorBag) {
            return [];
        }

        $out = [];
        foreach ($bag->getMessages() as $messages) {
            foreach ($messages as $message) {
                $out[] = ['message' => $message];
            }
        }

        return $out;
    }

    /**
     * Render page body tpl, then wrap it with layout.tpl.
     *
     * @param string $bodyTpl like "home.tpl" or "collections/index.tpl"
     * @param array<string,mixed> $vars
     * @param array<string,string> $meta
     */
    protected function renderTplPage(string $bodyTpl, array $vars, array $meta): \Illuminate\Http\Response
    {
        $renderer = $this->renderer();
        $common = $this->commonVars();
        $vars = array_merge($common, $vars);
        $vars['speedbar_block'] = $vars['speedbar_block'] ?? '';
        $vars['seo'] = array_merge([
            'title' => '',
            'description' => '',
            'canonical' => '',
            'robots' => '',
            'image' => '',
            'prev' => '',
            'next' => '',
            'og' => '',
            'twitter' => '',
        ], $meta);

        $cacheTtl = (int)config('tpl.cache_ttl', 300);
        $authKey = (string)(Auth::id() ?? 'guest');
        $themeKey = ThemeManager::activeName();
        $seriesCacheId = isset($vars['_cache_series_id']) ? (int)$vars['_cache_series_id'] : null;
        if ($seriesCacheId) {
            unset($vars['_cache_series_id']);
        }

        $tplPath = ThemeManager::activeBaseDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($bodyTpl, '/'));
        if (!str_ends_with(strtolower($tplPath), '.tpl')) {
            $tplPath .= '.tpl';
        }
        $tplMtime = is_file($tplPath) ? (int)filemtime($tplPath) : 0;

        $render = function () use ($renderer, $bodyTpl, $vars, $meta) {
            $page = $renderer->renderPage($bodyTpl, $vars);
            $resolvedMeta = $this->resolvePageMeta($meta, $page['meta'], $vars);

            $layoutVars = $vars;
            $layoutVars['content'] = $page['content'];
            $layoutVars['header'] = $renderer->render('partials/header.tpl', $vars);
            $layoutVars['notifications_dropdown'] = SiteConfig::bool('notifications_enabled')
                ? $renderer->render('partials/notifications_dropdown.tpl', $vars)
                : '';
            $layoutVars['sidebar'] = is_file(ThemeManager::activeBaseDir() . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'sidebar.tpl')
                ? $renderer->render('partials/sidebar.tpl', $vars)
                : '';
            $layoutVars['footer'] = $renderer->render('partials/footer.tpl', $vars);
            $layoutVars['auth_overlay'] = (
                SiteConfig::bool('auth_login_enabled')
                || SiteConfig::bool('auth_register_enabled')
                || SiteConfig::bool('auth_password_reset_enabled')
            ) ? $renderer->render('partials/auth_overlay.tpl', $vars) : '';
            $layoutVars['meta'] = $this->buildLayoutMeta($resolvedMeta);

            return $renderer->render('layout.tpl', $layoutVars);
        };

        if ($seriesCacheId) {
            $html = TplCache::rememberSeriesPage($seriesCacheId, $authKey, $cacheTtl, $render);
        } elseif ($this->shouldBypassTplHtmlCache($vars)) {
            // Flash/auth UI is session-specific — do not store in shared HTML cache.
            $html = $render();
        } else {
            $homeVersion = in_array($bodyTpl, ['home.tpl', 'catalog.tpl'], true) ? TplCache::homeVersion() : 0;
            // Key must NOT include serialize($vars): csrf/session flash made every
            // guest hit unique and bloated the database cache with dead HTML rows.
            $cacheKey = 'tpl:' . md5(implode('|', [
                request()->fullUrl(),
                $themeKey,
                $bodyTpl,
                $authKey,
                'hv:' . $homeVersion,
                'gv:' . TplCache::globalVersion(),
                'tm:' . $tplMtime,
            ]));
            $html = Cache::remember($cacheKey, $cacheTtl, $render);
        }

        // Cached HTML may contain another session's CSRF token (shared guest cache).
        $html = $this->injectCurrentCsrfToken((string) $html);
        $html = $this->injectFreshAssetVersions($html);

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Session-bound UI that must not be served from (or written into) shared HTML cache.
     *
     * @param  array<string, mixed>  $vars
     */
    protected function shouldBypassTplHtmlCache(array $vars): bool
    {
        if (($vars['auth_panel'] ?? '') !== '') {
            return true;
        }
        if (!empty($vars['auth_notice']) || !empty($vars['comment_notice'])) {
            return true;
        }
        if (!empty($vars['auth_errors_list']) || !empty($vars['form_errors_list'])) {
            return true;
        }

        return false;
    }

    protected function injectCurrentCsrfToken(string $html): string
    {
        $token = csrf_token();
        $replaced = preg_replace(
            '/(<meta\s+name=["\']csrf-token["\']\s+content=["\'])[^"\']*(["\'])/i',
            '${1}' . $token . '${2}',
            $html,
            1
        );
        if (!is_string($replaced)) {
            $replaced = $html;
        }

        $replaced = preg_replace(
            '/(<input\b[^>]*\bname=["\']_token["\'][^>]*\bvalue=["\'])[^"\']*(["\'])/i',
            '${1}' . $token . '${2}',
            $replaced
        );

        // Also handle value before name attribute order.
        if (is_string($replaced)) {
            $replaced = preg_replace(
                '/(<input\b[^>]*\bvalue=["\'])[^"\']*(["\'][^>]*\bname=["\']_token["\'])/i',
                '${1}' . $token . '${2}',
                $replaced
            );
        }

        return is_string($replaced) ? $replaced : $html;
    }

    /**
     * Page HTML is cached, but theme asset ?v= must stay in sync with filemtime.
     */
    protected function injectFreshAssetVersions(string $html): string
    {
        $replaced = preg_replace_callback(
            '#((?:https?:)?(?://[^/]+)?/theme-assets/([^/]+)/assets/([^?\s"\']+\.(?:js|css)))\?v=\d+#i',
            static function (array $matches): string {
                $urlPath = $matches[1];
                $theme = $matches[2];
                $file = $matches[3];
                $diskPath = ThemeManager::resolveAssetDiskPath($file, $theme);
                if (!$diskPath || !is_file($diskPath)) {
                    return $matches[0];
                }

                return $urlPath . '?v=' . filemtime($diskPath);
            },
            $html
        );

        return is_string($replaced) ? $replaced : $html;
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, string> $fromTpl
     * @param array<string, mixed> $vars
     * @return array<string, string>
     */
    protected function resolvePageMeta(array $defaults, array $fromTpl, array $vars): array
    {
        // Шаблон задаёт fallback; непустые значения из контроллера (админка) имеют приоритет.
        $merged = $fromTpl;
        foreach ($defaults as $key => $value) {
            if (trim((string)$value) !== '') {
                $merged[$key] = (string)$value;
            }
        }

        if (empty($merged['title'])) {
            $merged['title'] = (string)($vars['page']['heading'] ?? config('app.name'));
        }

        if (empty($merged['canonical'])) {
            $merged['canonical'] = request()->fullUrl();
        }

        foreach (['description', 'robots', 'image', 'prev', 'next', 'og', 'twitter'] as $key) {
            $merged[$key] = (string)($merged[$key] ?? '');
        }

        return $merged;
    }

    /**
     * @param array<string, string> $meta
     * @return array<string, string>
     */
    private function buildLayoutMeta(array $meta): array
    {
        $title = $meta['title'];
        $description = $meta['description'];
        $canonical = $meta['canonical'];
        $image = $meta['image'] !== '' ? $meta['image'] : null;

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og' => $meta['og'] !== '' ? $meta['og'] : $this->buildOgMeta($title, $description, $image, $canonical),
            'twitter' => $meta['twitter'] !== '' ? $meta['twitter'] : $this->buildTwitterMeta($title, $description, $image, $canonical),
            'prev' => $meta['prev'],
            'next' => $meta['next'],
            'robots' => $meta['robots'],
        ];
    }

    private function buildOgMeta(string $title, string $description, ?string $image, string $url): string
    {
        $parts = [
            '<meta property="og:type" content="article">',
            '<meta property="og:title" content="' . e($title) . '">',
            '<meta property="og:description" content="' . e($description) . '">',
            '<meta property="og:url" content="' . e($url) . '">',
        ];
        if ($image) {
            $parts[] = '<meta property="og:image" content="' . e($image) . '">';
        }
        return implode("\n", $parts);
    }

    private function buildTwitterMeta(string $title, string $description, ?string $image, string $url): string
    {
        $parts = [
            '<meta property="twitter:card" content="summary_large_image">',
            '<meta property="twitter:title" content="' . e($title) . '">',
            '<meta property="twitter:description" content="' . e($description) . '">',
            '<meta property="twitter:url" content="' . e($url) . '">',
        ];
        if ($image) {
            $parts[] = '<meta property="twitter:image" content="' . e($image) . '">';
        }
        return implode("\n", $parts);
    }
}

