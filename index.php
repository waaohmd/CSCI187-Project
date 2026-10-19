<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Buy, sell, and request moving help from students nearby.">
  <title>Campus Marketplace</title>
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <header class="site-header">
    <a href="#" class="brand">Campus Marketplace</a>
    <nav aria-label="Main navigation">
      <button class="nav-button active" data-view="marketplace">Marketplace</button>
      <button class="nav-button" data-view="help">Moving help</button>
      <button class="nav-button" data-view="mine">My posts</button>
    </nav>
  </header>

  <main class="container">
    <div class="page-heading">
      <div><h1 id="page-title">Items for sale</h1><p id="page-description">Buy used items from students nearby.</p></div>
      <div class="heading-actions">
        <button class="button secondary" data-open-form="help">Post moving help</button>
        <button class="button primary" data-open-form="listing">List an item</button>
      </div>
    </div>

    <section id="marketplace-view" class="view-panel">
      <div class="filters">
        <label>Search<input id="search" type="search" placeholder="Search items"></label>
        <label>Category<select id="category-filter"><option value="all">All categories</option><option>Furniture</option><option>Appliances</option><option>Electronics</option><option>Clothing</option><option>School supplies</option><option>Other</option></select></label>
        <label>Condition<select id="condition-filter"><option value="all">Any condition</option><option>Like new</option><option>Good</option><option>Fair</option><option>Well loved</option></select></label>
      </div>
      <div class="card-grid" id="listing-grid"></div>
    </section>

    <section id="help-view" class="view-panel" hidden><div class="help-list" id="help-list"></div></section>

    <section id="mine-view" class="view-panel" hidden>
      <div class="summary" id="mine-summary"></div>
      <div class="my-posts-grid">
        <div><h2>My listings</h2><div id="my-listings" class="post-list"></div></div>
        <div><h2>My help posts</h2><div id="my-help" class="post-list"></div></div>
      </div>
    </section>

    <div class="empty-state" id="empty-state" hidden><h2>No posts found</h2><p>Create a new post or change your filters.</p></div>
  </main>

  <dialog id="post-dialog">
    <div class="dialog-heading"><h2 id="form-title">List an item</h2><button class="icon-button" data-close-dialog aria-label="Close">×</button></div>

    <form id="listing-form" class="post-form">
      <input type="hidden" name="id">
      <label>Item name<input name="name" required maxlength="80"></label>
      <label>Description<textarea name="description" required maxlength="800" rows="4"></textarea></label>
      <div class="form-row">
        <label>Price ($)<input name="price" type="number" required min="0" max="100000" step="0.01"></label>
        <label>Category<select name="category" required><option value="">Select category</option><option>Furniture</option><option>Appliances</option><option>Electronics</option><option>Clothing</option><option>School supplies</option><option>Other</option></select></label>
      </div>
      <div class="form-row">
        <label>Condition<select name="condition" required><option value="">Select condition</option><option>Like new</option><option>Good</option><option>Fair</option><option>Well loved</option></select></label>
        <label>Pickup location<input name="location" required maxlength="100"></label>
      </div>
      <label>Photos (up to 4)<input id="item-photos" name="photos" type="file" accept="image/png,image/jpeg,image/webp" multiple></label>
      <div id="photo-preview" class="photo-preview"></div>
      <div class="form-actions"><button type="button" class="button secondary" data-close-dialog>Cancel</button><button type="submit" class="button primary" id="listing-submit">Publish listing</button></div>
    </form>

    <form id="help-form" class="post-form" hidden>
      <input type="hidden" name="id">
      <label>What help do you need?<input name="title" required maxlength="100"></label>
      <div class="form-row">
        <label>Type<select name="type" required><option>Delivery</option><option>Moving & lifting</option><option>Hauling</option><option>Driving</option></select></label>
        <label>Cash offer ($)<input name="offer" type="number" required min="1" max="10000" step="1"></label>
      </div>
      <label>From<input name="from_location" required maxlength="120" placeholder="Pickup location"></label>
      <label>To<input name="to_location" required maxlength="120" placeholder="Destination"></label>
      <div class="form-row">
        <label>Date<input name="date" type="date" required></label>
        <label>Preferred time<input name="time" type="time" required></label>
      </div>
      <label>Details<textarea name="details" required maxlength="600" rows="4"></textarea></label>
      <div class="form-actions"><button type="button" class="button secondary" data-close-dialog>Cancel</button><button type="submit" class="button primary" id="help-submit">Post request</button></div>
    </form>
  </dialog>

  <div id="confirm-overlay" class="confirm-overlay" hidden>
    <div class="confirm-box" role="alertdialog" aria-modal="true"><h2>Remove this post?</h2><p>This action cannot be undone.</p><div class="form-actions"><button class="button secondary" id="confirm-cancel">Cancel</button><button class="button danger" id="confirm-delete">Remove</button></div></div>
  </div>
  <div id="toast" class="toast" role="status" aria-live="polite"></div>
  <script src="assets/app.js" defer></script>
</body>
</html>
