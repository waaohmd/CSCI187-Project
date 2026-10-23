# Campus Community Marketplace — First Prototype

A JavaScript + PHP web application implementing the first posting workflow for the Campus Community Marketplace project.

Read `PROJECT_BRIEF.md` and `STYLE_REQUIREMENTS.md` before changing the project.

## First prototype

This prototype focuses on the initial marketplace and moving-help posting requirements. It provides working create, view, edit, sell, and remove flows backed by a PHP JSON API.

## Included

- Marketplace browsing with search, category, and condition filters.
- Create listings with item name, description, photos, price, category, and condition.
- Create mover/delivery help posts with from/to locations, date, time, details, and an offer.
- Edit and delete listings or help posts.
- Mark an item as sold.
- PHP JSON API with file-backed persistence.
- Browser-local fallback so the interface remains interactive when opened without PHP.

## Run

PHP 8.1+ is recommended:

```powershell
php -S localhost:8080 -t .
```

Then open `http://localhost:8080`.


## Files

- `index.php` — application shell and forms
- `assets/app.js` — user interactions and API client
- `assets/styles.css` — responsive interface
- `api.php` — CRUD API
- `data/store.json` — seed and local persistence data

## Continuous integration

The GitHub Actions workflow at `.github/workflows/ci.yml` runs for every push and pull request. It:

- Tests PHP 8.1 and PHP 8.4.
- Checks JavaScript syntax and the required form/API structure.
- Lints every PHP file.
- Starts the PHP application and tests listing and moving-help create, read, edit, sell, and delete operations through the API.

Run the checks locally with:

```powershell
npm run check
```

With the PHP server running on port 8080, run the API integration checks with:

```powershell
npm run test:api
```

