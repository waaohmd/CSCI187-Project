import assert from 'node:assert/strict';

const baseUrl = process.env.BASE_URL || 'http://127.0.0.1:8080';
const uniqueSuffix = Date.now();

function newClient() {
  let cookie = '';

  return async function send(resource, { method = 'GET', id, action, body } = {}) {
    const url = new URL('/api.php', baseUrl);
    url.searchParams.set('resource', resource);
    if (id) url.searchParams.set('id', id);
    if (action) url.searchParams.set('action', action);

    const response = await fetch(url, {
      method,
      headers: { ...(body ? { 'Content-Type': 'application/json' } : {}), ...(cookie ? { Cookie: cookie } : {}) },
      body: body ? JSON.stringify(body) : undefined
    });
    const sessionCookie = response.headers.getSetCookie().findLast(value => value.startsWith('PHPSESSID='));
    if (sessionCookie) cookie = sessionCookie.split(';')[0];
    return { status: response.status, payload: await response.json() };
  };
}

async function expectData(client, resource, options, expectedStatus = 200) {
  const { status, payload } = await client(resource, options);
  assert.equal(status, expectedStatus, `${options?.method ?? 'GET'} ${resource}: ${payload.error ?? status}`);
  return payload.data;
}

async function expectError(client, resource, options, expectedStatus) {
  const { status } = await client(resource, options);
  assert.equal(status, expectedStatus, `${options?.method ?? 'GET'} ${resource} should return ${expectedStatus}`);
}

const registration = name => ({ name, email: `${name}.${uniqueSuffix}@scu.edu`, password: 'integration-test' });
const listingBody = {
  name: 'CI test lamp',
  description: 'Temporary listing created by the integration test.',
  photos: [],
  price: 12,
  category: 'Other',
  condition: 'Good',
  location: 'Test Hall'
};
const helpBody = {
  title: 'CI test delivery',
  type: 'Delivery',
  from_location: 'Test Hall A',
  to_location: 'Test Hall B',
  date: '2026-10-22',
  time: '14:30',
  offer: 15,
  details: 'Temporary request created by the integration test.'
};

const seller = newClient();
const buyer = newClient();
const outsider = newClient();
const visitor = newClient();

assert.equal(await expectData(visitor, 'auth'), null, 'A visitor should not be signed in');
assert.ok(Array.isArray(await expectData(visitor, 'listings')), 'A visitor should browse listings');
await expectError(visitor, 'listings', { method: 'POST', body: listingBody }, 401);
await expectError(visitor, 'conversations', {}, 401);
await expectError(visitor, 'unknown', {}, 404);
await expectError(visitor, 'listings', { method: 'PATCH' }, 405);

await expectError(visitor, 'auth', { method: 'POST', action: 'register', body: { ...registration('nonscu'), email: 'a@gmail.com' } }, 422);
const sellerAccount = await expectData(seller, 'auth', { method: 'POST', action: 'register', body: registration('seller') }, 201);
assert.equal(sellerAccount.verified, true);
await expectError(visitor, 'auth', { method: 'POST', action: 'register', body: registration('seller') }, 409);
await expectError(visitor, 'auth', { method: 'POST', action: 'login', body: { email: sellerAccount.email, password: 'wrong-password' } }, 401);
const buyerAccount = await expectData(buyer, 'auth', { method: 'POST', action: 'register', body: registration('buyer') }, 201);
await expectData(outsider, 'auth', { method: 'POST', action: 'register', body: registration('outsider') }, 201);

assert.equal((await expectData(seller, 'auth')).id, sellerAccount.id, 'A session should remember the user');
await expectData(seller, 'auth', { method: 'POST', action: 'logout' });
assert.equal(await expectData(seller, 'auth'), null, 'Signing out should end the session');
await expectData(seller, 'auth', { method: 'POST', action: 'login', body: { email: sellerAccount.email, password: 'integration-test' } });

const profile = await expectData(seller, 'profile', { method: 'PUT', body: { name: 'CI Seller', residence: 'Test Hall', bio: 'Testing' } });
assert.equal(profile.residence, 'Test Hall');
assert.equal((await expectData(visitor, 'profile', { id: sellerAccount.id })).name, 'CI Seller');
await expectError(visitor, 'profile', { id: 'usr_missing' }, 404);

