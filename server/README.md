<h1 align="center">EduPay</h1>

<p align="center">
  Backend API for managing school bills, payments, payment cancellations, and refunds in a single system that is orderly, transparent, and auditable.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PostgreSQL-14%2B-4169E1?style=for-the-badge&logo=postgresql&logoColor=white" alt="PostgreSQL 14+">
  <img src="https://img.shields.io/badge/Auth-Laravel%20Sanctum-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel Sanctum">
  <img src="https://img.shields.io/badge/Excel-Maatwebsite-217346?style=for-the-badge&logo=microsoftexcel&logoColor=white" alt="Maatwebsite Excel">
</p>

## Table of Contents

- [About the Project](#about-the-project)
- [Key Features](#key-features)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation and Running the Project](#installation-and-running-the-project)
- [API Documentation](#api-documentation)

## About the Project

**EduPay** is a backend API that helps school administrative staff manage the student financial lifecycle, from issuing bills and recording payments to verification, payment cancellation, and refund requests and completion.

The project is designed to reduce manual record-keeping spread across multiple files, keep bill balances consistent, and provide an audit trail for sensitive financial actions. The current implementation focuses on a **backend MVP for a single school**, with a data design that can be extended for a multi-school foundation in the future.

## Key Features

- **Student management** — student CRUD, search by name/NIS/NISN/internal ID, status and academic year filters, active class management, and Excel import.
- **Bill issuance** — bulk bill preview before issuance, per-student bill issuance, student/class/grade-level snapshots, and duplicate prevention by student, bill type, period, and academic year.
- **Bill discounts** — fixed-amount discounts with a required reason, validation that the discount does not exceed the original amount, and audit log entries for every change.
- **Payment recording** — cash, bank transfer, and QRIS payments; cash payments are verified immediately, while bank transfers and QRIS payments enter the verification queue.
- **Payment allocation** — one payment can be allocated to one or more bills, with validation for bill ownership and total allocation.
- **Payment verification** — approve or reject payments awaiting verification, including verification notes.
- **Separation of duties** — the user who records a payment cannot verify that payment; the requester of a refund or cancellation cannot approve their own request.
- **Payment cancellation** — cancellation requests, approval/rejection, bill balance restoration, and transaction history retention.
- **Refunds** — draft creation, approval submission, approval/rejection, and refund completion with a disbursement reference.
- **Audit log** — records the user, time, action, subject, old/new values, reason, IP address, and user agent for sensitive actions.
- **Period protection** — bill and payment transactions are rejected when the related financial period has been closed.

> Note: this repository focuses on the backend API. Login/token endpoints, dashboards, reports, bank/QRIS reconciliation, PDF payment receipts, and notifications are not included in the current implementation scope.

## Tech Stack

| Component      | Technology              | Description                                         |
| -------------- | ----------------------- | --------------------------------------------------- |
| Language       | PHP 8.2+                | Application runtime                                 |
| Framework      | Laravel 12              | Routing, validation, ORM, queues, and testing       |
| Authentication | Laravel Sanctum 4       | API tokens for protected endpoints                  |
| Database       | PostgreSQL 14+          | Supports `jsonb` and `ilike` search                 |
| Excel Import   | Maatwebsite Excel 4     | Student imports from `.xlsx`, `.xls`, and `.csv`    |
| Testing        | PHPUnit 11              | Unit and feature tests                              |
| Code Style     | Laravel Pint            | Laravel-standard formatter                          |
| Asset Tooling  | Vite 7 + Tailwind CSS 4 | Frontend asset build (optional for the backend API) |

## Installation and Running the Project

### 1. Clone the repository

```bash
git clone <repository-url>
cd EduPay/server
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Prepare the environment file

Linux/macOS:

```bash
cp .env.example .env
php artisan key:generate
```

Windows (Command Prompt/PowerShell):

```powershell
copy .env.example .env
php artisan key:generate
```

### 4. Configure the database

This project uses PostgreSQL. Adjust the following variables in `.env`:

```dotenv
APP_NAME=EduPay
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=db_edupay
DB_USERNAME=postgres
DB_PASSWORD=your_password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Create the database if it does not exist yet:

```bash
createdb -U postgres db_edupay
```

Alternative using `psql`:

```sql
CREATE DATABASE db_edupay;
```

> Do not commit the `.env` file. It is already excluded through `.gitignore`.

### 5. Run migrations

```bash
php artisan migrate
```

### 6. Start the server

```bash
php artisan serve
```

The API is available at `http://127.0.0.1:8000/api`.

If you need frontend assets or the full development mode:

```bash
npm install
npm run dev
```

Or run the server, queue listener, log viewer, and Vite together:

```bash
composer run dev
```

### 7. Run tests

```bash
php artisan test
```

Or through the Composer script:

```bash
composer test
```

### 8. Create a local access token

All API endpoints are protected by the `auth:sanctum` middleware. For local testing, create a role, user, and token through Tinker:

```bash
php artisan tinker
```

```php
$role = App\Models\Role::firstOrCreate(
    ['name' => 'admin'],
    ['display_name' => 'Administrator']
);

$user = App\Models\User::firstOrCreate(
    ['email' => 'admin@edupay.test'],
    [
        'role_id' => $role->id,
        'name' => 'Admin EduPay',
        'password' => 'password',
    ]
);

echo $user->createToken('local')->plainTextToken;
```

Use the token in the request header:

```http
Authorization: Bearer <TOKEN>
Accept: application/json
```

## API Documentation

**Base URL:** `http://127.0.0.1:8000/api`

All endpoints require the `Authorization: Bearer <TOKEN>` and `Accept: application/json` headers.

### Bills

| Method | Endpoint                   | Description                                          |
| ------ | -------------------------- | ---------------------------------------------------- |
| `POST` | `/bills/preview`           | Generate a bulk bill preview without persisting data |
| `POST` | `/bills`                   | Issue bills for one or more students                 |
| `POST` | `/bills/{billId}/discount` | Apply a discount to a bill                           |

### Payments

| Method | Endpoint                       | Description                               |
| ------ | ------------------------------ | ----------------------------------------- |
| `POST` | `/payments`                    | Record a payment and its bill allocations |
| `POST` | `/payments/{paymentId}/verify` | Verify a payment awaiting verification    |
| `POST` | `/payments/{paymentId}/reject` | Reject a payment verification             |
| `POST` | `/payments/{paymentId}/cancel` | Request a payment cancellation            |

### Payment Cancellations

| Method | Endpoint                                          | Description                                         |
| ------ | ------------------------------------------------- | --------------------------------------------------- |
| `POST` | `/payment-cancellations/{cancellationId}/approve` | Approve a cancellation and restore the bill balance |
| `POST` | `/payment-cancellations/{cancellationId}/reject`  | Reject a cancellation request                       |

### Refunds

| Method | Endpoint                       | Description                  |
| ------ | ------------------------------ | ---------------------------- |
| `POST` | `/refunds`                     | Create a refund draft        |
| `POST` | `/refunds/{refundId}/submit`   | Submit a refund for approval |
| `POST` | `/refunds/{refundId}/approve`  | Approve a refund             |
| `POST` | `/refunds/{refundId}/reject`   | Reject a refund              |
| `POST` | `/refunds/{refundId}/complete` | Mark a refund as completed   |

### Students

| Method | Endpoint                            | Description                                        |
| ------ | ----------------------------------- | -------------------------------------------------- |
| `GET`  | `/students`                         | List students with search, filters, and pagination |
| `POST` | `/students`                         | Create a student                                   |
| `POST` | `/students/import`                  | Import students from an Excel/CSV file             |
| `GET`  | `/students/{student}`               | Show student details                               |
| `PUT`  | `/students/{student}`               | Update student data                                |
| `POST` | `/students/{student}/change-status` | Change a student's status with a reason            |

### Example request

```bash
curl --request POST \
  --url http://127.0.0.1:8000/api/bills/preview \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer <TOKEN>' \
  --header 'Content-Type: application/json' \
  --data '{
    "bill_type_id": 1,
    "academic_year_id": 1,
    "period_label": "2026-10",
    "student_ids": [1, 2]
  }'
```

> OpenAPI/Swagger documentation and a Postman collection are not included yet. Use the endpoint tables above as an initial reference.
