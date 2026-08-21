# My Stock Project

A Laravel 8 web application for importing stock-screening data, browsing filtered stock lists, maintaining a watch list, and generating AI-assisted stock research reports.

## Features

- Import CSV data into an existing database table or create a new table from a CSV file.
- Browse stock tables, filter records by date, sort columns, and combine duplicate symbols.
- Show a blinking Buy alert for qualifying stocks with sustained presence, high volume, and a positive average change.
- Add individual stocks to the watch list from the Stock List page.
- Sync the latest NSE indicator values from a local stock service, including current price, 9 EMA, 21 EMA, and 30-week EMA.
- Apply a stock-list filter to sync matching rows to the watch list. Existing symbols are updated instead of duplicated.
- View volume-based watch-list summaries for today, this week, this month, two weeks, the current quarter, the last six months, and this year.
- Generate an OpenAI-powered company scorecard report using current web-search results.
- Manage users through the standard Laravel resource routes.

## Technology

- PHP 7.3+ or PHP 8.x
- Laravel 8
- MySQL-compatible database
- Blade templates with Laravel Mix assets
- OpenAI Responses API for reports (optional)
- Local stock indicator service at `http://127.0.0.1:8001` for watch-list syncing

## Setup

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Create and configure the environment file:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Set the database values in `.env`, for example:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=my_stock_project
   DB_USERNAME=your_database_user
   DB_PASSWORD=your_database_password
   ```

3. Create the database and run migrations:

   ```bash
   php artisan migrate
   ```

4. Install and build frontend assets:

   ```bash
   npm install
   npm run dev
   ```

5. Start the application:

   ```bash
   php artisan serve
   ```

   Open `http://127.0.0.1:8000` in a browser.

## Watch-list database requirements

The application stores watch-list data in the existing `whatch_list` table (the spelling is retained for compatibility with the application code).

The table must include these columns:

| Column | Purpose |
| --- | --- |
| `symbol` | NSE stock symbol |
| `price` | Price supplied by the selected stock-list row |
| `current_price` | Latest price from the sync service |
| `9ema` | 9-period EMA |
| `21ema` | 21-period EMA |
| `30wema` | 30-week EMA |
| `created_at`, `updated_at` | Laravel timestamps |

The current database already has the `9ema` column. For a fresh database created from the original watch-list migration, add this column before using the watch-list feature:

```sql
ALTER TABLE whatch_list ADD COLUMN `9ema` DECIMAL(15,2) NULL AFTER current_price;
```

## External services

### Stock sync service

Clicking **Sync** in the Add to Watch List modal calls:

```text
GET http://127.0.0.1:8001/api/v1/stocks?symbol={SYMBOL}&exchange=NSE
```

The service must return a JSON object (or a `data`/`result` wrapper) containing current price, 9 EMA, 21 EMA, and 30-week EMA. The application accepts common field variants such as `9ema`, `ema_9`, `21ema`, `ema_21`, and `30wema`.

Ensure this service is running before using manual sync or **Apply Filter** watch-list syncing.

### OpenAI reports

The Report page requires an OpenAI API key. Add it to `.env`:

```dotenv
OPENAI_API_KEY=your_api_key
OPENAI_MODEL=gpt-5.6-terra
```

The report request uses the OpenAI Responses API with web search to produce an educational company scorecard. It is not personalized financial advice.

## Main pages and routes

| Page | Method and URI | Name |
| --- | --- | --- |
| Dashboard | `GET /` | — |
| Users | Resource `/users` | `users.*` |
| CSV Import | `GET/POST /csv-import` | `csv.import.*` |
| Stock List | `GET /stock-list` | `stock.list.index` |
| Add to Watch List | `POST /stock-list/watch-list` | `stock.list.watch-list.store` |
| Fetch stock indicators | `GET /stock-list/sync` | `stock.list.sync` |
| Watch List | `GET /watch-list` | `watch.list.index` |
| Reports | `GET /report`, `POST /report/generate` | `report.*` |

## CSV import notes

- The first CSV row is used as the column header row.
- When creating a new table, the importer normalizes names and infers basic column types.
- CSVs used by stock-list features should include a `symbol` column. `price`, `close`, `volume`, and `created_at` are used when available.
- The importer uses MySQL table discovery (`SHOW TABLES`), so a MySQL-compatible connection is required.

## Buy alert criteria

The Stock List page displays a **Buy alert** beside a stock when all of the following conditions are met:

- The stock appears in the selected table on at least four consecutive calendar days, including the selected date (or the latest available date when no date is selected).
- Its total volume on that date is greater than `65,000`.
- The average of its historical `chang` values in that table is greater than zero.

For this feature, the source table must contain `symbol`, `volume`, `chang`, and `created_at` columns. Rows with a non-numeric `chang` value are excluded from the average.

## Tests

Run the automated test suite with:

```bash
php artisan test
```

## Useful development commands

```bash
php artisan route:list
php artisan config:clear
php artisan cache:clear
npm run watch
```

## License

This project follows the MIT license declared by its Laravel foundation.


php artisan serve --host=127.0.0.1 --port=8000
