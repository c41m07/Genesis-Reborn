import { readdir, readFile, writeFile } from 'node:fs/promises';
import { basename, extname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = fileURLToPath(new URL('..', import.meta.url));
const iconsDir = resolve(rootDir, 'public/assets/svg/icons');
const spritePath = resolve(rootDir, 'public/assets/svg/sprite.svg');

const entries = await readdir(iconsDir);
const svgFiles = entries.filter((file) => extname(file).toLowerCase() === '.svg').sort();

const symbols = await Promise.all(
    svgFiles.map(async (file) => {
        const raw = await readFile(resolve(iconsDir, file), 'utf8');
        const match = raw.match(/<svg[^>]*viewBox="([^"]+)"[^>]*>([\s\S]*?)<\/svg>/i);
        if (!match) {
            console.warn(`Ignoré: ${file} ne contient pas de balise <svg> complète.`);
            return '';
        }
        const [, viewBox, inner] = match;
        const content = inner
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean)
            .map((line) => `    ${line}`)
            .join('\n');
        const id = `icon-${basename(file, '.svg')}`;
        return `  <symbol id="${id}" viewBox="${viewBox}">\n${content}\n  </symbol>`;
    })
);

const spriteContent = `<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none">\n${symbols
    .filter(Boolean)
    .join('\n')}\n</svg>\n`;

await writeFile(spritePath, spriteContent, 'utf8');
console.log(`Sprite généré: ${svgFiles.length} icône(s).`);
