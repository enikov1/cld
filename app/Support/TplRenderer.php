<?php

namespace App\Support;

class TplRenderer
{
    /** @var array<string, string> SEO block tag => meta key */
    private const META_TAGS = [
        'meta-title' => 'title',
        'meta-description' => 'description',
        'meta-canonical' => 'canonical',
        'meta-robots' => 'robots',
        'meta-image' => 'image',
        'meta-prev' => 'prev',
        'meta-next' => 'next',
        'meta-og' => 'og',
        'meta-twitter' => 'twitter',
    ];

    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/\\');
    }

    /**
     * Render template file from resources/tpl (или указанной baseDir).
     *
     * @param string $template path like "home.tpl" or "tpl/home.tpl"
     * @param array<string,mixed> $vars
     */
    public function render(string $template, array $vars = []): string
    {
        $file = $this->resolveTemplateFile($template);
        $content = file_get_contents($file);
        if ($content === false) {
            return '';
        }

        return $this->renderString($content, $vars);
    }

    /**
     * Render page template: extract [meta-*] blocks for SEO, strip them from body HTML.
     *
     * @return array{content: string, meta: array<string, string>}
     */
    public function renderPage(string $template, array $vars = []): array
    {
        $file = $this->resolveTemplateFile($template);
        $content = file_get_contents($file);
        if ($content === false) {
            return ['content' => '', 'meta' => []];
        }

        ['body' => $body, 'directives' => $directives] = $this->extractMetaDirectives($content);
        $html = $this->renderString($body, $vars);

        $meta = [];
        foreach ($directives as $key => $fragment) {
            $rendered = trim($this->renderString($fragment, $vars));
            if ($rendered !== '') {
                $meta[$key] = $rendered;
            }
        }

        return ['content' => $html, 'meta' => $meta];
    }

    /**
     * @return array{body: string, directives: array<string, string>}
     */
    public function extractMetaDirectives(string $tpl): array
    {
        $directives = [];
        $body = $tpl;

        foreach (self::META_TAGS as $tag => $key) {
            $pattern = '/\[' . preg_quote($tag, '/') . '\]([\s\S]*?)\[\/' . preg_quote($tag, '/') . '\]/';
            if (preg_match($pattern, $body, $match)) {
                $directives[$key] = $match[1];
                $body = (string)preg_replace($pattern, '', $body, 1);
            }
        }

        // Unknown meta blocks are stripped from page output but not mapped to layout meta.
        $body = (string)preg_replace('/\[meta-[a-z0-9-]+\][\s\S]*?\[\/meta-[a-z0-9-]+\]/', '', $body);

        return ['body' => ltrim($body, "\r\n"), 'directives' => $directives];
    }

    private function resolveTemplateFile(string $template): string
    {
        $tpl = ltrim($template, '/\\');
        if (!str_ends_with(strtolower($tpl), '.tpl')) {
            $tpl .= '.tpl';
        }

        // Support "partials/foo.tpl" directly under baseDir.
        return $this->baseDir . DIRECTORY_SEPARATOR . $tpl;
    }

    /**
     * Подставляет {переменные} в строку (SEO-поля из админки, заголовки и т.п.).
     *
     * @param array<string, mixed> $vars
     */
    public function interpolate(string $text, array $vars): string
    {
        return $this->renderString($text, $vars);
    }

    private function renderString(string $tpl, array $vars): string
    {
        $out = $tpl;

        // Loops: [loop items] ... [/loop]
        $out = $this->renderLoops($out, $vars);

        // not-blocks first to avoid overlap with positive blocks.
        $out = $this->renderNotBlocks($out, $vars);

        // Conditional blocks: [key] ... [/key]
        $out = $this->renderBlocks($out, $vars);

        // Variable interpolation: {var} (escaped), {var|raw} (unescaped)
        $out = $this->renderVariables($out, $vars);

        return $out;
    }

    private function renderLoops(string $tpl, array $vars): string
    {
        while (preg_match('/\[loop\s+/', $tpl)) {
            $next = $this->renderNextLoop($tpl, $vars);
            if ($next === $tpl) {
                break;
            }
            $tpl = $next;
        }

        return $tpl;
    }

    private function renderNextLoop(string $tpl, array $vars): string
    {
        if (!preg_match('/\[loop\s+([A-Za-z0-9_\.]+)\]/', $tpl, $open, PREG_OFFSET_CAPTURE)) {
            return $tpl;
        }

        $start = $open[0][1];
        $listKey = $open[1][0];
        $pos = $start + strlen($open[0][0]);
        $depth = 1;
        $endPos = null;

        while ($depth > 0 && preg_match('/\[(\/?loop)\b[^\]]*\]/', $tpl, $tag, PREG_OFFSET_CAPTURE, $pos)) {
            if ($tag[1][0] === '/loop') {
                $depth--;
                if ($depth === 0) {
                    $endPos = $tag[0][1] + strlen($tag[0][0]);
                }
            } else {
                $depth++;
            }
            $pos = $tag[0][1] + strlen($tag[0][0]);
        }

        if ($endPos === null) {
            return $tpl;
        }

        $innerStart = $start + strlen($open[0][0]);
        $inner = substr($tpl, $innerStart, $endPos - $innerStart - strlen('[/loop]'));
        $replacement = $this->renderLoopBlock($listKey, $inner, $vars);

        return substr($tpl, 0, $start) . $replacement . substr($tpl, $endPos);
    }

    private function renderLoopBlock(string $listKey, string $inner, array $vars): string
    {
        $list = $this->resolvePath($vars, $listKey);
        if (!is_array($list) || count($list) === 0) {
            return '';
        }

        $acc = '';
        foreach ($list as $item) {
            $ctx = $vars;
            $ctx['item'] = $item;

            $simpleKey = preg_match('/^[A-Za-z0-9_]+$/', $listKey) ? $listKey : null;
            if ($simpleKey) {
                $ctx[$simpleKey] = $item;
            }

            if (is_array($item) && array_key_exists('category', $item)) {
                $ctx['category'] = $item['category'];
            }

            $acc .= $this->renderString($inner, $ctx);
        }

        return $acc;
    }

    private function isEmptyValue(mixed $val): bool
    {
        if ($val === null || $val === false || $val === '') {
            return true;
        }

        // Hide zero ratings like 0 / 0.0 / "0.0" from decimal casts.
        if (is_int($val) || is_float($val) || (is_string($val) && is_numeric($val))) {
            if ((float) $val == 0.0) {
                return true;
            }
        }

        if (is_array($val) && count($val) === 0) {
            return true;
        }

        return false;
    }

    private function renderNotBlocks(string $tpl, array $vars): string
    {
        return (string)preg_replace_callback(
            '/\\[not-([A-Za-z0-9_\\.-]+)\\]([\\s\\S]*?)\\[\\/not-\\1\\]/',
            function (array $m) use ($vars) {
                $key = $m[1];
                $inner = $m[2];
                $val = $this->resolvePath($vars, $key);
                if ($this->isEmptyValue($val)) {
                    return $this->renderString($inner, $vars);
                }
                return '';
            },
            $tpl
        );
    }

    private function renderBlocks(string $tpl, array $vars): string
    {
        return (string)preg_replace_callback(
            '/\\[([A-Za-z0-9_\\.-]+)\\]([\\s\\S]*?)\\[\\/\\1\\]/',
            function (array $m) use ($vars) {
                $key = $m[1];
                $inner = $m[2];
                $val = $this->resolvePath($vars, $key);

                if ($this->isEmptyValue($val)) {
                    return '';
                }
                return $this->renderString($inner, $vars);
            },
            $tpl
        );
    }

    private function renderVariables(string $tpl, array $vars): string
    {
        return (string)preg_replace_callback(
            '/\\{([A-Za-z0-9_\\.\\-]+)(\\|raw)?\\}/',
            function (array $m) use ($vars) {
                $key = $m[1];
                $isRaw = !empty($m[2]) && $m[2] === '|raw';
                $val = $this->resolvePath($vars, $key);
                $val = $val === null ? '' : (string)$val;
                $val = PublicMedia::rewriteInText($val);
                if ($isRaw) {
                    return $val;
                }

                return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            $tpl
        );
    }

    /**
     * Resolve dot-path in arrays / objects.
     */
    private function resolvePath(array $vars, string $path): mixed
    {
        $parts = explode('.', $path);
        $cur = $vars;
        foreach ($parts as $p) {
            if (is_array($cur) && array_key_exists($p, $cur)) {
                $cur = $cur[$p];
                continue;
            }

            if (is_object($cur) && isset($cur->{$p})) {
                $cur = $cur->{$p};
                continue;
            }

            return null;
        }

        return $cur;
    }
}

