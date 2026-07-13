import { readdir, readFile, writeFile } from 'node:fs/promises';
import { join, extname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const themesRoot = join(fileURLToPath(new URL('.', import.meta.url)), '../resources/tpl');
const MINIFY_EXT = new Set(['.css', '.js']);

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
