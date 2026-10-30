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

function run(command, arguments_) {
  const result = spawnSync(command, arguments_, { cwd: root, stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
}

function runNode(arguments_) {
  run(process.execPath, arguments_);
}

if (mode === 'syntax') {
  for (const directory of ['assets', 'scripts', 'tests']) {
    for (const file of await filesIn(directory)) {
      if (/\.(mjs|js)$/.test(file)) runNode(['--check', file]);
    }
  }
} else {
  const testFiles = await filesIn('tests');
  const javaScriptUnitTests = testFiles.filter(file => file.endsWith('.unit.test.mjs'));
  const phpUnitTests = testFiles.filter(file => file.endsWith('.unit.php'));
  const apiTests = testFiles.filter(file => file.endsWith('.test.mjs') && !file.endsWith('.unit.test.mjs'));

  if (mode === 'all' || mode === 'unit') {
    console.log(`Running unit tests: ${[...javaScriptUnitTests, ...phpUnitTests].join(', ')}`);
    if (javaScriptUnitTests.length > 0) {
      runNode(['--test', ...javaScriptUnitTests]);
    }
    for (const phpUnitTest of phpUnitTests) {
      run('php', [phpUnitTest]);
    }
  }

  if (mode === 'all' || mode === 'api') {
    if (apiTests.length === 0) throw new Error('No API tests found');
    console.log(`Running API tests: ${apiTests.join(', ')}`);
    // API tests share the data store, so run files sequentially to avoid races.
    runNode(['--test', '--test-concurrency=1', ...apiTests]);
  }
}
