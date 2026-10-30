# Specifications — Campus Community Marketplace

These specifications describe how the walking skeleton behaves. Each one maps to a requirement in `REQUIREMENTS.md`.

## Architecture

- **Frontend:** `index.php` renders the page; `assets/app.js` handles views, forms, and API calls; `assets/styles.css` styles the page.
- **Controller:** `api.php` routes HTTP requests and translates domain or persistence errors into JSON responses.
- **Domain:** `src/Domain.php` contains plain validation, ownership, and post lookup functions.
- **Storage:** `src/Store.php` reads and writes the `listings` and `help_posts` arrays in `data/store.json` with file locking.
- **Preview mode:** When the PHP API is unreachable, `assets/app.js` loads `data/store.json` and saves changes in the browser's `localStorage` instead.

## Specifications for posting items

1. When the user clicks **List an item**, the system opens a form with fields for item name, description, price, category (dropdown), condition (dropdown), pickup location, and up to four photos.
2. When the user submits the form with valid fields, the system creates a listing with status `available` and adds it to the top of the Marketplace list. Otherwise the system shows an error message and creates nothing.
3. The system accepts a listing only when all of these rules hold:
   - `name` is 1–80 characters.
   - `description` is 1–800 characters.
   - `price` is a number from 0 to 100,000.
   - `category` is one of `Furniture`, `Appliances`, `Electronics`, `Clothing`, `School supplies`, or `Other`.
   - `condition` is one of `Like new`, `Good`, `Fair`, or `Well loved`.
   - `location` is 1–100 characters.
   - `photos` contains at most four PNG, JPEG, or WebP images. The form rejects any photo larger than 1 MB.

## Specifications for browsing

1. When the user clicks **Campus Marketplace**, the system opens the Marketplace page.
2. The system displays **Post moving help** and **List an item** only on the Marketplace page.
3. The Marketplace page displays every listing's photo, name, price, category, condition, description, pickup location, and seller, newest first.
4. When the user types in **Search**, the system shows only listings whose name, description, or pickup location contains the text, ignoring case.
5. When the user selects a **Category** or **Condition** filter, the system shows only listings that match every selected filter.
6. When no listing matches, the system shows "No posts found".
7. The system keeps sold listings visible with a **Sold** badge.

## Specifications for managing posts

1. The **My posts** page displays the user's listings and help posts, plus counts of listings, sold listings, and help posts.
2. When the user clicks **Edit** on their post, the system opens the form filled with the post's current values. When the user clicks **Save changes**, the system validates and saves the post using the same rules as creating it.
3. When the user clicks **Mark sold** on an available listing, the system sets its status to `sold` and hides the **Mark sold** button.
4. When the user clicks **Remove** on their post, the system asks "Remove this post? This action cannot be undone." When the user confirms, the system deletes the post from every list. When the user cancels, nothing changes.
5. When a user tries to edit, sell, or remove a post they do not own, the API rejects the request with status `403`.

## Specifications for moving and delivery help

1. When the user clicks **Post moving help**, the system opens a form with fields for title, type (dropdown), cash offer, from location, to location, date, preferred time, and details.
2. When the user submits the form with valid fields, the system creates a help post with status `open` and adds it to the top of the Moving help list. Otherwise the system shows an error message and creates nothing.
3. The system accepts a help post only when all of these rules hold:
   - `title` is 1–100 characters.
   - `type` is one of `Delivery`, `Moving & lifting`, `Hauling`, or `Driving`.
   - `offer` is a number from 1 to 10,000.
   - `from_location` and `to_location` are each 1–120 characters.
   - `date` uses the format `YYYY-MM-DD`. The form does not allow a new post's date to be in the past.
   - `time` uses the format `HH:MM`.
   - `details` is 1–600 characters.
4. The Moving help page displays every help post's title, type, route, details, date and time, poster, and cash offer.

## API reference

All requests go to `api.php`. Set `resource` to `listings` or `help`.

| Method | Query | Body | Result |
| --- | --- | --- | --- |
| `GET` | `resource` | none | `200` with every post in the collection |
| `POST` | `resource` | post fields | `201` with the new post |
| `POST` | `resource=listings&id=…&action=sell` | none | `200` with the listing marked `sold` |
| `PUT` | `resource`, `id` | post fields | `200` with the updated post |
| `DELETE` | `resource`, `id` | none | `200` with the deleted post |

Successful responses use the shape `{ "data": … }`. Failed responses use the shape `{ "error": "…", "field": "…" }`, where `field` appears only on validation failures.

| Status | Meaning |
| --- | --- |
| `400` | The body is not valid JSON, or `id` is missing. |
| `403` | The post belongs to another user. |
| `404` | The resource or post does not exist. |
| `405` | The method is not supported. |
| `422` | A field failed validation. |
| `500` | The data store could not be read or written. |

## Data model

| Field | Listing | Help post |
| --- | --- | --- |
| `id` | `lst_` + random hex | `hlp_` + random hex |
| `owner` | `You` for the current user | `You` for the current user |
| `status` | `available` or `sold` | `open` |
| `created_at`, `updated_at` | ISO 8601 UTC timestamps | ISO 8601 UTC timestamps |

The walking skeleton has no accounts yet, so the current user is always `You`.
