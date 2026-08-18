import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const CSS_URL = 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Oswald:wght@600;700;800&display=swap';
const KEEP_SUBSETS = new Set(['cyrillic', 'cyrillic-ext', 'latin', 'latin-ext']);
const fontsDir = join(fileURLToPath(new URL('.', import.meta.url)), '../resources/tpl/default/assets/fonts');

function slugFamily(name) {
    return name.toLowerCase().replace(/\s+/g, '-');
}

async function main() {
    const css = await fetch(CSS_URL, { headers: { 'User-Agent': UA } }).then((r) => r.text());
    const blocks = css.split(/(?=\/\* )/g);
    const faces = [];
    const usedNames = new Map();

    for (const block of blocks) {
        const subset = (block.match(/^\/\* ([^*]+) \*\//) || [])[1]?.trim();
        if (!subset || !KEEP_SUBSETS.has(subset)) {
            continue;
        }

        const family = (block.match(/font-family:\s*'([^']+)'/) || [])[1];
        const weight = (block.match(/font-weight:\s*(\d+)/) || [])[1];
        const unicode = (block.match(/unicode-range:\s*([^;]+);/) || [])[1]?.trim();
        const remote = (block.match(/url\((https:\/\/fonts\.gstatic\.com\/[^)]+)\)/) || [])[1];
        if (!family || !weight || !unicode || !remote) {
            continue;
        }

        const file = `${slugFamily(family)}-${weight}-${subset}.woff2`;
        if (usedNames.has(file)) {
            continue;
        }
        usedNames.set(file, remote);
        faces.push({ family, weight, subset, unicode, file, remote });
    }

    await mkdir(fontsDir, { recursive: true });

    for (const face of faces) {
        const buf = Buffer.from(await fetch(face.remote, { headers: { 'User-Agent': UA } }).then((r) => r.arrayBuffer()));
        await writeFile(join(fontsDir, face.file), buf);
        console.log(`  ${face.file} (${(buf.length / 1024).toFixed(1)} KB)`);
    }

    const outCss = faces.map((face) => `@font-face {
  font-family: '${face.family}';
  font-style: normal;
  font-weight: ${face.weight};
  font-display: swap;
  src: url('fonts/${face.file}') format('woff2');
  unicode-range: ${face.unicode};
}`).join('\n\n') + '\n';

    const cssPath = join(dirname(fontsDir), 'fonts.css');
    await writeFile(cssPath, outCss);
    console.log(`wrote ${faces.length} faces -> fonts.css`);
}

await main();
