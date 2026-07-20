import { readdir, readFile, writeFile, access } from 'node:fs/promises';
import { join, extname, basename, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const themesRoot = join(fileURLToPath(new URL('.', import.meta.url)), '../resources/tpl');
const MINIFY_EXT = new Set(['.css', '.js']);

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

        const ext = extname(name).toLowerCase();
        if (!MINIFY_EXT.has(ext)) {
            continue;
        }

        await minifyFile(join(dir, name));
    }
}

async function main() {
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
