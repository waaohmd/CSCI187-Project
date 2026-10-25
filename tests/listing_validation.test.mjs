// Unit tests for listing validation in api.php.
// Author: Adrian Ho
//
// Verifies the posting specification: a listing with a non-empty name and a
// price >= 0 is created; any invalid listing returns an error and creates nothing.
// Requires the PHP server to be running (see README.md).
import assert from 'node:assert/strict';

const baseUrl = process.env.BASE_URL || 'http://127.0.0.1:8080';
const VALIDATION_FAILED = 422;
const CREATED = 201;

const VALID_LISTING = {
  name: 'Validation test lamp',
  description: 'Temporary listing created by the validation test.',
  photos: [],
  price: 12,
  category: 'Other',
  condition: 'Good',
  location: 'Test Hall'
};

const INVALID_LISTINGS = [
  { label: 'blank name', changes: { name: '   ' }, field: 'name' },
  { label: 'name over 80 characters', changes: { name: 'x'.repeat(81) }, field: 'name' },
  { label: 'missing description', changes: { description: '' }, field: 'description' },
  { label: 'negative price', changes: { price: -1 }, field: 'price' },
  { label: 'non-numeric price', changes: { price: 'free' }, field: 'price' },
  { label: 'price above maximum', changes: { price: 100001 }, field: 'price' },
  { label: 'unknown category', changes: { category: 'Cars' }, field: 'category' },
  { label: 'unknown condition', changes: { condition: 'Broken' }, field: 'condition' },
  { label: 'missing pickup location', changes: { location: '' }, field: 'location' },
  { label: 'more than four photos', changes: { photos: Array(5).fill('assets/desk.svg') }, field: 'photos' }
];

function listingsUrl(id) {
  const url = new URL('/api.php', baseUrl);
  url.searchParams.set('resource', 'listings');
  if (id) url.searchParams.set('id', id);
  return url;
}

async function postListing(listing) {
  const response = await fetch(listingsUrl(), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(listing)
  });
  return { status: response.status, payload: await response.json() };
}

async function countListings() {
  const response = await fetch(listingsUrl());
  const payload = await response.json();
  return payload.data.length;
}

const listingCountBefore = await countListings();

for (const { label, changes, field } of INVALID_LISTINGS) {
  const { status, payload } = await postListing({ ...VALID_LISTING, ...changes });
  assert.equal(status, VALIDATION_FAILED, `Listing with ${label} should be rejected`);
  assert.equal(payload.field, field, `Listing with ${label} should report the ${field} field`);
  assert.ok(payload.error, `Listing with ${label} should include an error message`);
}

assert.equal(await countListings(), listingCountBefore, 'Rejected listings must not be saved');

// A price of exactly 0 is the lower boundary of "price >= 0", so free items must be accepted.
let freeListingId;
try {
  const { status, payload } = await postListing({ ...VALID_LISTING, price: 0 });
  assert.equal(status, CREATED, 'A free listing should be created');
  freeListingId = payload.data.id;
  assert.equal(payload.data.price, 0);
  assert.equal(payload.data.status, 'available');
  assert.equal(await countListings(), listingCountBefore + 1, 'A valid listing must be saved');
} finally {
  if (freeListingId) await fetch(listingsUrl(freeListingId), { method: 'DELETE' });
}

console.log('Listing validation tests passed.');
