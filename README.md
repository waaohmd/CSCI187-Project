# Campus Community Marketplace

A web app for students to buy and sell used items or request moving and delivery help.

## Features

- Create item listings with photos, prices, categories, and conditions.
- Search and filter listings.
- Edit, remove, or mark listings as sold.
- Post moving and delivery requests with pickup and destination locations.

## Prerequisites

- PHP 8.1+
- Node.js 20+

## Setup

1. `git clone https://github.com/waaohmd/CSCI187-Project.git`: Clone the repository.
2. `cd CSCI187-Project`: Open the project directory.

## Run

`php -S localhost:8080 -t .`: Launch the app at [http://localhost:8080](http://localhost:8080).

## Test

`npm run check`: Check JavaScript syntax and run static smoke checks and unit tests. No PHP server is required.

`npm run test:unit`: Run independent unit tests, including marketplace search and filtering (Marc Guo).

`npm run test:api`: Run all API integration tests while the PHP server is running.

`npm test`: Run every test while the PHP server is running.

The runner automatically discovers `*.test.mjs` files in `tests/` and its subfolders. Name independent unit tests `*.unit.test.mjs`; other test files run as API integration tests, except the existing `smoke.test.mjs` structure check. New files need no changes to `package.json` or CI. Test files run sequentially because integration tests share the data store.

CI runs syntax checks, smoke checks, unit tests, PHP syntax checks, and API integration tests on PHP 8.1 and 8.4. Each contributor should add their name to their test file and choose a different behavior to test. To verify CI, push an intentional failing assertion on a separate branch, wait for a failed run, then remove it and confirm a successful run before merging.

## Project guidelines

Read `PROJECT_BRIEF.md` and `STYLE_REQUIREMENTS.md` before changing the project.

