import assert from 'node:assert/strict';

const baseUrl = process.env.BASE_URL || 'http://127.0.0.1:8080';

async function request(resource, { method = 'GET', id, action, body } = {}) {
  const url = new URL('/api.php', baseUrl);
  url.searchParams.set('resource', resource);
  if (id) url.searchParams.set('id', id);
  if (action) url.searchParams.set('action', action);

  const response = await fetch(url, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : undefined,
    body: body ? JSON.stringify(body) : undefined
  });
  const payload = await response.json();
  assert.ok(response.ok, `${method} ${url.pathname}${url.search} failed: ${payload.error || response.status}`);
  return payload.data;
}

const initialListings = await request('listings');
assert.ok(Array.isArray(initialListings), 'Listings endpoint must return an array');
const initialHelpPosts = await request('help');
assert.ok(Array.isArray(initialHelpPosts), 'Help endpoint must return an array');

let listingId;
let helpId;

try {
  const listing = await request('listings', {
    method: 'POST',
    body: {
      name: 'CI test lamp',
      description: 'Temporary listing created by the integration test.',
      photos: [],
      price: 12,
      category: 'Other',
      condition: 'Good',
      location: 'Test Hall'
    }
  });
  listingId = listing.id;
  assert.equal(listing.owner, 'You');
  assert.equal(listing.status, 'available');
  const listingsAfterCreate = await request('listings');
  assert.equal(listingsAfterCreate.length, initialListings.length + 1, 'Creating must add a listing');
  assert.ok(
    initialListings.every(initial => listingsAfterCreate.some(current => current.id === initial.id)),
    'Creating a listing must not replace existing listings'
  );

  const updatedListing = await request('listings', {
    method: 'PUT',
    id: listingId,
    body: {
      name: 'CI test lamp',
      description: 'Updated temporary listing.',
      photos: [],
      price: 10,
      category: 'Other',
      condition: 'Fair',
      location: 'Test Hall'
    }
  });
  assert.equal(updatedListing.price, 10);
  assert.equal(updatedListing.condition, 'Fair');

  const soldListing = await request('listings', { method: 'POST', id: listingId, action: 'sell' });
  assert.equal(soldListing.status, 'sold');

  const help = await request('help', {
    method: 'POST',
    body: {
      title: 'CI test delivery',
      type: 'Delivery',
      from_location: 'Test Hall A',
      to_location: 'Test Hall B',
      date: '2026-10-22',
      time: '14:30',
      offer: 15,
      details: 'Temporary request created by the integration test.'
    }
  });
  helpId = help.id;
  assert.equal(help.status, 'open');
  const helpPostsAfterCreate = await request('help');
  assert.equal(helpPostsAfterCreate.length, initialHelpPosts.length + 1, 'Creating must add a help post');
  assert.ok(
    initialHelpPosts.every(initial => helpPostsAfterCreate.some(current => current.id === initial.id)),
    'Creating a help post must not replace existing help posts'
  );

  const updatedHelp = await request('help', {
    method: 'PUT',
    id: helpId,
    body: {
      title: 'Updated CI test delivery',
      type: 'Delivery',
      from_location: 'Test Hall A',
      to_location: 'Test Hall C',
      date: '2026-10-22',
      time: '15:00',
      offer: 18,
      details: 'Updated temporary request.'
    }
  });
  assert.equal(updatedHelp.to_location, 'Test Hall C');
  assert.equal(updatedHelp.offer, 18);
} finally {
  if (listingId) await request('listings', { method: 'DELETE', id: listingId });
  if (helpId) await request('help', { method: 'DELETE', id: helpId });
}

console.log('API integration tests passed.');

