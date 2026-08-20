// Copies the self-hosted Alpine bundles into public/assets so no page depends
// on a third-party CDN at runtime. Run via `npm run build:js`.
import { copyFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const out = resolve(root, 'public/assets');
await mkdir(out, { recursive: true });

const jobs = [
  ['node_modules/alpinejs/dist/cdn.min.js', 'alpine.min.js'],
  ['node_modules/@alpinejs/collapse/dist/cdn.min.js', 'alpine-collapse.min.js'],
];
for (const [from, to] of jobs) {
  await copyFile(resolve(root, from), resolve(out, to));
  console.log(`copied ${to}`);
}
