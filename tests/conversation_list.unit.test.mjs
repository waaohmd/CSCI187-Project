import assert from 'node:assert/strict';
import test from 'node:test';
import { canRespondToPost, lastMessagePreview, postsOwnedBy, saveConversationInList } from '../assets/marketplace.js';

const user = { id: 'usr_current' };

test('a user can respond to another student\'s available post', () => {
  assert.equal(canRespondToPost({ owner_id: 'usr_other', status: 'available' }, user), true);
});

test('a user cannot respond to their own post', () => {
  assert.equal(canRespondToPost({ owner_id: 'usr_current', status: 'available' }, user), false);
});

test('nobody can respond to a sold listing', () => {
  assert.equal(canRespondToPost({ owner_id: 'usr_other', status: 'sold' }, user), false);
});

test('a signed-out visitor sees the respond action so it can prompt sign-in', () => {
  assert.equal(canRespondToPost({ owner_id: 'usr_other', status: 'open' }, null), true);
});

test('my posts contains only the signed-in user\'s posts', () => {
  const posts = [{ id: 'mine', owner_id: 'usr_current' }, { id: 'other', owner_id: 'usr_other' }];

  assert.deepEqual(postsOwnedBy(posts, 'usr_current').map(post => post.id), ['mine']);
  assert.deepEqual(postsOwnedBy([], 'usr_current'), []);
});

test('a new conversation goes to the top of the list', () => {
  const existing = [{ id: 'cnv_existing' }];
  const created = { id: 'cnv_created' };

  assert.deepEqual(saveConversationInList(existing, created), [created, ...existing]);
});

test('an updated conversation moves to the top without being duplicated', () => {
  const conversations = [{ id: 'cnv_first' }, { id: 'cnv_second', messages: [] }];
  const updated = { id: 'cnv_second', messages: [{ body: 'Hello' }] };

  assert.deepEqual(saveConversationInList(conversations, updated), [updated, conversations[0]]);
});

test('the preview shows the latest message', () => {
  assert.equal(lastMessagePreview({ messages: [{ body: 'First' }, { body: 'Latest' }] }), 'Latest');
});

test('the preview handles a conversation without messages', () => {
  assert.equal(lastMessagePreview({ messages: [] }), 'No messages yet.');
});
