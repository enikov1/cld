import { readdir, readFile, writeFile, access, copyFile, mkdir } from 'node:fs/promises';
import { join, extname, basename, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const themesRoot = join(fileURLToPath(new URL('.', import.meta.url)), '../resources/tpl');
const MINIFY_EXT = new Set(['.css', '.js']);

// Engine functional layer omrotpl: source — resources/omrotpl/, artifact — public/omrotpl/.
const omroSourceDir = join(themesRoot, '../omrotpl');
const omroOutDir = join(themesRoot, '../../public/omrotpl');

/**
 * data-omro-* contract layer: maps theme-independent attributes
 * to engine hooks. Runs BEFORE module initialization (its DOMContentLoaded
 * handler is registered first in the assembled file).
 */
const OMRO_CONTRACT_LAYER = `/* ══ OmroTPL contract: data-omro-* → engine hooks ═══════════════════════
   Themes use the data-omro-* attributes instead of engine classes.        */
(function () {
  'use strict';

  var qsa = function (sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); };
  var mark = function (sel, hook) {
    qsa(sel).forEach(function (el) { el.classList.add(hook); });
  };
  var markId = function (el, id) { if (!el.id) { el.id = id; } };

  document.addEventListener('DOMContentLoaded', function () {
    // Authorization modal
    qsa('[data-omro-modal="auth"]').forEach(function (el) { markId(el, 'loginOverlay'); });
    mark('[data-omro-modal-open]', 'js-login-open');
    mark('[data-omro-modal-close]', 'js-login-close');
    mark('[data-omro-form="login"]', 'login-form');
    mark('[data-omro-form-switch]', 'js-auth-switch');
    qsa('[data-omro-reset-email]').forEach(function (el) { markId(el, 'resetEmailField'); });
    qsa('[data-omro-reset-token]').forEach(function (el) { markId(el, 'resetTokenField'); });

    // Search
    mark('[data-omro-search]', 'ls-search');
    mark('[data-omro-search-panel]', 'ls-search__panel');
    mark('[data-omro-search-loading]', 'ls-search__loading');
    mark('[data-omro-search-open]', 'js-header-search-open');
    mark('[data-omro-search-close]', 'js-header-search-close');
    mark('[data-omro-search-input]', 'js-quick-search');

    // Mobile menu and theme
    qsa('[data-omro-menu]').forEach(function (el) { markId(el, 'lsMobileMenu'); el.classList.add('ls-mobile-overlay'); });
    mark('[data-omro-menu-open]', 'js-mobile-menu-open');
    mark('[data-omro-menu-close]', 'js-mobile-menu-close');
    mark('[data-omro-theme-toggle]', 'js-theme-toggle');

    // Texts: description trimming, details, comment spoilers
    mark('[data-omro-slice]', 'slice-this');
    mark('[data-omro-details-toggle]', 'serial-details__toggle');
    mark('[data-omro-spoiler]', 'comment-spoiler__text');

    // Subscription module (variant → element id)
    var NOTIFY_IDS = {
      modal: 'notifyOverlay',
      open: 'notifyOpenBtn',
      form: 'notifyForm',
      feedback: 'notifyFeedback',
      badge: 'notifySubscribedBadge',
      unsubscribe: 'notifyUnsubscribeBtn',
      subscribed: 'notifySubscribeFeedback',
      box: 'seriesSubscribeBox',
      bell: 'headerNotifyBtn',
      count: 'headerNotifyCount',
      clear: 'clearAllNotifi'
    };
    qsa('[data-omro-notify]').forEach(function (el) {
      var id = NOTIFY_IDS[el.getAttribute('data-omro-notify')];
      if (id) { markId(el, id); }
    });
    mark('[data-omro-notify-item]', 'series-item');
  });
})();
`;

/**
 * omrotpl.js = contract layer + engine script (site.js source) with
 * renaming of internal events lordserial:* → omro:*.
 */
async function buildOmrotpl() {
    await mkdir(omroOutDir, { recursive: true });

    const seedPath = join(omroSourceDir, 'omrotpl.js');
    if (!(await fileExists(seedPath))) {
        // First migration: the source of the engine script — the site.js of the standard theme.
        const legacySource = join(themesRoot, 'default/assets/site.js');
        if (!(await fileExists(legacySource))) {
            console.log('  skip omrotpl (no source)');
            return;
        }
        await writeFile(seedPath, await readFile(legacySource, 'utf8'));
        console.log('  omrotpl.js source migrated from the standard theme');
    }

    const source = await readFile(seedPath, 'utf8');
    const js = OMRO_CONTRACT_LAYER + '\n' + source.replaceAll('lordserial:', 'omro:');

    const outJs = join(omroOutDir, 'omrotpl.js');
    await writeFile(outJs, js);
    console.log(`  omrotpl.js generated (${(js.length / 1024).toFixed(1)} KB)`);
    await minifyFile(outJs);

    const srcCss = join(omroSourceDir, 'omrotpl.css');
    if (await fileExists(srcCss)) {
        const outCss = join(omroOutDir, 'omrotpl.css');
        await copyFile(srcCss, outCss);
        await minifyFile(outCss);
    }
}

/** ES-module entries → bundled IIFE in assets/ (tree-shaken deps like Swiper modules). */
const THEME_MODULE_BUNDLES = [
    {
        theme: 'default',
        entry: 'modules/home-carousels.js',
        outfile: 'assets/home-carousels.js',
    },
];

async function fileExists(path) {
    try {
        await access(path);
        return true;
    } catch {
        return false;
    }
}

async function minifyFile(filePath) {
    const ext = extname(filePath).toLowerCase();
    const loader = ext === '.css' ? 'css' : 'js';
    const source = await readFile(filePath, 'utf8');
    const result = await esbuild.transform(source, {
        loader,
        minify: true,
        legalComments: 'none',
    });
    const outPath = filePath.replace(/\.(css|js)$/i, '.min.$1');
    await writeFile(outPath, result.code);
    const savings = source.length > 0
        ? ((1 - result.code.length / source.length) * 100).toFixed(1)
        : '0.0';

    console.log(`  ${basename(filePath)} -> ${basename(outPath)} (-${savings}%)`);
}

async function bundleThemeModules() {
    for (const item of THEME_MODULE_BUNDLES) {
        const themeDir = join(themesRoot, item.theme);
        const entryPath = join(themeDir, item.entry);
        const outPath = join(themeDir, item.outfile);

        if (!(await fileExists(entryPath))) {
            console.log(`  skip bundle (missing): ${item.theme}/${item.entry}`);
            continue;
        }

        await esbuild.build({
            entryPoints: [entryPath],
            outfile: outPath,
            bundle: true,
            format: 'iife',
            platform: 'browser',
            target: ['es2018'],
            // Pull only imported Swiper modules + their CSS into the bundle.
            loader: { '.css': 'css' },
            logLevel: 'warning',
        });

        const source = await readFile(outPath, 'utf8');
        console.log(`  bundle ${item.entry} -> ${item.outfile} (${(source.length / 1024).toFixed(1)} KB)`);
    }
}

async function processAssetsDir(dir) {
    const entries = await readdir(dir, { withFileTypes: true });

    for (const entry of entries) {
        if (!entry.isFile()) {
            continue;
        }

        const name = entry.name;
        if (/\.min\.(css|js)$/i.test(name)) {
            continue;
        }
        // esbuild unquotes `font-family: 'Open Sans'` and breaks the stack.
        if (name === 'fonts.css') {
            continue;
        }

        const ext = extname(name).toLowerCase();
        if (!MINIFY_EXT.has(ext)) {
            continue;
        }

        await minifyFile(join(dir, name));
    }
}

async function main() {
    console.log('Building omrotpl engine assets...');
    await buildOmrotpl();

    console.log('Bundling theme ES modules...');
    await bundleThemeModules();

    const themes = await readdir(themesRoot, { withFileTypes: true });
    let processed = 0;

    for (const theme of themes) {
        if (!theme.isDirectory()) {
            continue;
        }

        const assetsDir = join(themesRoot, theme.name, 'assets');
        try {
            await readdir(assetsDir);
        } catch {
            continue;
        }

        console.log(`Theme: ${theme.name}`);
        await processAssetsDir(assetsDir);
        processed++;
    }

    if (processed === 0) {
        console.log('No theme assets found.');
    }
}

main().catch((error) => {
    console.error(error);
    process.exit(1);
});
