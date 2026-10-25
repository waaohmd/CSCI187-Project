# Requirements — Campus Community Marketplace

These requirements describe what students need from the app. See `SPECIFICATIONS.md` for how the system meets each requirement and `PROJECT_BRIEF.md` for the full vision.

## Walking skeleton requirements (implemented)

### Posting items

- User should be able to list items with a description of the product.
- User should be able to add an item name, description, photos, price, category, and condition to their listings.
- User should be able to add a pickup location to their listings.
- User should be able to sell items on the app by marking a listing as sold.
- User should be able to edit or remove their listings.

### Moving and delivery help

- User should be able to post a need for mover or delivery help, including specific to/from destination locations.
- User should be able to choose a type of help: delivery, moving and lifting, hauling, or driving.
- User should be able to set a preferred date, time, and cash offer for a help post.
- User should be able to edit or remove their help posts.

### Browsing

- User should be able to browse every listing with its photo, name, price, category, and condition.
- User should be able to search listings by keyword.
- User should be able to filter listings by category and condition.
- User should be able to browse every open moving and delivery help post.
- User should be able to view all of their own listings and help posts in one place.

### Trust and safety

- User should only be able to edit, sell, or remove their own posts.
- User should be warned before removing a post, because removal cannot be undone.
- User should see a clear error message when a post is missing required information.

## MVP requirements (planned)

- User should be able to create an account and sign in with a campus email.
- User should be able to message a buyer or seller in the app.
- User should be able to accept a moving or delivery help post as a mover.
- User should be able to choose a pickup or help time slot that all parties agree on.
- User should be able to sort available delivery jobs by estimated completion time.
- User should be able to reserve an item to inspect it in person before paying.
- User should be able to pay securely in the app for items and moving help.

## Future requirements (stretch)

- User should be able to lock in a purchase with a reservation deposit.
- User should be able to filter items by verified condition ratings.
- User should be able to view a suggested selling price based on an item's condition.
- User should be able to book doorstep pickup for items they want to donate.
- User should be able to view an interactive campus map of donation bins and student pop-up sales.

## Non-functional requirements

- The app should work in a desktop or mobile web browser without installing anything.
- The app should keep saved posts after the server restarts.
- The app should reject invalid input on the server, not only in the browser form.
- The app should pass every automated check in CI before changes merge to `main`.
