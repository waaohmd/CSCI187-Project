// Author: Brian Wu
// Tests help-post validation in the community marketplace API.
// Temporary posts are deleted so the data store is not changed permanently.

import assert from 'node:assert/strict';

const baseUrl = process.env.BASE_URL || 'http://127.0.0.1:8080';

async function requestHelp(method, data = undefined, id = undefined) {
  const url = new URL('/api.php', baseUrl);
  url.searchParams.set('resource', 'help');
  if (id) url.searchParams.set('id', id);

  const options = { method };
  if (data !== undefined) {
    options.headers = { 'Content-Type': 'application/json' };
    options.body = JSON.stringify(data);
  }

  const response = await fetch(url, options);
  const payload = await response.json();
  return { status: response.status, payload };
}

async function getHelpPosts() {
  const result = await requestHelp('GET');
  assert.equal(result.status, 200, 'GET help posts should succeed');
  assert.ok(Array.isArray(result.payload.data), 'Help posts should be an array');
  return result.payload.data;
}

const validHelp = {
  title: `CI help validation ${Date.now()}`,
  type: 'Delivery',
  from_location: 'West Hall',
  to_location: 'East Hall',
  date: '2026-11-05',
  time: '14:30',
  offer: 15,
  details: 'Please help carry a small box across campus.'
};

const initialCount = (await getHelpPosts()).length;

// Each invalid request must receive HTTP 422 and identify the bad field.
const invalidCases = [
  { name: 'blank title', changes: { title: '   ' }, field: 'title' },
  { name: 'title over 100 characters', changes: { title: 'A'.repeat(101) }, field: 'title' },
  { name: 'unknown help type', changes: { type: 'Cleaning' }, field: 'type' },
  { name: 'blank pickup location', changes: { from_location: '' }, field: 'from_location' },
  { name: 'blank destination', changes: { to_location: '' }, field: 'to_location' },
  { name: 'invalid date format', changes: { date: '11/05/2026' }, field: 'date' },
  { name: 'invalid time format', changes: { time: '2:30 PM' }, field: 'time' },
  { name: 'offer below minimum', changes: { offer: 0 }, field: 'offer' },
  { name: 'offer above maximum', changes: { offer: 10001 }, field: 'offer' },
  { name: 'nonnumeric offer', changes: { offer: 'free' }, field: 'offer' },
  { name: 'blank details', changes: { details: '' }, field: 'details' },
  { name: 'details over 600 characters', changes: { details: 'A'.repeat(601) }, field: 'details' }
];

for (const { name, changes, field } of invalidCases) {
  const result = await requestHelp('POST', { ...validHelp, ...changes });
  assert.equal(
    result.status, 422,
    `${name}: expected HTTP 422, got ${result.status}: ${JSON.stringify(result.payload)}`
  );
  assert.equal(result.payload.field, field, `${name}: wrong validation field`);
  assert.ok(result.payload.error, `${name}: missing error message`);
  console.log(`PASS: ${name} rejected`);
}

assert.equal(
  (await getHelpPosts()).length,
  initialCount,
  'Invalid help posts must not be saved'
);

// Check that both valid offer boundaries (1 and 1000) are accepted.
for (const offer of [1, 10000]) {
  let createdId;
  try {
    const result = await requestHelp('POST', {
      ...validHelp,
      title: `${validHelp.title} - offer ${offer}`,
      offer
    });
    createdId = result.payload?.data?.id;

    assert.equal(
      result.status, 201,
      `Offer ${offer} should be accepted: ${JSON.stringify(result.payload)}`
    );
    assert.ok(createdId, 'New help post should have an ID');
    assert.equal(result.payload.data.offer, offer);
    assert.equal(result.payload.data.status, 'open');

    const posts = await getHelpPosts();
    assert.ok(posts.some(post => post.id === createdId), 'Valid post should be saved');
    console.log(`PASS: valid offer ${offer} accepted and saved`);
  } finally {
    // Clean up even if an assertion fails after the post is created.
    if (createdId) {
      const deleted = await requestHelp('DELETE', undefined, createdId);
      assert.equal(deleted.status, 200, `Cleanup failed for ${createdId}`);
    }
  }
}

assert.equal(
  (await getHelpPosts()).length,
  initialCount,
  'Test must leave the original number of help posts unchanged'
);

assert.fail('Intentional CI failure for assignment');
console.log(`Help validation tests passed: ${invalidCases.length} invalid cases and 2 valid boundary cases.`);