# Limit Order Exchange Mini Engine

**A Laravel-powered exchange prototype for placing and managing BTC and ETH limit orders.**

`Laravel 13` · `Vue 3` · `Inertia 3`

---

## The Project

The application supports authenticated buy and sell limit orders for BTC and ETH. Open orders reserve the required USD or asset balance, and cancelling an order releases that reservation.

> **Important:** Full matches only; an order matches only when the buy and sell prices are equal.

## Get Started

### Requirements

- PHP 8.4 or newer and Composer
- Node.js and npm

### Install

From the project root, create the local SQLite database file if needed and install the application dependencies:

```sh
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
composer run setup
```

`composer run setup` installs PHP and frontend dependencies, creates `.env` and the application key, runs database migrations, and builds the frontend. SQLite is used by default.

To use another database, update these settings in `.env` with your database connection details:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Create the database before connecting to it. If you change these settings after running `composer run setup`, apply the migrations to the selected database with `php artisan migrate`.

### Real-Time Updates

When two orders match, an `OrderMatched` event is broadcast through [Pusher](https://pusher.com) to the buyer's and seller's private channels, and their dashboards refresh without reloading the page.

Create a Channels app in your Pusher dashboard and add its credentials to `.env`:

```dotenv
BROADCAST_CONNECTION=pusher

PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_app_cluster
```

The `VITE_PUSHER_*` variables reuse these values, so rebuild the frontend (`npm run build`) or restart `composer run dev` after changing them.

Broadcasting uses the queue, which defaults to the `database` connection (`QUEUE_CONNECTION=database`). `composer run dev` runs a queue worker that processes jobs from the database queue, so no separate worker is needed during development. If you start the processes individually, run `php artisan queue:listen` alongside the server, otherwise match events are never delivered.

### Demo Data

Seed the database with the demo accounts and their starting balances:

```sh
php artisan db:seed
```

This creates a funded **Buy Tester** with **100,000.00 USD** and a funded **Sell Tester** with **1.00000000 BTC** and **10.00000000 ETH**. Use either account to sign in from the login screen:

| Account     | Email                | Password   | Starting balance                 |
| ----------- | -------------------- | ---------- | -------------------------------- |
| Buy Tester  | `buyer@example.com`  | `password` | 100,000.00 USD                   |
| Sell Tester | `seller@example.com` | `password` | 1.00000000 BTC · 10.00000000 ETH |

While signed in, you can top up or overwrite the current user's USD balance and BTC/ETH assets at any time from the demo settings page at [http://localhost:8000/settings/demo](http://localhost:8000/settings/demo). This page is intended for testing and demo purposes only and is not suited for production.

Start the development processes:

```sh
composer run dev
```

When the server is ready, open [http://localhost:8000](http://localhost:8000).

## Tests

```sh
php artisan test --compact
```
