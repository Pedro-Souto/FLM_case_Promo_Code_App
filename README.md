# Promo Code API Documentation

## Introduction
The Promo Code API provides a robust solution for managing promotional codes with user authentication and admin features. This API allows administrators to create and manage promo codes while enabling users to validate and use them for discounts.

**Base URL:** `http://localhost:8000/api`

**Documentation:** https://documenter.getpostman.com/view/8883476/2sB2qgee3L

**Authentication:** Bearer Token  
**Version:** 1.0.0

---

## Table of Contents
- [Authentication](#authentication)
- [User Management](#user-management-admin-only)
- [Promo Code Management](#promo-code-management-admin-only)
- [Promo Code Usage](#promo-code-usage-user)
- [Error Handling](#error-handling)


---

## Getting Started with Docker (Laravel Sail)

If you want to run this Laravel Promo Code API using Docker, Laravel Sail provides a simple way to set up and manage your local development environment.


## Setting Up Sail for This Project

Follow these steps to run this application using Sail:

### 1. Install Sail

Inside the project directory, require Sail via Composer:

```bash
composer require laravel/sail --dev
```

### 2. Publish Sail's Docker Configuration

Generate the default `docker-compose.yml` and related setup:

```bash
php artisan sail:install
```

> You can choose your stack (e.g., `mysql`, `redis`) when prompted, or manually edit the `docker-compose.yml` afterward.

### 3. Start Docker Containers

Run the containers with:

```bash
./vendor/bin/sail up
```

Or, if you've set up an alias (recommended):

```bash
sail up
```

### 4. (Optional) Set Up a Bash Alias

To make `sail` easier to use, add this alias to your shell configuration (`.bashrc`, `.zshrc`, etc.):

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Then reload your shell:

```bash
source ~/.bashrc
# or
source ~/.zshrc
```

---

## Notes

* Ensure no other services are using ports `80`, `443`, or `3306`. If needed, adjust port mappings in `docker-compose.yml`.
* Once containers are running, you can access the API at:
  **[http://localhost:8000/api](http://localhost:8000/api)**
* Run migrations and seeders (if required):

```bash
sail artisan migrate --seed
```

---


## Authentication
All endpoints (except registration and login) require a Bearer token in the Authorization header.

### Register User
```http
POST /auth/register
```
Creates a new user account.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "is_admin": false
}
```

**Success Response (201 Created):**
```json
{
  "message": "Successfully created user!",
  "accessToken": "1|abc123def456..."
}
```

### Register Admin User
```http
POST /auth/register
```
Creates a new admin account.

**Request Body:**
```json
{
  "name": "Admin User",
  "email": "admin@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "is_admin": true
}
```

### Login
```http
POST /auth/login
```
Authenticates user and returns access token.

**Request Body:**
```json
{
  "email": "admin@example.com",
  "password": "password123"
}
```

**Success Response (200 OK):**
```json
{
  "accessToken": "1|abc123def456...",
  "token_type": "Bearer"
}
```

### Logout
```http
POST /auth/logout
```
Revokes all tokens for the authenticated user.

**Success Response:**
```json
{
  "message": "Successfully logged out"
}
```

---

## User Management (Admin Only)
### Get Current User
```http
GET /auth/user
```
Returns details of the authenticated user.

**Success Response (200 OK):**
```json
{
  "id": 1,
  "name": "Admin User",
  "email": "admin@example.com",
  "is_admin": true
}
```

### Get All Users
```http
GET /auth/users
```
Returns list of all users.

**Success Response (200 OK):**
```json
[
  {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com"
  },
  {
    "id": 2,
    "name": "John Doe",
    "email": "john@example.com"
  }
]
```

---

## Promo Code Management (Admin Only)
### Create Percentage Promo Code
```http
POST /auth/promo-codes
```
Creates a percentage-based promo code.

**Request Body:**
```json
{
  "type": "percentage",
  "value": 20,
  "expiry_date": "2025-12-31 23:59:59",
  "max_usages": 100,
  "max_usages_per_user": 1
}
```

**Success Response (201 Created):**
```json
{
  "message": "Promo code created successfully",
  "promo_code": {
    "id": 1,
    "code": "AUTO123",
    "type": "percentage",
    "value": 20,
    "expiry_date": "2025-12-31 23:59:59",
    "max_usages": 100,
    "max_usages_per_user": 1,
    "is_active": true
  }
}
```

### Create Fixed Value Promo Code
```http
POST /auth/promo-codes
```
Creates a fixed-value promo code with custom code.

**Request Body:**
```json
{
  "code": "SAVE50",
  "type": "value",
  "value": 50,
  "expiry_date": "2025-12-31 23:59:59",
  "max_usages": 50,
  "max_usages_per_user": 2
}
```

### Create Restricted Promo Code
```http
POST /auth/promo-codes
```
Creates a promo code restricted to specific users.

**Request Body:**
```json
{
  "code": "VIP30",
  "type": "percentage",
  "value": 30,
  "expiry_date": "2025-12-31 23:59:59",
  "max_usages": 10,
  "max_usages_per_user": 1,
  "user_ids": [1, 2, 3]
}
```

### Get All Promo Codes
```http
GET /auth/promo-codes
```
Returns all promo codes with user restrictions.

**Success Response (200 OK):**
```json
[
  {
    "id": 1,
    "code": "SAVE50",
    "type": "value",
    "value": 50,
    "expiry_date": "2025-12-31T23:59:59.000000Z",
    "max_usages": 50,
    "max_usages_per_user": 2,
    "is_active": true,
    "users": [
      {"id": 1, "name": "Admin User", "email": "admin@example.com"},
      {"id": 2, "name": "John Doe", "email": "john@example.com"}
    ]
  }
]
```

---

## Promo Code Usage (User)
### Validate Promo Code
```http
POST /auth/promo-codes/validate
```
Validates a promo code and calculates discount (rate limited: 10 requests/min).

**Request Body:**
```json
{
  "price": 100.00,
  "promo_code": "SAVE50"
}
```

**Success Response (200 OK):**
```json
{
  "price": 100.00,
  "promocode_discounted_amount": 50.00,
  "final_price": 50.00
}
```

**Error Responses:**
- `404 Not Found`: Promo code not found/invalid
- `404 Not Found`: Promo code inactive/expired
- `404 Not Found`: Usage limits exceeded

### Use Promo Code (Alternative)
```http
POST /auth/promo-codes/use
```
Marks promo code as used without price calculation.

**Request Body:**
```json
{
  "code": "VIP30"
}
```

**Success Response:**
```json
{
  "message": "Promo code applied successfully",
  "promo_code": {
    "code": "VIP30",
    "type": "percentage",
    "value": 30
  }
}
```

---

## Error Handling
### Common Error Responses

**Rate Limit Exceeded (429 Too Many Requests):**
```json
{
  "message": "Too Many Attempts.",
  "exception": "Illuminate\\Http\\Exceptions\\ThrottleRequestsException"
}
```

**Unauthorized Access (401 Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```

**Validation Errors (422 Unprocessable Content):**
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

### Promo Code Validation Errors
| Error Code                     | Description                                      |
|--------------------------------|--------------------------------------------------|
| `PROMO_CODE_NOT_FOUND`         | Promo code doesn't exist                         |
| `PROMO_CODE_INACTIVE`          | Promo code is disabled                           |
| `PROMO_CODE_EXPIRED`           | Promo code has expired                           |
| `PROMO_CODE_USAGE_LIMIT_EXCEEDED` | Global usage limit reached                     |
| `PROMO_CODE_USER_USAGE_LIMIT_EXCEEDED` | User-specific usage limit reached          |
| `PROMO_CODE_NOT_AVAILABLE_FOR_USER` | User not authorized to use this promo code   |




## Test Suite Results

### Command

```bash
php artisan test
```

---

### `Tests\Unit\ExampleTest`

* ✅ that true is true

---

### `Tests\Unit\PromoCodeModelTest`

* ✅ promo code uses provided code
* ✅ promo code has correct default values
* ✅ can be used by user returns true for global promo
* ✅ can be used by user returns true for specific user
* ✅ can be used by user returns false for restricted promo
* ✅ find by code cached returns correct promo
* ✅ find by code cached returns null for nonexistent
* ✅ record usage increments counters

---

### `Tests\Unit\UserModelTest`

* ✅ user has correct fillable attributes
* ✅ user password is hidden

---

### `Tests\Feature\AuthTest`

* ✅ user can register
* ✅ user can login
* ✅ user cannot login with invalid credentials
* ✅ authenticated user can logout
* ✅ admin can get profile
* ✅ non admin cannot get profile
* ✅ admin can get all users
* ✅ non admin cannot get all users
* ✅ registration requires valid data
* ✅ registration prevents duplicate email
* ✅ admin user can be created
* ✅ login requires email and password
* ✅ unauthenticated user cannot access protected routes

---

### `Tests\Feature\ExampleTest`

* ✅ the application returns a successful response

---

### `Tests\Feature\PromoCodeTest`

* ✅ admin can create percentage promo code
* ✅ admin can create value promo code
* ✅ non admin cannot create promo code
* ✅ admin can list promo codes
* ✅ user can validate active percentage promo code
* ✅ user can validate active value promo code
* ✅ user cannot validate inactive promo code
* ✅ user cannot validate expired promo code
* ✅ user cannot validate nonexistent promo code
* ✅ validation requires authentication
* ✅ percentage promo code cannot exceed 100 percent
* ✅ promo code creation validates required fields

---

### 📊 Summary

* **Tests Passed:** 37
* **Assertions:** 83
* **Duration:** 0.54s

---