<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ErrorPageRenderer
{
    /**
     * @return array<string, string>
     */
    private static function messages(): array
    {
        return [
            403 => 'У вас нет доступа к этой странице.',
            404 => 'Запрашиваемая страница не найдена или была удалена.',
            419 => 'Сессия истекла. Обновите страницу и попробуйте снова.',
            500 => 'На сервере произошла ошибка. Мы уже работаем над этим.',
            503 => 'Сайт временно недоступен. Попробуйте зайти чуть позже.',
        ];
    }

    public static function response(int $code, ?string $message = null): Response
    {
        $code = in_array($code, [403, 404, 419, 500, 503], true) ? $code : 404;
        $tpl = 'errors/' . $code . '.tpl';
        $baseDir = ThemeManager::activeBaseDir();
        $renderer = new TplRenderer($baseDir);

        if (!is_file($baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $tpl))) {
            $tpl = 'errors/404.tpl';
            $code = 404;
        }

        $titles = [
            403 => 'Доступ запрещён',
            404 => 'Страница не найдена',
            419 => 'Сессия истекла',
            500 => 'Ошибка сервера',
            503 => 'Сайт на обслуживании',
        ];

        $bodyVars = [
            'error_code' => $code,
            'error_title' => $titles[$code] ?? 'Ошибка',
            'error_message' => $message ?: (self::messages()[$code] ?? self::messages()[404]),
        ];

        $common = self::commonVars();
        $vars = array_merge($common, $bodyVars);
        $vars['seo'] = [
            'title' => $bodyVars['error_title'] . ' — ' . ($common['site']['name'] ?? 'LordSerial'),
            'description' => $bodyVars['error_message'],
            'robots' => 'noindex, nofollow',
        ];

        $page = $renderer->renderPage($tpl, $vars);

        $layoutVars = array_merge($common, [
            'content' => $page['content'],
            'header' => $renderer->render('partials/header.tpl', $common),
            'notifications_dropdown' => SiteConfig::bool('notifications_enabled')
                ? $renderer->render('partials/notifications_dropdown.tpl', $common)
                : '',
            'footer' => $renderer->render('partials/footer.tpl', $common),
            'auth_overlay' => $renderer->render('partials/auth_overlay.tpl', $common),
            'meta' => [
                'title' => $page['meta']['title'] ?? $vars['seo']['title'],
                'description' => $page['meta']['description'] ?? $vars['seo']['description'],
                'canonical' => url('/'),
                'og' => '',
                'twitter' => '',
                'prev' => '',
                'next' => '',
                'robots' => $page['meta']['robots'] ?? 'noindex, nofollow',
            ],
        ]);

        $html = $renderer->render('layout.tpl', $layoutVars);

        return response($html, $code)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @return array<string, mixed>
     */
    private static function commonVars(): array
    {
        $user = Auth::user();

        return array_merge([
            'csrf_token' => csrf_token(),
            'search_query' => '',
            'auth' => [
                'logged_in' => (bool)$user,
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
            ],
            'auth_panel' => '',
            'auth_email' => '',
            'auth_notice' => '',
            'reset_token' => '',
            'auth_errors_list' => [],
            'form_errors_list' => [],
            'THEME' => ThemeManager::webPath(),
            'theme' => ThemeManager::assetVars(),
            'site' => [
                'name' => SiteSetting::get('site_name', config('app.name', 'LordSerial')),
                'tagline' => SiteSetting::get('site_tagline', 'Сериалы онлайн'),
                'footer_text' => SiteSetting::get('footer_text', 'Сериалы онлайн в HD качестве'),
                'year' => (string)date('Y'),
            ],
            'has_notifications' => SiteConfig::bool('notifications_enabled'),
            'speedbar_block' => '',
        ], NavMenuBuilder::forTpl(), SiteConfig::forTpl());
    }
}
