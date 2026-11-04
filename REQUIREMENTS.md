# Requirements — Campus Community Marketplace

These requirements describe what students need from the app. See `SPECIFICATIONS.md` for how the system meets each requirement and `PROJECT_BRIEF.md` for the full vision.

## MVP requirements (implemented)

### Posting

1. User should be able to list items with a description of the product.
2. User should be able to add an item name, description, photos, price, category, and condition to their listings.
3. User should be able to post a need for a mover or delivery help, including specific to/from destination locations.
4. User should be able to sell items on the app.
5. User should be able to edit or remove their listings and posts.

### Responding and buying

6. Movers should be able to respond to posts needing movers.
7. Buyers should be able to respond to posts selling items.
8. Buyers should be able to search for items.
9. User should be able to search by item name or category, such as furniture, electronics, clothing, or school supplies.
10. Buyers should be able to view item listing information, such as description, photos, price, category, condition, and seller information.

### Contact and coordination

11. Buyers, sellers, and movers should be able to communicate through in-app chat.
12. User should be able to set pickup and drop-off locations.

### Authentication

13. User should be able to sign in to their school account.
14. User should be able to create and maintain a basic profile associated with their SCU account.
15. User should be able to view whether another user is a verified SCU student.

### Trust and safety

- User should only be able to edit, sell, or remove their own posts.
- User should only be able to read and send messages in their own conversations.
- User should be warned before removing a post, because removal cannot be undone.
- User should see a clear error message when a post, account, or message is missing required information.

## Future requirements (stretch)

- User should be able to confirm ownership of their SCU email or sign in through SCU single sign-on.
- User should be able to choose a pickup or help time slot that all parties agree on.
- User should be able to sort available delivery jobs by estimated completion time.
- User should be able to reserve an item to inspect it in person before paying.
- User should be able to pay securely in the app for items and moving help.
- User should be able to lock in a purchase with a reservation deposit.
- User should be able to filter items by verified condition ratings.
- User should be able to view a suggested selling price based on an item's condition.
- User should be able to book doorstep pickup for items they want to donate.
- User should be able to view an interactive campus map of donation bins and student pop-up sales.

## Non-functional requirements

- The app should work in a desktop or mobile web browser without installing anything.
- The app should keep saved posts, accounts, and conversations after the server restarts.
- The app should reject invalid input on the server, not only in the browser form.
- The app should store passwords only as salted hashes.
- The app should pass every automated check in CI before changes merge to `main`.
