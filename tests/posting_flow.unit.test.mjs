import assert from 'node:assert/strict';
import test from 'node:test';
import { fillPostForm, savePostInCollection } from '../assets/marketplace.js';

// Like a real form, reset() clears visible fields but leaves the hidden id unchanged.
function fakePostForm() {
  const elements = { id: { value: '' }, name: { value: '' }, photos: { value: '' } };
  return { elements, reset() { elements.name.value = ''; elements.photos.value = ''; } };
}

test('editing a post fills the form with its id and fields', () => {
  const form = fakePostForm();

  fillPostForm(form, { id: 'lst_existing', name: 'Desk', photos: ['assets/desk.svg'], status: 'available' });

  assert.equal(form.elements.id.value, 'lst_existing');
  assert.equal(form.elements.name.value, 'Desk');
  assert.equal(form.elements.photos.value, '');
});

test('opening a new post form after editing clears the edited post id', () => {
  const form = fakePostForm();
  fillPostForm(form, { id: 'lst_existing', name: 'Desk' });

  fillPostForm(form);

  assert.equal(form.elements.id.value, '');
  assert.equal(form.elements.name.value, '');
});

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
