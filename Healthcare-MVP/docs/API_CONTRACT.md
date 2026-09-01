# Task-013 API Contract

## Purpose

Common API conventions for the Healthcare-MVP project.

**Task-013.pdf is the functional source of truth.**

---

## Base URL

All API requests are routed through the Core PHP API Gateway:

```text
backend/public/index.php
```

---

## Standard HTTP Methods

- `GET` — Fetch data
- `POST` — Create data / perform actions
- `PUT` — Update data
- `DELETE` — Delete or soft-delete data where required

---

## Standard Response Format

### Success

```json
{
  "success": true,
  "data": {},
  "message": "Operation successful"
}
```

### Error

```json
{
  "success": false,
  "message": "Error message"
}
```

---

## Authentication

Protected APIs use:

```text
Authorization: Bearer <AccessToken>
```

Access tokens are short-lived and stored in the PHP session.

Refresh tokens are stored in the database.

---

## CSRF

CSRF validation is required for:

- POST
- PUT
- DELETE

CSRF tokens are session-based.

CSRF tokens are regenerated on:

- Application load
- Login
- Token refresh

---

## Encryption

Requests and responses use the common AES implementation.

Request structure:

```json
{
  "csrf_token": "random_string",
  "payload": "ENCRYPTED_DATA"
}
```

Do not implement separate AES logic inside individual modules.

---

## Tenant Isolation

Tenant identity must come from authenticated/trusted context.

Do not trust a frontend-supplied `tenant_id` for authorization.

Every tenant-owned resource must be checked against the authenticated user's tenant.

Tenant A users must never access Tenant B data.

---

# Endpoint Convention

Use plural resource names and `{id}` for resource identifiers.

Example:

```text
GET    /patients
GET    /patients/{id}
POST   /patients
PUT    /patients/{id}
DELETE /patients/{id}
```

---

# Task-013 Endpoint Areas

## Authentication & Tenant

```text
POST /register
POST /login
POST /refresh
POST /logout
```

## Users & Roles

Endpoints must support:

- User management
- Role management
- Role assignment
- Profile operations

## Patients

```text
GET    /patients
GET    /patients/{id}
POST   /patients
PUT    /patients/{id}
DELETE /patients/{id}
```

Must support:

- Add
- Edit
- Delete
- Tenant isolation
- Encrypted medical data
- Appointment linkage
- Soft delete

## Appointments

```text
GET    /appointments
POST   /appointments
PUT    /appointments/{id}
DELETE /appointments/{id}
```

Must support:

- Create
- Update
- Cancel
- Status tracking
- Upcoming appointments
- Time-overlap conflict validation
- Role-based access

## Prescriptions

Endpoints must support:

- Provider prescription creation
- Pharmacist verification
- Prescription status updates
- Prescription status retrieval
- Encrypted prescription data

## Communication

Endpoints must support:

- Appointment-based notes
- Message history
- Encrypted message storage
- Role-based visibility

## Billing

Endpoints must support:

- Invoice generation
- Payment-status updates
- Pending/Paid summaries
- Tenant-based billing data

## Staff

Endpoints must support:

- Add staff
- Edit staff
- Delete staff
- Role assignment
- Active/inactive status
- Tenant-based segregation

## Calendar

Endpoints must support:

- Appointment fetch by date
- Appointment fetch by date range
- Tooltip details
- Role-based appointment visibility

## Settings & Security

Endpoints must support:

- Change password
- Logout
- Session invalidation
- Refresh-token invalidation
- Token rotation
- CSRF regeneration

---

## Important

Exact endpoint details must remain consistent across modules.

If a new endpoint is required, discuss it with the Team Lead before implementing a conflicting alternative.