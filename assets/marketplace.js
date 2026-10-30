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
