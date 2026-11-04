import { canRespondToPost, fillPostForm, filterListings, lastMessagePreview, postsOwnedBy, saveConversationInList, savePostInCollection } from './marketplace.js';

(() => {
  'use strict';

  const CONVERSATION_REFRESH_MS = 5000;
  const SIGNED_IN_VIEWS = ['mine', 'messages', 'profile'];
  const state = { user: null, listings: [], help_posts: [], conversations: [], activeConversationId: '', pendingResponse: null, view: 'marketplace', photos: [], pendingDelete: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

  const elements = {
    title: $('#page-title'), description: $('#page-description'), dialog: $('#post-dialog'),
    listingForm: $('#listing-form'), helpForm: $('#help-form'), formTitle: $('#form-title'),
    listingGrid: $('#listing-grid'), helpList: $('#help-list'), myListings: $('#my-listings'),
    myHelp: $('#my-help'), summary: $('#mine-summary'), empty: $('#empty-state'),
    search: $('#search'), category: $('#category-filter'), condition: $('#condition-filter'),
    photoInput: $('#item-photos'), photoPreview: $('#photo-preview'), toast: $('#toast'),
    confirmOverlay: $('#confirm-overlay'), authDialog: $('#auth-dialog'), authTitle: $('#auth-title'),
    loginForm: $('#login-form'), registerForm: $('#register-form'), accountName: $('#account-name'),
    signIn: $('#sign-in-button'), signOut: $('#sign-out-button'), detailDialog: $('#detail-dialog'),
    detailTitle: $('#detail-title'), detailBody: $('#detail-body'), respondDialog: $('#respond-dialog'),
    respondTitle: $('#respond-title'), respondForm: $('#respond-form'), respondDropoff: $('#respond-dropoff'),
    conversationList: $('#conversation-list'), conversationPanel: $('#conversation-panel'),
    conversationHeading: $('#conversation-heading'), locationsForm: $('#locations-form'),
    messageList: $('#message-list'), messageForm: $('#message-form'),
    profileForm: $('#profile-form'), profileStatus: $('#profile-status')
  };

  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
  })[character]);

  const safePhoto = value => {
    const photo = String(value || '');
    return /^(assets\/|data:image\/(png|jpeg|webp);base64,)/.test(photo) ? photo : 'assets/mini-fridge.svg';
  };

  const money = value => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(Number(value) || 0);
  const dateTime = (date, time) => {
    const parsed = new Date(`${date}T${time || '12:00'}`);
    return Number.isNaN(parsed.getTime()) ? `${date} ${time}` : parsed.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  };

  async function request(resource, { method = 'GET', id = '', action = '', data } = {}) {
    const query = new URLSearchParams({ resource });
    if (id) query.set('id', id);
    if (action) query.set('action', action);
    const response = await fetch(`api.php?${query}`, {
      method,
      headers: data ? { 'Content-Type': 'application/json' } : {},
      body: data ? JSON.stringify(data) : undefined
    });
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) throw new Error('The PHP server is not running.');
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'The request failed.');
    return payload.data;
  }

  async function loadConversations() {
    state.conversations = state.user ? await request('conversations') : [];
  }

  async function loadData() {
    const [user, listings, help] = await Promise.all([request('auth'), request('listings'), request('help')]);
    state.user = user; state.listings = listings; state.help_posts = help;
    await loadConversations();
    renderAccount(); renderAll();
  }

  const verifiedBadge = user => user?.verified
    ? '<span class="badge verified">Verified SCU student</span>'
    : '<span class="badge unverified">Not verified</span>';

  function listingCard(item) {
    const photo = item.photos?.[0] || 'assets/mini-fridge.svg';
    const sold = item.status === 'sold';
    return `<article class="item-card">
      <img class="item-photo" src="${escapeHtml(safePhoto(photo))}" alt="${escapeHtml(item.name)}">
      <div class="item-body">
        <div class="card-line"><h2>${escapeHtml(item.name)}</h2><span class="price">${money(item.price)}</span></div>
        <div><span class="badge">${escapeHtml(item.category)}</span><span class="badge">${escapeHtml(item.condition)}</span>${sold ? '<span class="badge sold">Sold</span>' : ''}</div>
        <p class="description">${escapeHtml(item.description)}</p>
        <p class="meta">Pickup: ${escapeHtml(item.location)} · Seller: ${escapeHtml(item.owner?.name)}</p>
        <div>${verifiedBadge(item.owner)}</div>
        <div class="post-actions"><button class="small-button" data-action="details" data-resource="listings" data-id="${escapeHtml(item.id)}">View details</button></div>
      </div>
    </article>`;
  }

  function helpCard(post) {
    return `<article class="help-card">
      <div><h2>${escapeHtml(post.title)}</h2><span class="badge">${escapeHtml(post.type)}</span>
        <p class="route"><strong>Pickup:</strong> ${escapeHtml(post.from_location)}<br><strong>Drop-off:</strong> ${escapeHtml(post.to_location)}</p>
        <p>${escapeHtml(post.details)}</p><p class="meta">${dateTime(post.date, post.time)} · Posted by ${escapeHtml(post.owner?.name)}</p>
        <div>${verifiedBadge(post.owner)}</div>
        ${canRespondToPost(post, state.user) ? `<div class="post-actions"><button class="small-button" data-action="respond" data-resource="help" data-id="${escapeHtml(post.id)}">Respond</button></div>` : ''}
      </div><div class="offer">${money(post.offer)}</div>
    </article>`;
  }

  function actionButtons(resource, id, canSell) {
    return `<div class="post-actions">
      <button class="small-button" data-action="edit" data-resource="${resource}" data-id="${escapeHtml(id)}">Edit</button>
      ${canSell ? `<button class="small-button" data-action="sold" data-resource="listings" data-id="${escapeHtml(id)}">Mark sold</button>` : ''}
      <button class="small-button danger-link" data-action="delete" data-resource="${resource}" data-id="${escapeHtml(id)}">Remove</button>
    </div>`;
  }

  function myListingRow(item) {
    return `<article class="post-row"><div class="card-line"><h3>${escapeHtml(item.name)}</h3><strong>${money(item.price)}</strong></div>
      <div><span class="badge">${escapeHtml(item.condition)}</span><span class="badge ${item.status === 'sold' ? 'sold' : ''}">${escapeHtml(item.status)}</span></div>
      ${actionButtons('listings', item.id, item.status !== 'sold')}</article>`;
  }

  function myHelpRow(item) {
    return `<article class="post-row"><div class="card-line"><h3>${escapeHtml(item.title)}</h3><strong>${money(item.offer)}</strong></div>
      <p class="meta">${dateTime(item.date, item.time)} · ${escapeHtml(item.from_location)} → ${escapeHtml(item.to_location)}</p>
      ${actionButtons('help', item.id, false)}</article>`;
  }

  function renderMarketplace() {
    const items = filterListings(state.listings, {
      query: elements.search.value,
      category: elements.category.value,
      condition: elements.condition.value
    });
    elements.listingGrid.innerHTML = items.map(listingCard).join('');
    toggleEmpty(items.length === 0);
  }

  function renderHelp() {
    elements.helpList.innerHTML = state.help_posts.map(helpCard).join('');
    toggleEmpty(state.help_posts.length === 0);
  }

  function renderMine() {
    const listings = postsOwnedBy(state.listings, state.user.id);
    const help = postsOwnedBy(state.help_posts, state.user.id);
    const sold = listings.filter(item => item.status === 'sold').length;
    elements.summary.innerHTML = `<div><strong>${listings.length}</strong><span>Listings</span></div><div><strong>${sold}</strong><span>Sold</span></div><div><strong>${help.length}</strong><span>Help posts</span></div>`;
    elements.myListings.innerHTML = listings.map(myListingRow).join('') || '<p>No listings yet.</p>';
    elements.myHelp.innerHTML = help.map(myHelpRow).join('') || '<p>No help posts yet.</p>';
    toggleEmpty(false);
  }

  function conversationRow(conversation) {
    const active = conversation.id === state.activeConversationId ? ' active' : '';
    return `<button class="post-row conversation-row${active}" data-conversation="${escapeHtml(conversation.id)}">
      <strong>${escapeHtml(conversation.post_title)}</strong>
      <p class="meta">With ${escapeHtml(conversation.other_user?.name)}</p>
      <p class="meta">${escapeHtml(lastMessagePreview(conversation))}</p>
    </button>`;
  }

  function messageBubble(message) {
    const mine = message.sender_id === state.user.id ? ' mine' : '';
    return `<div class="message${mine}"><p>${escapeHtml(message.body)}</p></div>`;
  }

  function renderActiveConversation() {
    const conversation = state.conversations.find(item => item.id === state.activeConversationId);
    elements.conversationPanel.hidden = !conversation;
    if (!conversation) return;
    elements.conversationHeading.innerHTML = `<h2>${escapeHtml(conversation.post_title)}</h2><p class="meta">With ${escapeHtml(conversation.other_user?.name)}</p>${verifiedBadge(conversation.other_user)}`;
    elements.messageList.innerHTML = conversation.messages.map(messageBubble).join('');
    elements.messageList.scrollTop = elements.messageList.scrollHeight;
    if (elements.locationsForm.contains(document.activeElement)) return;
    elements.locationsForm.elements.pickup_location.value = conversation.pickup_location;
    elements.locationsForm.elements.dropoff_location.value = conversation.dropoff_location;
  }

  function renderMessages() {
    elements.conversationList.innerHTML = state.conversations.map(conversationRow).join('') || '<p>No conversations yet. Respond to a listing or help post to start one.</p>';
    renderActiveConversation();
    toggleEmpty(false);
  }

  function renderProfile() {
    const form = elements.profileForm;
    elements.profileStatus.innerHTML = `<p class="meta">${escapeHtml(state.user.email)}</p>${verifiedBadge(state.user)}`;
    ['name', 'residence', 'bio'].forEach(field => { form.elements[field].value = state.user[field]; });
    toggleEmpty(false);
  }

  function renderAccount() {
    const signedIn = Boolean(state.user);
    elements.signIn.hidden = signedIn; elements.signOut.hidden = !signedIn; elements.accountName.hidden = !signedIn;
    elements.accountName.textContent = state.user?.name ?? '';
  }

  function renderAll() {
    if (state.view === 'marketplace') renderMarketplace();
    if (state.view === 'help') renderHelp();
    if (state.view === 'mine') renderMine();
    if (state.view === 'messages') renderMessages();
    if (state.view === 'profile') renderProfile();
  }

  function setView(view) {
    if (SIGNED_IN_VIEWS.includes(view) && !state.user) { openAuth(); return; }
    state.view = view;
    $$('.view-panel').forEach(panel => panel.hidden = panel.id !== `${view}-view`);
    $$('.nav-button').forEach(button => button.classList.toggle('active', button.dataset.view === view));
    const copy = {
      marketplace: ['Items for sale', 'Buy used items from students nearby.'],
      help: ['Moving and delivery help', 'Post a route and cash offer for another student.'],
      mine: ['My posts', 'Edit, remove, or mark your listings as sold.'],
      messages: ['Messages', 'Chat with buyers, sellers, and movers, and agree on locations.'],
      profile: ['My profile', 'Other students see your name, residence, bio, and verification status.']
    }[view];
    elements.title.textContent = copy[0]; elements.description.textContent = copy[1];
    renderAll();
  }

  function toggleEmpty(show) { elements.empty.hidden = !show; }

  function openForm(type, item = null) {
    if (!state.user) { openAuth(); return; }
    const listing = type === 'listing';
    elements.listingForm.hidden = !listing; elements.helpForm.hidden = listing;
    elements.formTitle.textContent = item ? (listing ? 'Edit listing' : 'Edit help post') : (listing ? 'List an item' : 'Post moving help');
    const form = listing ? elements.listingForm : elements.helpForm;
    fillPostForm(form, item);
    if (listing) {
      state.photos = [...(item?.photos || [])]; renderPhotoPreview();
      $('#listing-submit').textContent = item ? 'Save changes' : 'Publish listing';
    } else {
      $('#help-submit').textContent = item ? 'Save changes' : 'Post request';
      if (!item) form.elements.date.min = new Date().toISOString().slice(0, 10);
    }
    elements.dialog.showModal();
  }

  function closeDialog() { elements.dialog.close(); }

  function setAuthMode(mode) {
    const registering = mode === 'register';
    elements.loginForm.hidden = registering; elements.registerForm.hidden = !registering;
    elements.authTitle.textContent = registering ? 'Create your account' : 'Sign in';
  }

  function openAuth() {
    setAuthMode('login'); elements.loginForm.reset(); elements.registerForm.reset();
    elements.authDialog.showModal();
  }

  async function completeSignIn(user, message) {
    state.user = user;
    await loadConversations();
    elements.authDialog.close(); renderAccount(); renderAll(); showToast(message);
  }

  async function submitLogin(event) {
    event.preventDefault();
    try {
      const user = await request('auth', { method: 'POST', action: 'login', data: formObject(elements.loginForm) });
      await completeSignIn(user, `Welcome back, ${user.name}.`);
    } catch (error) { showToast(error.message, true); }
  }

  async function submitRegistration(event) {
    event.preventDefault();
    try {
      const user = await request('auth', { method: 'POST', action: 'register', data: formObject(elements.registerForm) });
      await completeSignIn(user, 'Your account is ready.');
    } catch (error) { showToast(error.message, true); }
  }

  async function signOut() {
    try {
      await request('auth', { method: 'POST', action: 'logout' });
      state.user = null; state.conversations = []; state.activeConversationId = '';
      renderAccount(); setView('marketplace'); showToast('You are signed out.');
    } catch (error) { showToast(error.message, true); }
  }

  async function submitProfile(event) {
    event.preventDefault();
    try {
      state.user = await request('profile', { method: 'PUT', data: formObject(elements.profileForm) });
      [state.listings, state.help_posts] = await Promise.all([request('listings'), request('help')]);
      renderAccount(); renderAll(); showToast('Profile saved.');
    } catch (error) { showToast(error.message, true); }
  }

  function openListingDetails(item) {
    const seller = item.owner;
    const photos = (item.photos?.length ? item.photos : ['assets/mini-fridge.svg'])
      .map(photo => `<img src="${escapeHtml(safePhoto(photo))}" alt="${escapeHtml(item.name)}">`).join('');
    elements.detailTitle.textContent = item.name;
    elements.detailBody.innerHTML = `<div class="detail-photos">${photos}</div>
      <div class="card-line"><div><span class="badge">${escapeHtml(item.category)}</span><span class="badge">${escapeHtml(item.condition)}</span>${item.status === 'sold' ? '<span class="badge sold">Sold</span>' : ''}</div><span class="price">${money(item.price)}</span></div>
      <p class="detail-description">${escapeHtml(item.description)}</p>
      <p class="meta">Pickup: ${escapeHtml(item.location)}</p>
      <div class="seller-card"><strong>${escapeHtml(seller?.name)}</strong> ${verifiedBadge(seller)}
        ${seller?.residence ? `<p class="meta">Lives at ${escapeHtml(seller.residence)}</p>` : ''}
        ${seller?.bio ? `<p>${escapeHtml(seller.bio)}</p>` : ''}
      </div>
      ${canRespondToPost(item, state.user) ? `<div class="form-actions"><button class="button primary" data-action="respond" data-resource="listings" data-id="${escapeHtml(item.id)}">Message seller</button></div>` : ''}`;
    elements.detailDialog.showModal();
  }

  function openResponse(resource, post) {
    if (elements.detailDialog.open) elements.detailDialog.close();
    if (!state.user) { openAuth(); return; }
    const listing = resource === 'listings';
    state.pendingResponse = { post_type: listing ? 'listing' : 'help', post_id: post.id };
    elements.respondTitle.textContent = listing ? `Message about ${post.name}` : `Respond to ${post.title}`;
    elements.respondDropoff.hidden = !listing;
    elements.respondForm.reset();
    elements.respondDialog.showModal();
  }

  async function submitResponse(event) {
    event.preventDefault();
    const { message, dropoff_location } = formObject(elements.respondForm);
    try {
      const conversation = await request('conversations', { method: 'POST', data: { ...state.pendingResponse, body: message, dropoff_location } });
      elements.respondDialog.close();
      showConversation(conversation); showToast('Response sent.');
    } catch (error) { showToast(error.message, true); }
  }

  function showConversation(conversation) {
    state.conversations = saveConversationInList(state.conversations, conversation);
    state.activeConversationId = conversation.id;
    setView('messages');
  }

  async function submitMessage(event) {
    event.preventDefault();
    const { body } = formObject(elements.messageForm);
    try {
      const conversation = await request('conversations', { method: 'POST', id: state.activeConversationId, action: 'message', data: { body } });
      elements.messageForm.reset(); showConversation(conversation);
    } catch (error) { showToast(error.message, true); }
  }

  async function submitLocations(event) {
    event.preventDefault();
    try {
      const conversation = await request('conversations', { method: 'PUT', id: state.activeConversationId, data: formObject(elements.locationsForm) });
      showConversation(conversation); showToast('Locations saved.');
    } catch (error) { showToast(error.message, true); }
  }

  async function refreshConversations() {
    if (state.view !== 'messages' || !state.user) return;
    try { await loadConversations(); renderMessages(); } catch (error) { showToast(error.message, true); }
  }

  async function readPhotos(files) {
    const selected = [...files].slice(0, 4 - state.photos.length);
    for (const file of selected) {
      if (file.size > 1024 * 1024) { showToast(`${file.name} is larger than 1 MB.`, true); continue; }
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) { showToast(`${file.name} is not a supported image.`, true); continue; }
      const dataUrl = await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.onerror = reject; reader.readAsDataURL(file); });
      state.photos.push(dataUrl);
    }
    renderPhotoPreview();
  }

  function renderPhotoPreview() {
    elements.photoPreview.innerHTML = state.photos.map((photo, index) => `<span><img class="preview-photo" src="${escapeHtml(safePhoto(photo))}" alt="Selected photo ${index + 1}"><button type="button" class="small-button" data-remove-photo="${index}" aria-label="Remove photo ${index + 1}">Remove</button></span>`).join('');
  }

  function formObject(form) { return Object.fromEntries(new FormData(form).entries()); }

  async function submitListing(event) {
    event.preventDefault();
    const data = formObject(elements.listingForm); delete data.id; delete data.photos;
    data.price = Number(data.price); data.photos = state.photos;
    const id = elements.listingForm.elements.id.value;
    try {
      const saved = await request('listings', { method: id ? 'PUT' : 'POST', id, data });
      updateStateRecord('listings', saved, id);
      closeDialog(); renderAll(); showToast(id ? 'Listing updated.' : 'Listing published.');
    } catch (error) { showToast(error.message, true); }
  }

  async function submitHelp(event) {
    event.preventDefault();
    const data = formObject(elements.helpForm); const id = data.id; delete data.id; data.offer = Number(data.offer);
    try {
      const saved = await request('help', { method: id ? 'PUT' : 'POST', id, data });
      updateStateRecord('help_posts', saved, id);
      closeDialog(); renderAll(); showToast(id ? 'Help post updated.' : 'Help request posted.');
    } catch (error) { showToast(error.message, true); }
  }

  function updateStateRecord(key, saved, id) {
    state[key] = savePostInCollection(state[key], saved, id);
  }

  function handlePostAction(button) {
    const { action, resource, id } = button.dataset;
    const key = resource === 'listings' ? 'listings' : 'help_posts';
    const item = state[key].find(record => record.id === id);
    if (!item) return;
    if (action === 'edit') openForm(resource === 'listings' ? 'listing' : 'help', item);
    if (action === 'delete') { state.pendingDelete = { resource, key, id }; elements.confirmOverlay.hidden = false; }
    if (action === 'sold') markSold(id);
    if (action === 'details') openListingDetails(item);
    if (action === 'respond') openResponse(resource, item);
  }

  async function markSold(id) {
    try {
      const saved = await request('listings', { method: 'POST', id, action: 'sell' });
      updateStateRecord('listings', saved, id);
      renderAll(); showToast('Listing marked as sold.');
    } catch (error) { showToast(error.message, true); }
  }

  async function confirmDelete() {
    if (!state.pendingDelete) return;
    const { resource, key, id } = state.pendingDelete;
    try {
      await request(resource, { method: 'DELETE', id });
      state[key] = state[key].filter(item => item.id !== id);
      state.pendingDelete = null; elements.confirmOverlay.hidden = true; renderAll(); showToast('Post removed.');
    } catch (error) { showToast(error.message, true); }
  }

  let toastTimer;
  function showToast(message, error = false) {
    clearTimeout(toastTimer); elements.toast.textContent = message; elements.toast.className = `toast show${error ? ' error' : ''}`;
    toastTimer = setTimeout(() => { elements.toast.className = 'toast'; }, 3000);
  }

  document.addEventListener('click', event => {
    const view = event.target.closest('[data-view]'); if (view) setView(view.dataset.view);
    const opener = event.target.closest('[data-open-form]'); if (opener) openForm(opener.dataset.openForm);
    const closer = event.target.closest('[data-close-dialog]'); if (closer) closer.closest('dialog').close();
    const authMode = event.target.closest('[data-auth-mode]'); if (authMode) setAuthMode(authMode.dataset.authMode);
    const conversation = event.target.closest('[data-conversation]');
    if (conversation) { state.activeConversationId = conversation.dataset.conversation; renderMessages(); }
    const action = event.target.closest('[data-action]'); if (action) handlePostAction(action);
    const removePhoto = event.target.closest('[data-remove-photo]');
    if (removePhoto) { state.photos.splice(Number(removePhoto.dataset.removePhoto), 1); renderPhotoPreview(); }
  });
  elements.search.addEventListener('input', renderMarketplace);
  elements.category.addEventListener('change', renderMarketplace);
  elements.condition.addEventListener('change', renderMarketplace);
  elements.photoInput.addEventListener('change', event => readPhotos(event.target.files));
  elements.listingForm.addEventListener('submit', submitListing);
  elements.helpForm.addEventListener('submit', submitHelp);
  $('#confirm-cancel').addEventListener('click', () => { state.pendingDelete = null; elements.confirmOverlay.hidden = true; });
  $('#confirm-delete').addEventListener('click', confirmDelete);
  elements.loginForm.addEventListener('submit', submitLogin);
  elements.registerForm.addEventListener('submit', submitRegistration);
  elements.profileForm.addEventListener('submit', submitProfile);
  elements.respondForm.addEventListener('submit', submitResponse);
  elements.messageForm.addEventListener('submit', submitMessage);
  elements.locationsForm.addEventListener('submit', submitLocations);
  elements.signIn.addEventListener('click', openAuth);
  elements.signOut.addEventListener('click', signOut);
  $$('dialog').forEach(dialog => dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); }));
  setInterval(refreshConversations, CONVERSATION_REFRESH_MS);

  loadData().catch(error => showToast(error.message, true));
})();
