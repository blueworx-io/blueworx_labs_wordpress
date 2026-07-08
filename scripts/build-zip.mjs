#!/usr/bin/env node
// Builds the deployment artifact: dist/<slug>.zip containing the plugin folder.
// Removes any existing <slug>*.zip first so only the current one remains.
import { createWriteStream, mkdirSync, readdirSync, rmSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import archiver from 'archiver';

const SLUG = 'blueworx-project-wordpress-labs';
const DIST = 'dist';

// Runtime files/dirs that ship inside the plugin folder.
const INCLUDE = ['blueworx-project-wordpress-labs.php', 'readme.txt', 'includes', 'assets', 'languages'];

mkdirSync(DIST, { recursive: true });
for (const f of readdirSync(DIST)) {
  if (f.toLowerCase().startsWith(SLUG.toLowerCase()) && f.toLowerCase().endsWith('.zip')) {
    rmSync(join(DIST, f));
  }
}

const output = createWriteStream(join(DIST, `${SLUG}.zip`));
const archive = archiver('zip', { zlib: { level: 9 } });

output.on('close', () => console.log(`Built ${DIST}/${SLUG}.zip (${archive.pointer()} bytes).`));
archive.on('warning', (err) => { if (err.code !== 'ENOENT') throw err; });
archive.on('error', (err) => { throw err; });

archive.pipe(output);
for (const entry of INCLUDE) {
  if (!existsSync(entry)) continue; // languages/ is optional
  archive.glob(entry.includes('.') ? entry : `${entry}/**/*`, { dot: false }, { prefix: `${SLUG}/` });
}
await archive.finalize();
