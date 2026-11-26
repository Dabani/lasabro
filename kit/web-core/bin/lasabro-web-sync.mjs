#!/usr/bin/env node
// Writes the registry of installed web plugins for a host app.
//   lasabro-web-sync [--app <dir>] [--out <file>] [--frontend react]
// Reads <app>/package.json dependencies, keeps the packages whose package.json has "lasabro": { "plugin", "frontend" },
// and writes <out> (default <app>/src/plugins.generated.ts).
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { discoverWebPlugins, generateRegistrySource } from '../src/sync.ts';

const args = process.argv.slice(2);
const opt = (name, fallback) => { const i = args.indexOf(`--${name}`); return i >= 0 && args[i + 1] ? args[i + 1] : fallback; };

const app = resolve(opt('app', process.cwd()));
const out = resolve(opt('out', join(app, 'src/plugins.generated.ts')));
const frontend = opt('frontend', 'react');

const pkg = JSON.parse(readFileSync(join(app, 'package.json'), 'utf8'));
const deps = Object.keys({ ...pkg.dependencies, ...pkg.devDependencies });
const require = createRequire(join(app, 'package.json'));
const read = (name) => {
  try { return JSON.parse(readFileSync(require.resolve(`${name}/package.json`), 'utf8')); } catch { return undefined; }
};

const plugins = discoverWebPlugins(deps, read, frontend);
mkdirSync(dirname(out), { recursive: true });
writeFileSync(out, generateRegistrySource(plugins));
console.log(`${plugins.length} web plugin(s) -> ${out}`);
for (const p of plugins) console.log(`  ${p.plugin}  ${p.name}`);
