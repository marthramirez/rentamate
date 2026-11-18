# Boarding House Management System

A small admin application for boarding house staff. It manages properties, rooms, tenants, room assignments, leases, bills, and payments.

The Vue app talks to a Laravel JSON API. Business rules, including room capacity and payment balances, are enforced in the API.

## Stack

- Frontend: Vue 3, Vue Router, Bootstrap 5, Axios (Vue CLI)
- Backend: Laravel 13, Eloquent, MySQL
- Tests use SQLite in memory

## Setup

### 1. Database

Create a MySQL database that matches `backend/.env`. The included local file uses:

```
DB_CONNECTION=mysql
DB_DATABASE=db_renta
DB_USERNAME=root
DB_PASSWORD=
```

The application timezone defaults to `Asia/Manila` (`APP_TIMEZONE`).

### 2. Backend

```bash
cd backend
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

The API is at `http://127.0.0.1:8000/api`.

`db:seed` loads an administrator, a sample property, rooms, one active tenant, a partial rent payment, and an open water bill. Skip the seed if you want an empty database. Seeding again does not duplicate the sample property, and it keeps `test@example.com` as an active administrator without changing that account's password.

Sign in with:

```
Email: test@example.com
Password: password
```

Change that password after the first sign-in. The sign-in token lasts 14 days and is stored in the browser.

### 3. Frontend

```bash
cd frontend
npm install
npm run serve
```

Open `http://127.0.0.1:8080`. The dev server proxies `/api` to the Laravel server.

For a production build:

```bash
cd frontend
npm run build
```

Set `VUE_APP_API_URL` before building if the API is not served from the same host at `/api`. Example: `VUE_APP_API_URL=http://127.0.0.1:8000/api`. The Vue history router needs the host to fall back to `index.html`.

### 4. Tests

```bash
cd backend
php artisan test
```

## Modules

- Properties: create, edit, and deactivate. A property with rooms is deactivated instead of deleted.
- Rooms: create, edit, and deactivate. Room numbers are unique per property. Occupancy is calculated from assignments.
- Tenants: register, edit, search, and view lease and billing history.
- Room allocation: assign a tenant, reserve a space, move out, transfer to another room, and keep assignment history.
- Leases: draft or active agreements, with the agreed rent stored on the lease.
- Billing and payments: create charges, record partial payments, void a payment, and cancel a bill that has no active payments.
- Users: administrators create and edit sign-in accounts. Staff can use every module except user management.

The dashboard shows the summary counts, then a line chart of charges and collections for the last six months and bar charts for occupancy by property and bills by status.

## Assumptions

- A tenant can have only one lease in `active` status.
- Both `active` and `reserved` assignments consume room capacity. Only `active` assignments count as occupied spaces.
- Creating an active lease also creates the room assignment. A draft lease does not hold a room until it is allocated.
- Move-out marks the assignment completed and, by default, terminates the lease. Uncheck that option only when the same lease will be allocated again.
- Transfer ends the old lease and opens a new lease, so the new agreed rent is its own snapshot.
- Expiring or terminating a lease closes open assignments and keeps bills, payments, and assignment rows.
- Only a draft lease with no history can be cancelled or hard-deleted.
- Overdue is calculated when bills are read. The stored status stays `unpaid` or `partially_paid` until the balance changes. A bill is overdue when the due date is before today and a balance remains.
- Payments are voided with `voided_at` and `void_reason`. They are not deleted. Voided payments are left out of the paid total.
- Money is stored in pesos and shown as PHP. GCash is one of the payment methods.
- Dropdowns load up to 500 records. Lists are paginated.
- Every API route except sign-in requires a bearer token. An inactive account cannot sign in, and deactivating an account revokes its tokens. Accounts are deactivated, not deleted.
- There are two roles. `admin` can manage users. `staff` can use the operational modules. This is not a permission matrix: staff are not limited by property or module.
- The system keeps at least one active administrator. You cannot deactivate your own account.
- An administrator sets another user's password from Users. Anyone signed in can change their own password from Account. There is no email reset.
- A new password signs out that account's other sessions. The session that changed the password stays signed in.

## Main API routes

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/api/login` | Sign in and receive a token |
| GET | `/api/me` | Current account |
| POST | `/api/logout` | Revoke the current token |
| PUT | `/api/account/password` | Change the current account's password |
| GET/POST | `/api/users` | List or create users (administrator) |
| GET/PUT/DELETE | `/api/users/{id}` | View, update, or deactivate a user |
| GET | `/api/dashboard` | Summary counts |
| GET | `/api/analytics` | Monthly charges, collections, occupancy, and bill counts |
| GET/POST | `/api/properties` | List or create |
| GET/PUT/DELETE | `/api/properties/{id}` | View, update, delete or deactivate |
| GET/POST | `/api/rooms` | List, filter, or create |
| GET/PUT/DELETE | `/api/rooms/{id}` | View, update, delete or deactivate |
| GET/POST | `/api/tenants` | List, search, or create |
| GET/PUT/DELETE | `/api/tenants/{id}` | View, update, delete or deactivate |
| GET/POST | `/api/leases` | List or create |
| GET/PUT/DELETE | `/api/leases/{id}` | View, update, or delete an empty draft |
| POST | `/api/leases/{id}/allocate` | Assign the lease's room |
| POST | `/api/leases/{id}/terminate` | Terminate and close occupancy |
| POST | `/api/leases/{id}/expire` | Mark expired and close occupancy |
| POST | `/api/leases/{id}/cancel` | Cancel a draft |
| GET | `/api/room-assignments` | Occupancy history |
| POST | `/api/room-assignments/{id}/move-out` | Complete an assignment |
| POST | `/api/room-assignments/{id}/transfer` | Move the tenant to another room |
| GET/POST | `/api/billing-records` | List or create bills |
| GET | `/api/billing-records/{id}` | Bill with payments and balance |
| POST | `/api/billing-records/{id}/cancel` | Cancel a bill with no active payments |
| POST | `/api/billing-records/{id}/payments` | Record a full or partial payment |
| GET | `/api/payments` | Payment history |
| POST | `/api/payments/{id}/void` | Void a payment and keep the row |
