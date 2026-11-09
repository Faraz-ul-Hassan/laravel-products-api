# Products API

A RESTful CRUD API for managing products, built with Laravel and MySQL.

## Features
- Full CRUD for products (`/api/products`)
- Request validation with Form Requests (clean `422` errors, unique SKU)
- API Resources for consistent JSON output
- Pagination, search, price/active filters, and whitelisted sorting
- JSON error handling for `404` and validation errors
- 19 automated feature tests

## Tech stack
PHP 8.2+, Laravel, MySQL, PHPUnit, Postman

## Setup
```bash
git clone https://github.com/Faraz-ul-Hassan/products-api.git
cd products-api
composer install
cp .env.example .env
php artisan key:generate
# set DB_* values in .env, create the database, then:
php artisan migrate --seed
php artisan serve
```

## Endpoints
| Method | URL | Description |
|---|---|---|
| GET | /api/products | List (query: search, is_active, min_price, max_price, sort_by, sort_dir, per_page) |
| GET | /api/products/{id} | Show one |
| POST | /api/products | Create |
| PUT/PATCH | /api/products/{id} | Update |
| DELETE | /api/products/{id} | Delete |

Send `Accept: application/json` on every request.

## Example
```http
POST /api/products
{ "name": "Keyboard", "sku": "SKU-KEY-100", "price": 49.5, "stock": 20 }
```
Response `201`:
```json
{ "data": { "id": 26, "name": "Keyboard", "sku": "SKU-KEY-100", "price": 49.5, "stock": 20, "is_active": true } }
```

## Testing
```bash
php artisan test
```
A Postman collection is in `docs/products-api.postman_collection.json`.

## Design decisions
- Validation lives in Form Requests, not controllers.
- Responses go through API Resources, so database changes don't break the API contract.
- Sort columns are whitelisted; `per_page` is capped at 100.
