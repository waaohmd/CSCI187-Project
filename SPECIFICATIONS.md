# Specifications — Campus Community Marketplace

These specifications describe how the MVP behaves. Each section maps to the matching heading in `REQUIREMENTS.md`, and numbers in brackets name the requirements it covers.

## Architecture

- **Frontend:** `index.php` renders the page; `assets/app.js` handles views, forms, and API calls; `assets/marketplace.js` holds DOM-free rules; `assets/styles.css` styles the page.
- **Controller:** `api.php` manages the session, routes HTTP requests to the models, and translates their errors into JSON responses. It contains no business rules.
- **Models:** `src/PostModel.php` manages listings and help posts. `src/UserModel.php` manages accounts and profiles. `src/ConversationModel.php` manages responses, messages, and meeting locations.
- **Domain:** `src/Domain.php` contains plain validation, permission, and lookup functions.
- **Storage:** `src/Store.php` reads and writes `data/store.json` with file locking.

See `docs/CRC_DIAGRAM.md` for responsibilities and `docs/ER_DIAGRAM.md` for the data model.

## Specifications for posting [1–5]

1. When a signed-in user clicks **List an item**, the system opens a form with fields for item name, description, price, category (dropdown), condition (dropdown), pickup location, and up to four photos. When a signed-out user clicks it, the system opens **Sign in**.
2. When the user submits the form with valid fields, the system creates a listing owned by the user with status `available` and adds it to the top of the Marketplace list. Otherwise the system shows an error message and creates nothing.
3. The system accepts a listing only when all of these rules hold:
   - `name` is 1–80 characters.
   - `description` is 1–800 characters.
   - `price` is a number from 0 to 100,000.
   - `category` is one of `Furniture`, `Appliances`, `Electronics`, `Clothing`, `School supplies`, or `Other`.
   - `condition` is one of `Like new`, `Good`, `Fair`, or `Well loved`.
   - `location` is 1–100 characters.
   - `photos` contains at most four PNG, JPEG, or WebP images. The form rejects any photo larger than 1 MB.
4. When a signed-in user clicks **Post moving help**, the system opens a form with fields for title, type (dropdown), cash offer, pickup location, drop-off location, date, preferred time, and details.
5. When the user submits the form with valid fields, the system creates a help post with status `open` and adds it to the top of the Moving help list. Otherwise the system shows an error message and creates nothing.
6. The system accepts a help post only when all of these rules hold:
   - `title` is 1–100 characters.
   - `type` is one of `Delivery`, `Moving & lifting`, `Hauling`, or `Driving`.
   - `offer` is a number from 1 to 10,000.
   - `from_location` and `to_location` are each 1–120 characters.
   - `date` uses the format `YYYY-MM-DD`. The form does not allow a new post's date to be in the past.
   - `time` uses the format `HH:MM`.
   - `details` is 1–600 characters.
7. The **My posts** page displays the user's listings and help posts, plus counts of listings, sold listings, and help posts.
8. When the user clicks **Edit** on their post, the system opens the form filled with the post's current values. When the user clicks **Save changes**, the system validates and saves the post using the same rules as creating it.
9. When the user clicks **Mark sold** on an available listing, the system sets its status to `sold` and hides the **Mark sold** button.
10. When the user clicks **Remove** on their post, the system asks "Remove this post? This action cannot be undone." When the user confirms, the system deletes the post from every list. When the user cancels, nothing changes.
11. When a user tries to edit, sell, or remove a post they do not own, the API rejects the request with status `403`.

## Specifications for responding and buying [6–10]

1. When the user clicks **Campus Marketplace**, the system opens the Marketplace page.
2. The system displays **Post moving help** and **List an item** only on the Marketplace page.
3. The Marketplace page displays every listing's photo, name, price, category, condition, description, pickup location, seller, and seller verification, newest first. Signed-out visitors can browse.
4. When the user types in **Search**, the system shows only listings whose name, description, pickup location, or category contains the text, ignoring case.
5. When the user selects a **Category** or **Condition** filter, the system shows only listings that match every selected filter.
6. When no listing matches, the system shows "No posts found".
7. The system keeps sold listings visible with a **Sold** badge.
8. When the user clicks **View details**, the system shows the listing's photos, description, price, category, condition, pickup location, and the seller's name, verification, residence, and bio.
9. When a buyer clicks **Message seller**, the system asks for a message and an optional drop-off location, then starts a conversation with the seller.
10. The Moving help page displays every help post's title, type, route, details, date and time, poster, poster verification, and cash offer.
11. When a mover clicks **Respond** on a help post, the system asks for a message, then starts a conversation with the poster.
12. The system hides the response buttons on the user's own posts and on sold listings. The API rejects a response to the user's own post with status `422` and a response to a sold listing with status `409`.
13. When a user responds to the same post again, the system adds the message to their existing conversation.

## Specifications for contact and coordination [11–12]

