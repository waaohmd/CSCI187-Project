// Author: Marc Guo
// Unit tests for the walking skeleton's browsing specifications 2, 3, and 5.
// Exercise the same pure function used by the page without HTTP, storage, or DOM mocks.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { filterListings } from '../assets/marketplace.mjs';

// Distractors distinguish name, description, location, category, and condition matches.
const listings = Object.freeze([
  Object.freeze({ id: 'desk', name: 'Oak Desk', description: 'Wide study surface', location: 'West Hall', category: 'Furniture', condition: 'Good', status: 'available' }),
  Object.freeze({ id: 'chair', name: 'Reading Chair', description: 'Fits under a desk', location: 'East Hall', category: 'Furniture', condition: 'Fair', status: 'sold' }),
  Object.freeze({ id: 'lamp', name: 'Study Lamp', description: 'Bright LED light', location: 'North Hall', category: 'Electronics', condition: 'Good', status: 'available' }),
  Object.freeze({ id: 'fridge', name: 'Mini Fridge', description: 'Quiet and compact', location: 'West Hall', category: 'Appliances', condition: 'Like new', status: 'available' })
]);

const cases = [
  { name: 'no filters preserves all listings in their original order', filters: {}, expected: ['desk', 'chair', 'lamp', 'fridge'] },
  { name: 'whitespace search acts as an empty search', filters: { query: '   ' }, expected: ['desk', 'chair', 'lamp', 'fridge'] },
  { name: 'search matches a name ignoring case and surrounding whitespace', filters: { query: '  OAK  ' }, expected: ['desk'] },
  { name: 'search also matches descriptions', filters: { query: 'bright led' }, expected: ['lamp'] },
  { name: 'search also matches pickup locations', filters: { query: 'WEST HALL' }, expected: ['desk', 'fridge'] },
  { name: 'search does not match category text alone', filters: { query: 'Electronics' }, expected: [] },
  { name: 'category excludes matching-condition items in other categories', filters: { category: 'Furniture' }, expected: ['desk', 'chair'] },
  { name: 'condition excludes other conditions in the same category', filters: { condition: 'Good' }, expected: ['desk', 'lamp'] },
  { name: 'category and condition must both match', filters: { category: 'Furniture', condition: 'Good' }, expected: ['desk'] },
  { name: 'search category and condition must all match', filters: { query: 'desk', category: 'Furniture', condition: 'Fair' }, expected: ['chair'] },
  { name: 'a search match cannot bypass a conflicting category', filters: { query: 'oak', category: 'Electronics' }, expected: [] },
  { name: 'unmatched search returns no listings', filters: { query: 'bicycle' }, expected: [] },
  { name: 'sold listings remain visible when they match', filters: { query: 'reading', category: 'Furniture', condition: 'Fair' }, expected: ['chair'] }
];

for (const { name, filters, expected } of cases) {
  test(name, () => {
    assert.deepEqual(filterListings(listings, filters).map(item => item.id), expected);
  });
}

test('an empty marketplace returns no listings', () => {
  assert.deepEqual(filterListings([], { query: 'desk' }), []);
});

test('filtering does not reorder or modify the source array or its listings', () => {
  const before = structuredClone(listings);
  const result = filterListings(listings);
  assert.notStrictEqual(result, listings);
  assert.strictEqual(result[0], listings[0]);
  result.reverse();
  assert.deepEqual(listings, before);
});

test('default options show every listing', () => {
  // Intentional assignment failure: restore the correct expectation after CI turns red.
  assert.deepEqual(filterListings(listings).map(item => item.id), []);
});
