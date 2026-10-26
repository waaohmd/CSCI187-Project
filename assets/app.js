import { filterListings } from './marketplace.js';

(() => {
  'use strict';

  const STORAGE_KEY = 'campus-marketplace-v1';
  const state = { listings: [], help_posts: [], view: 'marketplace', localMode: false, photos: [], pendingDelete: null };
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

  const elements = {
    title: $('#page-title'), description: $('#page-description'), dialog: $('#post-dialog'),
    listingForm: $('#listing-form'), helpForm: $('#help-form'), formTitle: $('#form-title'),
    listingGrid: $('#listing-grid'), helpList: $('#help-list'), myListings: $('#my-listings'),
    myHelp: $('#my-help'), summary: $('#mine-summary'), empty: $('#empty-state'),
    search: $('#search'), category: $('#category-filter'), condition: $('#condition-filter'),
    photoInput: $('#item-photos'), photoPreview: $('#photo-preview'), toast: $('#toast'),
    confirmOverlay: $('#confirm-overlay')
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
    if (state.localMode) return localRequest(resource, { method, id, action, data });
    const query = new URLSearchParams({ resource });
    if (id) query.set('id', id);
    if (action) query.set('action', action);
    const response = await fetch(`api.php?${query}`, {
      method,
      headers: data ? { 'Content-Type': 'application/json' } : {},
      body: data ? JSON.stringify(data) : undefined
    });
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) throw new Error('PHP API is not running.');
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'The request failed.');
    return payload.data;
  }

  function localRequest(resource, { method, id, action, data }) {
    const key = resource === 'listings' ? 'listings' : 'help_posts';
    const collection = state[key];
    if (method === 'GET') return collection;
    const index = collection.findIndex(item => item.id === id);
    if (method === 'POST' && action === 'sell') {
      if (index < 0) throw new Error('Listing not found.');
      collection[index] = { ...collection[index], status: 'sold', updated_at: new Date().toISOString() };
      persistLocal(); return collection[index];
    }
    if (method === 'POST') {
      const item = { id: `${resource === 'listings' ? 'lst' : 'hlp'}_${Date.now()}`, owner: 'You', ...data, status: resource === 'listings' ? 'available' : 'open', created_at: new Date().toISOString() };
      collection.unshift(item); persistLocal(); return item;
    }
    if (index < 0) throw new Error('Post not found.');
    if (method === 'PUT') { collection[index] = { ...collection[index], ...data, updated_at: new Date().toISOString() }; persistLocal(); return collection[index]; }
    if (method === 'DELETE') { const [item] = collection.splice(index, 1); persistLocal(); return item; }
  }

  function persistLocal() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ listings: state.listings, help_posts: state.help_posts }));
  }

  async function loadData() {
    try {
      const [listings, help] = await Promise.all([request('listings'), request('help')]);
      state.listings = listings; state.help_posts = help;
    } catch (error) {
      state.localMode = true;
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        state.listings = parsed.listings || []; state.help_posts = parsed.help_posts || [];
      } else {
        const response = await fetch('data/store.json');
        const seed = await response.json();
        state.listings = seed.listings || []; state.help_posts = seed.help_posts || [];
        persistLocal();
      }
      showToast('Preview mode: changes are saved in this browser.');
    }
    renderAll();
  }

  function listingCard(item) {
    const photo = item.photos?.[0] || 'assets/mini-fridge.svg';
    const sold = item.status === 'sold';
    return `<article class="item-card">
      <img class="item-photo" src="${escapeHtml(safePhoto(photo))}" alt="${escapeHtml(item.name)}">
      <div class="item-body">
        <div class="card-line"><h2>${escapeHtml(item.name)}</h2><span class="price">${money(item.price)}</span></div>
        <div><span class="badge">${escapeHtml(item.category)}</span><span class="badge">${escapeHtml(item.condition)}</span>${sold ? '<span class="badge sold">Sold</span>' : ''}</div>
        <p class="description">${escapeHtml(item.description)}</p>
        <p class="meta">Pickup: ${escapeHtml(item.location)} · Seller: ${escapeHtml(item.owner)}</p>
      </div>
    </article>`;
  }

  function helpCard(post, includeActions = false) {
    return `<article class="help-card">
      <div><h2>${escapeHtml(post.title)}</h2><span class="badge">${escapeHtml(post.type)}</span>
        <p class="route"><strong>From:</strong> ${escapeHtml(post.from_location)}<br><strong>To:</strong> ${escapeHtml(post.to_location)}</p>
        <p>${escapeHtml(post.details)}</p><p class="meta">${dateTime(post.date, post.time)} · Posted by ${escapeHtml(post.owner)}</p>
        ${includeActions ? actionButtons('help', post.id, false) : ''}
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
    elements.helpList.innerHTML = state.help_posts.map(post => helpCard(post)).join('');
    toggleEmpty(state.help_posts.length === 0);
  }

  function renderMine() {
    const listings = state.listings.filter(item => item.owner === 'You');
    const help = state.help_posts.filter(item => item.owner === 'You');
    const sold = listings.filter(item => item.status === 'sold').length;
    elements.summary.innerHTML = `<div><strong>${listings.length}</strong><span>Listings</span></div><div><strong>${sold}</strong><span>Sold</span></div><div><strong>${help.length}</strong><span>Help posts</span></div>`;
    elements.myListings.innerHTML = listings.map(myListingRow).join('') || '<p>No listings yet.</p>';
    elements.myHelp.innerHTML = help.map(myHelpRow).join('') || '<p>No help posts yet.</p>';
    toggleEmpty(false);
  }

  function renderAll() {
    if (state.view === 'marketplace') renderMarketplace();
    if (state.view === 'help') renderHelp();
    if (state.view === 'mine') renderMine();
  }

  function setView(view) {
    state.view = view;
    $$('.view-panel').forEach(panel => panel.hidden = panel.id !== `${view}-view`);
    $$('.nav-button').forEach(button => button.classList.toggle('active', button.dataset.view === view));
    const copy = {
      marketplace: ['Items for sale', 'Buy used items from students nearby.'],
      help: ['Moving and delivery help', 'Post a route and cash offer for another student.'],
      mine: ['My posts', 'Edit, remove, or mark your listings as sold.']
    }[view];
    elements.title.textContent = copy[0]; elements.description.textContent = copy[1];
    renderAll();
  }

  function toggleEmpty(show) { elements.empty.hidden = !show; }

  function openForm(type, item = null) {
    const listing = type === 'listing';
    elements.listingForm.hidden = !listing; elements.helpForm.hidden = listing;
    elements.formTitle.textContent = item ? (listing ? 'Edit listing' : 'Edit help post') : (listing ? 'List an item' : 'Post moving help');
    const form = listing ? elements.listingForm : elements.helpForm;
    form.reset();
    if (item) Object.entries(item).forEach(([key, value]) => { if (form.elements[key] && key !== 'photos') form.elements[key].value = value; });
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
      if (!state.localMode) updateStateRecord('listings', saved, id);
      closeDialog(); renderAll(); showToast(id ? 'Listing updated.' : 'Listing published.');
    } catch (error) { showToast(error.message, true); }
  }

  async function submitHelp(event) {
    event.preventDefault();
    const data = formObject(elements.helpForm); const id = data.id; delete data.id; data.offer = Number(data.offer);
    try {
      const saved = await request('help', { method: id ? 'PUT' : 'POST', id, data });
      if (!state.localMode) updateStateRecord('help_posts', saved, id);
      closeDialog(); renderAll(); showToast(id ? 'Help post updated.' : 'Help request posted.');
    } catch (error) { showToast(error.message, true); }
  }

  function updateStateRecord(key, saved, id) {
    if (!id) state[key].unshift(saved);
    else state[key] = state[key].map(item => item.id === id ? saved : item);
  }

  function handlePostAction(button) {
    const { action, resource, id } = button.dataset;
    const key = resource === 'listings' ? 'listings' : 'help_posts';
    const item = state[key].find(record => record.id === id);
    if (!item) return;
    if (action === 'edit') openForm(resource === 'listings' ? 'listing' : 'help', item);
    if (action === 'delete') { state.pendingDelete = { resource, key, id }; elements.confirmOverlay.hidden = false; }
    if (action === 'sold') markSold(id);
  }

  async function markSold(id) {
    try {
      const saved = await request('listings', { method: 'POST', id, action: 'sell' });
      if (!state.localMode) updateStateRecord('listings', saved, id);
      renderAll(); showToast('Listing marked as sold.');
    } catch (error) { showToast(error.message, true); }
  }

  async function confirmDelete() {
    if (!state.pendingDelete) return;
    const { resource, key, id } = state.pendingDelete;
    try {
      await request(resource, { method: 'DELETE', id });
      if (!state.localMode) state[key] = state[key].filter(item => item.id !== id);
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
    if (event.target.closest('[data-close-dialog]')) closeDialog();
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
  elements.dialog.addEventListener('click', event => { if (event.target === elements.dialog) closeDialog(); });

  loadData().catch(error => showToast(error.message, true));
})();