const initialListings = await expectData(visitor, 'listings');
const listing = await expectData(seller, 'listings', { method: 'POST', body: listingBody }, 201);
assert.equal(listing.owner_id, sellerAccount.id);
assert.equal(listing.owner.verified, true);
assert.equal(listing.status, 'available');
const listingsAfterCreate = await expectData(visitor, 'listings');
assert.equal(listingsAfterCreate.length, initialListings.length + 1, 'Creating must add a listing');
assert.ok(
  initialListings.every(initial => listingsAfterCreate.some(current => current.id === initial.id)),
  'Creating a listing must not replace existing listings'
);
await expectError(seller, 'listings', { method: 'POST', body: { ...listingBody, price: -1 } }, 422);

const updatedListing = await expectData(seller, 'listings', { method: 'PUT', id: listing.id, body: { ...listingBody, price: 10, condition: 'Fair' } });
assert.equal(updatedListing.price, 10);
assert.equal(updatedListing.condition, 'Fair');
await expectError(buyer, 'listings', { method: 'PUT', id: listing.id, body: listingBody }, 403);
await expectError(buyer, 'listings', { method: 'POST', id: listing.id, action: 'sell' }, 403);
await expectError(buyer, 'listings', { method: 'DELETE', id: listing.id }, 403);
await expectError(seller, 'listings', { method: 'DELETE' }, 400);

const help = await expectData(buyer, 'help', { method: 'POST', body: helpBody }, 201);
assert.equal(help.status, 'open');
const updatedHelp = await expectData(buyer, 'help', { method: 'PUT', id: help.id, body: { ...helpBody, to_location: 'Test Hall C', offer: 18 } });
assert.equal(updatedHelp.to_location, 'Test Hall C');
assert.equal(updatedHelp.offer, 18);

const conversation = await expectData(buyer, 'conversations', {
  method: 'POST',
  body: { post_type: 'listing', post_id: listing.id, body: 'Is this available?', dropoff_location: 'Test Hall B' }
}, 201);
assert.equal(conversation.pickup_location, 'Test Hall');
assert.equal(conversation.dropoff_location, 'Test Hall B');
await expectError(seller, 'conversations', { method: 'POST', body: { post_type: 'listing', post_id: listing.id, body: 'Hello' } }, 422);
await expectError(outsider, 'conversations', { id: conversation.id }, 403);

const reply = await expectData(seller, 'conversations', { method: 'POST', id: conversation.id, action: 'message', body: { body: 'Yes, it is.' } }, 201);
assert.equal(reply.messages.length, 2);
assert.equal(reply.other_user.id, buyerAccount.id);
const locations = await expectData(seller, 'conversations', { method: 'PUT', id: conversation.id, body: { pickup_location: 'Lobby', dropoff_location: 'Room 2' } });
assert.equal(locations.pickup_location, 'Lobby');
assert.equal((await expectData(buyer, 'conversations')).some(item => item.id === conversation.id), true);

const moverConversation = await expectData(seller, 'conversations', { method: 'POST', body: { post_type: 'help', post_id: help.id, body: 'I can help.' } }, 201);
assert.equal(moverConversation.dropoff_location, 'Test Hall C');
await expectError(seller, 'help', { method: 'POST', id: help.id, action: 'close' }, 403);
const closedHelp = await expectData(buyer, 'help', { method: 'POST', id: help.id, action: 'close' });
assert.equal(closedHelp.status, 'closed');
await expectError(outsider, 'conversations', { method: 'POST', body: { post_type: 'help', post_id: help.id, body: 'Hello' } }, 409);

const soldListing = await expectData(seller, 'listings', { method: 'POST', id: listing.id, action: 'sell' });
assert.equal(soldListing.status, 'sold');
await expectError(outsider, 'conversations', { method: 'POST', body: { post_type: 'listing', post_id: listing.id, body: 'Hello' } }, 409);

await expectData(seller, 'listings', { method: 'DELETE', id: listing.id });
await expectData(buyer, 'help', { method: 'DELETE', id: help.id });
await expectError(seller, 'listings', { method: 'DELETE', id: listing.id }, 404);

console.log('API integration tests passed.');
