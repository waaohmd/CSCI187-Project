import assert from 'node:assert/strict';
import test from 'node:test';
import { savePostInCollection } from '../assets/marketplace.js';

test('creating a listing adds it without replacing an existing listing', () => {
  const existing = [{ id: 'lst_existing', name: 'Desk' }];
  const created = { id: 'lst_created', name: 'Chair' };

  assert.deepEqual(savePostInCollection(existing, created), [created, ...existing]);
});

test('creating a help post adds it without replacing an existing help post', () => {
  const existing = [{ id: 'hlp_existing', title: 'Move a desk' }];
  const created = { id: 'hlp_created', title: 'Deliver a chair' };

  assert.deepEqual(savePostInCollection(existing, created), [created, ...existing]);
});

test('editing a post replaces only the matching post', () => {
  const original = [
    { id: 'lst_first', name: 'Desk' },
    { id: 'lst_second', name: 'Chair' }
  ];
  const edited = { id: 'lst_second', name: 'Desk chair' };

  assert.deepEqual(savePostInCollection(original, edited, edited.id), [original[0], edited]);
});
