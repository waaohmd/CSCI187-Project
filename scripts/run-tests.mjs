import { readdir } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = fileURLToPath(new URL('../', import.meta.url));
const mode = process.argv[2] || 'all';
if (!['all', 'unit', 'api', 'syntax'].includes(mode)) {
  throw new Error(`Unknown test mode: ${mode}`);
}

async function filesIn(directory) {
  const entries = await readdir(path.join(root, directory), { withFileTypes: true });
  const files = await Promise.all(entries.map(entry => {
    const relative = path.join(directory, entry.name);
    return entry.isDirectory() ? filesIn(relative) : [relative];
  }));
  return files.flat().sort();
}

function run(arguments_) {
  const result = spawnSync(process.execPath, arguments_, { cwd: root, stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
}

if (mode === 'syntax') {
  for (const directory of ['assets', 'scripts', 'tests']) {
    for (const file of await filesIn(directory)) {
      if (/\.(mjs|js)$/.test(file)) run(['--check', file]);
    }
  }
} else {
  const files = (await filesIn('tests')).filter(file => {
    if (!file.endsWith('.test.mjs')) return false;
    if (mode === 'all') return true;
    const isUnit = file.endsWith('.unit.test.mjs');
    if (mode === 'unit') return isUnit;
    return !isUnit && path.basename(file) !== 'smoke.test.mjs';
  });
  if (files.length === 0) throw new Error(`No test files found for ${mode}`);
  console.log(`Running ${mode} tests: ${files.join(', ')}`);
  // API tests share the data store, so run files sequentially to avoid races.
  run(['--test', '--test-concurrency=1', ...files]);
}
