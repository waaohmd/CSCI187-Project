import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = file => readFile(path.join(root, file), 'utf8');

const [html, javascript, php, rawStore] = await Promise.all([
  read('index.php'),
  read('assets/app.js'),
  read('api.php'),
  read('data/store.json')
]);

const store = JSON.parse(rawStore);

for (const field of ['name', 'description', 'photos', 'price', 'category', 'condition']) {
  assert.match(html, new RegExp(`name=["']${field}["']`), `Listing form is missing ${field}`);
}

for (const field of ['from_location', 'to_location', 'date', 'time', 'offer', 'details']) {
  assert.match(html, new RegExp(`name=["']${field}["']`), `Help form is missing ${field}`);
}

for (const behavior of ["method: id ? 'PUT' : 'POST'", "method: 'DELETE'", "action: 'sell'"]) {
  assert.ok(javascript.includes(behavior), `JavaScript is missing behavior: ${behavior}`);
}

for (const method of ["$method === 'GET'", "$method === 'POST'", "$method === 'PUT'", "$method === 'DELETE'"]) {
  assert.ok(php.includes(method), `PHP API is missing method handler: ${method}`);
}

assert.ok(Array.isArray(store.listings), 'Store listings must be an array');
assert.ok(Array.isArray(store.help_posts), 'Store help_posts must be an array');

console.log('Static smoke tests passed.');

