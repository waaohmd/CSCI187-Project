// Unit tests for post ownership and request errors in api.php.
// Author: Abhinav Raghavendra
//
// Verifies the trust and safety specification: a user cannot edit, sell, or
// remove a post they do not own, and every refused request leaves the post
// unchanged. Also checks the 400 and 404 responses for malformed requests.
// Requires the PHP server to be running (see README.md).
import assert from 'node:assert/strict';

const baseUrl = process.env.BASE_URL || 'http://127.0.0.1:8080';
const BAD_REQUEST = 400;
const FORBIDDEN = 403;
const NOT_FOUND = 404;

// Seed posts in data/store.json that belong to other students.
const OTHER_USERS_LISTING_ID = 'lst_1001';
const OTHER_USERS_HELP_ID = 'hlp_2002';
const MISSING_ID = 'lst_does_not_exist';

const LISTING_EDIT = {
  name: 'Hijacked listing',
  description: 'This edit should never be saved.',
  price: 1,
  category: 'Other',
  condition: 'Good',
  location: 'Test Hall'
};

const HELP_EDIT = {
  title: 'Hijacked help post',
  type: 'Delivery',
  from_location: 'Test Hall A',
  to_location: 'Test Hall B',
  date: '2026-11-05',
  time: '14:30',
  offer: 1,
  details: 'This edit should never be saved.'
};

async function request(resource, { method = 'GET', id, action, rawBody } = {}) {
  const url = new URL('/api.php', baseUrl);
  url.searchParams.set('resource', resource);
  if (id) url.searchParams.set('id', id);
  if (action) url.searchParams.set('action', action);

  const response = await fetch(url, {
    method,
    headers: rawBody === undefined ? undefined : { 'Content-Type': 'application/json' },
    body: rawBody
  });
  return { status: response.status, payload: await response.json() };
}

async function findPost(resource, id) {
  const { payload } = await request(resource);
  return payload.data.find(post => post.id === id);
}

async function assertRefused(label, resource, id, options, expectedStatus) {
  const before = await findPost(resource, id);
  const { status, payload } = await request(resource, { id, ...options });
  assert.equal(status, expectedStatus, `${label}: expected HTTP ${expectedStatus}, got ${status}`);
  assert.ok(payload.error, `${label}: missing error message`);
  assert.equal(payload.data, undefined, `${label}: a refused request must not return data`);
  assert.deepEqual(await findPost(resource, id), before, `${label}: the post must not change`);
  console.log(`PASS: ${label}`);
}

const otherListing = await findPost('listings', OTHER_USERS_LISTING_ID);
const otherHelp = await findPost('help', OTHER_USERS_HELP_ID);
assert.ok(otherListing && otherListing.owner !== 'You', `${OTHER_USERS_LISTING_ID} must be another user's listing`);
assert.ok(otherHelp && otherHelp.owner !== 'You', `${OTHER_USERS_HELP_ID} must be another user's help post`);

await assertRefused('edit another user\'s listing', 'listings', OTHER_USERS_LISTING_ID,
  { method: 'PUT', rawBody: JSON.stringify(LISTING_EDIT) }, NOT_FOUND);
await assertRefused('mark another user\'s listing sold', 'listings', OTHER_USERS_LISTING_ID,
  { method: 'POST', action: 'sell' }, FORBIDDEN);
await assertRefused('remove another user\'s listing', 'listings', OTHER_USERS_LISTING_ID,
  { method: 'DELETE' }, FORBIDDEN);
await assertRefused('edit another user\'s help post', 'help', OTHER_USERS_HELP_ID,
  { method: 'PUT', rawBody: JSON.stringify(HELP_EDIT) }, FORBIDDEN);
await assertRefused('remove another user\'s help post', 'help', OTHER_USERS_HELP_ID,
  { method: 'DELETE' }, FORBIDDEN);

// Ownership is checked before the body is parsed, so a malformed edit to someone
// else's post must still be refused as forbidden rather than as bad JSON.
await assertRefused('malformed edit to another user\'s listing', 'listings', OTHER_USERS_LISTING_ID,
  { method: 'PUT', rawBody: '{not json' }, FORBIDDEN);

const missingCases = [
  { label: 'edit a missing listing', options: { method: 'PUT', rawBody: JSON.stringify(LISTING_EDIT) }, status: NOT_FOUND },
  { label: 'sell a missing listing', options: { method: 'POST', action: 'sell' }, status: NOT_FOUND },
  { label: 'remove a missing listing', options: { method: 'DELETE' }, status: NOT_FOUND }
];

for (const { label, options, status: expectedStatus } of missingCases) {
  const { status, payload } = await request('listings', { id: MISSING_ID, ...options });
  assert.equal(status, expectedStatus, `${label}: expected HTTP ${expectedStatus}, got ${status}`);
  assert.ok(payload.error, `${label}: missing error message`);
  console.log(`PASS: ${label}`);
}

const listingCountBefore = (await request('listings')).payload.data.length;

const missingId = await request('listings', { method: 'DELETE' });
assert.equal(missingId.status, BAD_REQUEST, 'A DELETE without an id should be rejected');
assert.ok(missingId.payload.error, 'A DELETE without an id should include an error message');
console.log('PASS: remove without an id');

const unknownResource = await request('payments');
assert.equal(unknownResource.status, NOT_FOUND, 'An unknown resource should return 404');
console.log('PASS: unknown resource');

assert.equal(
  (await request('listings')).payload.data.length,
  listingCountBefore,
  'Rejected requests must not add or remove listings'
);

console.log('Ownership and request error tests passed.');
