// Author: Abhinav Raghavendra
import assert from 'node:assert/strict';
import test from 'node:test';
import { canRespondToPost, lastMessagePreview, messageSenderName, openHelpPosts, postsOwnedBy, saveConversationInList } from '../assets/marketplace.js';

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

test('nobody can respond to a closed help post', () => {
  assert.equal(canRespondToPost({ owner_id: 'usr_other', status: 'closed' }, user), false);
});

test('the Moving help page leaves out closed help posts', () => {
  const posts = [{ id: 'hlp_open', status: 'open' }, { id: 'hlp_closed', status: 'closed' }];

  assert.deepEqual(openHelpPosts(posts).map(post => post.id), ['hlp_open']);
  assert.deepEqual(openHelpPosts([]), []);
});

test('a message shows You for the signed-in user and a name for the other user', () => {
  const conversation = { other_user: { name: 'Hue N.' } };

  assert.equal(messageSenderName({ sender_id: 'usr_current' }, conversation, 'usr_current'), 'You');
  assert.equal(messageSenderName({ sender_id: 'usr_other' }, conversation, 'usr_current'), 'Hue N.');
});

test('a message still has a sender label when the other user no longer exists', () => {
  assert.equal(messageSenderName({ sender_id: 'usr_gone' }, { other_user: null }, 'usr_current'), 'Unknown student');
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
