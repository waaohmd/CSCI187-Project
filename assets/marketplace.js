// Keep browsing rules independent of the DOM so they can be tested directly.
export function filterListings(listings, { query = '', category = 'all', condition = 'all' } = {}) {
  const search = query.trim().toLowerCase();
  return listings.filter(item => {
    const matchesText = !search || `${item.name} ${item.description} ${item.location}`.toLowerCase().includes(search);
    return matchesText && (category === 'all' || item.category === category) && (condition === 'all' || item.condition === condition);
  });
}

export function savePostInCollection(posts, savedPost, editedPostId = '') {
  if (!editedPostId) return [savedPost, ...posts];
  return posts.map(post => post.id === editedPostId ? savedPost : post);
}

export function fillPostForm(form, post = null) {
  form.reset();
  // reset() leaves hidden inputs unchanged, and a stale id would turn the next new post into an edit.
  form.elements.id.value = '';
  if (!post) return;
  Object.entries(post).forEach(([field, value]) => {
    if (form.elements[field] && field !== 'photos') form.elements[field].value = value;
  });
}