1. The **Messages** page lists the user's conversations, most recently active first, with the post title, the other user, and the latest message.
2. When the user opens a conversation, the system displays its messages in the order they were sent and the other user's verification.
3. When the user clicks **Send**, the system adds a message of 1–1,000 characters to the conversation.
4. The open page checks for new messages every five seconds.
5. Each conversation stores a pickup and a drop-off location of up to 120 characters. A listing response starts with the listing's pickup location and the buyer's drop-off location. A help response starts with the help post's locations.
6. When either participant clicks **Save locations**, the system updates both locations for both participants.
7. When a user tries to read or change a conversation they are not part of, the API rejects the request with status `403`.

## Specifications for authentication [13–15]

1. When the user clicks **Create an account**, the system asks for a name, an `@scu.edu` email, and a password of 8–72 characters. The system rejects any other email domain and any email that already has an account.
2. When the user clicks **Sign in**, the system checks their email and password and starts a session. A failed attempt shows "Email or password is incorrect." without saying which field failed.
3. When the user clicks **Sign out**, the system ends the session and opens the Marketplace page.
4. Creating or changing posts, responding, messaging, and opening **My posts**, **Messages**, or **Profile** require a signed-in user. The API rejects signed-out requests with status `401`.
5. The **Profile** page lets the user change their name (1–80 characters), residence (up to 100 characters), and bio (up to 300 characters). The email cannot change.
6. The system shows **Verified SCU student** for any account with an `@scu.edu` email, and **Not verified** otherwise, on listings, listing details, help posts, conversations, and the user's profile.
7. Public profiles include `id`, `name`, `residence`, `bio`, and `verified`. They never include the email or password hash.

**Known limit:** The MVP confirms the email domain, not ownership of the mailbox. Confirming ownership requires a mail service or SCU single sign-on, which `REQUIREMENTS.md` lists as future work.

## API reference

All requests go to `api.php`. Set `resource` to `auth`, `profile`, `listings`, `help`, or `conversations`. Sessions use the `PHPSESSID` cookie.

| Method | Query | Body | Sign-in | Result |
| --- | --- | --- | --- | --- |
| `GET` | `resource=auth` | none | No | `200` with the signed-in account, or `null` |
| `POST` | `resource=auth&action=register` | `name`, `email`, `password` | No | `201` with the account, signed in |
| `POST` | `resource=auth&action=login` | `email`, `password` | No | `200` with the account, signed in |
| `POST` | `resource=auth&action=logout` | none | No | `200` with `null` |
| `GET` | `resource=profile&id=…` | none | No | `200` with a public profile |
| `PUT` | `resource=profile` | `name`, `residence`, `bio` | Yes | `200` with the updated account |
| `GET` | `resource=listings` or `help` | none | No | `200` with every post and its `owner` |
| `POST` | `resource=listings` or `help` | post fields | Yes | `201` with the new post |
| `POST` | `resource=listings&id=…&action=sell` | none | Yes | `200` with the listing marked `sold` |
| `PUT` | `resource=listings` or `help`, `id` | post fields | Yes | `200` with the updated post |
| `DELETE` | `resource=listings` or `help`, `id` | none | Yes | `200` with the deleted post |
| `GET` | `resource=conversations` | none | Yes | `200` with the user's conversations |
| `GET` | `resource=conversations&id=…` | none | Yes | `200` with one conversation |
| `POST` | `resource=conversations` | `post_type`, `post_id`, `body`, optional `pickup_location` and `dropoff_location` | Yes | `201` with the conversation |
| `POST` | `resource=conversations&id=…&action=message` | `body` | Yes | `201` with the conversation |
| `PUT` | `resource=conversations&id=…` | `pickup_location`, `dropoff_location` | Yes | `200` with the conversation |

Set `post_type` to `listing` or `help`. Successful responses use the shape `{ "data": … }`. Failed responses use the shape `{ "error": "…", "field": "…" }`, where `field` appears only on validation failures.

| Status | Meaning |
| --- | --- |
| `400` | The body is not valid JSON, or `id` is missing. |
| `401` | The user must sign in, or the email or password is incorrect. |
| `403` | The post or conversation belongs to other users. |
| `404` | The resource, action, or record does not exist. |
| `405` | The method is not supported. |
| `409` | The email already has an account, or the listing is already sold. |
| `422` | A field failed validation. |
| `500` | The data store could not be read or written. |

## Data model

`data/store.json` holds the `users`, `listings`, `help_posts`, `conversations`, and `messages` arrays. See `docs/ER_DIAGRAM.md` for every field.

| Record | ID format | Status values |
| --- | --- | --- |
| User | `usr_` + random hex | none |
| Listing | `lst_` + random hex | `available` or `sold` |
| Help post | `hlp_` + random hex | `open` |
| Conversation | `cnv_` + random hex | none |
| Message | `msg_` + random hex | none |

All timestamps use ISO 8601 in UTC. Sample users have an empty `password_hash`, so nobody can sign in as them.

## Testing

- `*.unit.php` and `*.unit.test.mjs` files call plain functions directly and need no server. Model tests use a temporary data file.
- `tests/api.test.mjs` is the integration test. It sends HTTP requests to a running PHP server.
- Every core feature has a test of its expected behavior, plus tests for boundaries, invalid input, and failure states.
