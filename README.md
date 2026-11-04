# Campus Community Marketplace

A web app for SCU students to buy and sell used items, request moving and delivery help, and coordinate through in-app chat.

## Features

- Create an account with an `@scu.edu` email, sign in, and maintain a profile.
- Create, edit, sell, and remove item listings.
- Search listings by name, description, location, or category, and filter by category and condition.
- View full listing details, including seller information and verification.
- Create, edit, close, and remove moving or delivery requests.
- Respond to listings and help posts, chat with other students, and agree on pickup and drop-off locations.

## Prerequisites

- PHP 8.1+
- Node.js 20+

## Setup

1. Run `git clone https://github.com/waaohmd/CSCI187-Project.git`.
2. Run `cd CSCI187-Project`.

## Run

Run `php -S localhost:8080 -t .`.

Open [http://localhost:8080](http://localhost:8080), then click **Sign in** and **Create an account**. Use any `@scu.edu` address. The sample sellers in `data/store.json` cannot sign in.

## Test

Run `npm run check` for syntax checks and unit tests. Unit tests call functions directly and need no server.

Run the API integration tests against a temporary copy of the data:

```bash
cp data/store.json /tmp/store.json
MARKETPLACE_DATA_FILE=/tmp/store.json php -S 127.0.0.1:8080 -t .
npm run test:api
```

Run the last command in a second terminal. Without `MARKETPLACE_DATA_FILE`, the tests add accounts to `data/store.json`.

## Troubleshooting

- **"The PHP server is not running."** The page was opened without PHP. Start the server from the **Run** section.
- **"Use your @scu.edu email address."** Accounts require an SCU email.

## Project documents

- `REQUIREMENTS.md` lists what the app must do.
- `SPECIFICATIONS.md` describes how the app behaves and documents the API.
- `docs/CRC_DIAGRAM.md` lists the CRC cards. Edit the diagram in `docs/CRC_DIAGRAM.drawio`.
- `docs/ER_DIAGRAM.md` shows the data model.

Read `PROJECT_BRIEF.md` and `STYLE_REQUIREMENTS.md` before changing the project.
