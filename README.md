# Healthcare MVP – Task 013

A secure, multi-tenant Healthcare Management System built with **React, Core PHP, MySQL, JWT, AES-256 encryption, CSRF protection, RBAC, and GitHub-based team collaboration**.

The system supports multiple hospitals/healthcare organizations using **isolated tenant databases**, while a central Master Database manages tenant registration, provisioning, subscription status, and tenant database configuration.

---

## 📌 Project Overview

The Healthcare MVP provides a modular backend for managing healthcare operations across multiple hospital tenants.

The application includes:

* Tenant registration and provisioning
* Secure authentication
* JWT-based access and refresh tokens
* Role-Based Access Control (RBAC)
* Patient management
* User and role management
* Appointment scheduling
* Prescription and pharmacy management
* Communication and appointment notes
* Calendar
* Billing and payments
* Dashboard and reports
* Security and session management

The system is designed around **tenant isolation**, where each hospital has its own dedicated database.

---

# 🏗️ Architecture

```
                         ┌─────────────────────┐
                         │     React Frontend  │
                         └──────────┬──────────┘
                                    │
                         HTTPS / API Requests
                                    │
                         ┌──────────▼──────────┐
                         │      Core PHP API   │
                         │      index.php      │
                         └──────────┬──────────┘
                                    │
                     ┌──────────────▼──────────────┐
                     │       Security Layer        │
                     │ JWT │ CSRF │ AES │ RBAC     │
                     └──────────────┬──────────────┘
                                    │
                         ┌──────────▼──────────┐
                         │     Controllers     │
                         └──────────┬──────────┘
                                    │
                         ┌──────────▼──────────┐
                         │      Services       │
                         └──────────┬──────────┘
                                    │
                         ┌──────────▼──────────┐
                         │    Repositories     │
                         └──────────┬──────────┘
                                    │
                       ┌────────────┴────────────┐
                       │                         │
                ┌──────▼──────┐          ┌──────▼────────┐
                │ Master DB   │          │ Tenant DB     │
                │             │          │               │
                │ Tenants     │          │ Patients      │
                │ Subscription│          │ Users         │
                │ Billing     │          │ Appointments  │
                │ Metadata    │          │ Prescriptions │
                └─────────────┘          │ Billing       │
                                         │ Messages      │
                                         │ etc.          │
                                         └───────────────┘
```

---

# 🏢 Multi-Tenant Database Architecture

The system uses **database-per-tenant isolation**.

For example:

```text
Master DB
│
├── Tenant: ABC Healthcare
│      └── heal_tenant_1
│
├── Tenant: XYZ Healthcare
│      └── heal_tenant_2
│
└── Tenant: Test Hospital
       └── heal_tenant_3
```

The Master Database stores tenant-level information such as:

* Tenant ID
* Hospital name
* Email
* Subdomain
* Status
* Trial period
* Subscription status
* Tenant database name
* Tenant database host
* Tenant database credentials

Healthcare data is stored inside the respective tenant database.

### Example

```text
testhospital.localhost
        ↓
Master DB
        ↓
Tenant ID = 3
        ↓
heal_tenant_3
```

This prevents one hospital from accessing another hospital's healthcare data.

---

# 🔐 Security Architecture

Security is a major part of Task-013.

The application implements:

### JWT Authentication

Two JWT types are used:

* Access Token
* Refresh Token

Access tokens have a short lifetime, while refresh tokens are used to obtain new access tokens.

Both tokens are stored in **HttpOnly cookies**.

```text
Login
  ↓
Access Token
  +
Refresh Token
  ↓
HttpOnly Cookies
```

---

### Refresh Token Security

Refresh tokens are **never stored as plaintext in the database**.

The refresh token is hashed before being stored:

```text
Refresh Token
      ↓
Hash
      ↓
refresh_tokens table
```

During refresh:

```text
Client Refresh Token
        ↓
Hash
        ↓
Compare with stored hash
        ↓
Valid?
 ┌──────┴──────┐
 Yes           No
 ↓              ↓
Rotate tokens   Reject
```

Old refresh tokens are revoked when token rotation occurs.

---

### CSRF Protection

State-changing requests require a CSRF token.

Example:

```text
X-CSRF-Token: {{csrf_token}}
```

CSRF tokens are regenerated during important authentication events such as login and token refresh.

---

### AES-256 Encryption

Sensitive API request and response payloads use:

**AES-256-CBC**

with:

* AES encryption key from environment configuration
* Random IV
* Base64 encoded encrypted payload
* PHP OpenSSL backend implementation
* CryptoJS client-side implementation

Request flow:

```text
JSON Request
     ↓
AES-256-CBC Encryption
     ↓
{ "payload": "..." }
     ↓
PHP API
     ↓
AES Decryption
     ↓
Application
```

---

### Password Security

Passwords are never stored as plaintext.

Passwords are securely hashed before being stored in the database.

---

### RBAC

The system implements Role-Based Access Control.

Supported roles:

```text
Admin
Provider
Nurse
Patient
Pharmacist
```

`Provider` represents the doctor/provider role internally.

Access to APIs is restricted based on the authenticated user's role.

---

# 🧩 Modules

Task-013 consists of 11 modules.

| Module | Feature                            |
| ------ | ---------------------------------- |
| 01     | Authentication & Tenant Management |
| 02     | User & Role Management             |
| 03     | Patient Management                 |
| 04     | Appointment & Scheduling           |
| 05     | Prescription & Pharmacy            |
| 06     | Dashboard & Reports                |
| 07     | Communication                      |
| 08     | Billing & Payment                  |
| 09     | Staff Management                   |
| 10     | Calendar                           |
| 11     | Settings & Security                |

---

## 01 – Authentication & Tenant Management

Provides:

* Hospital registration
* Tenant validation
* Tenant provisioning
* Dynamic tenant database selection
* Login
* JWT access token generation
* JWT refresh token generation
* Refresh token hashing
* Token rotation
* Logout
* Tenant trial validation
* Subscription status validation

### Tenant Registration Flow

```text
Hospital Registration
        ↓
Validate Email
        ↓
Validate Subdomain
        ↓
Create Tenant in Master DB
        ↓
Create Tenant Database
        ↓
Create Tenant DB User
        ↓
Run Tenant Schema
        ↓
Create Default Admin
        ↓
Update Master DB
        ↓
Tenant Active
```

---

# 02 – User & Role Management

Provides Admin-controlled management of:

* Users
* User roles
* User status
* Role assignment
* User profiles

Supported roles:

```text
Admin
Provider
Nurse
Patient
Pharmacist
```

---

# 03 – Patient Management

Provides:

* Create patient
* View patients
* View individual patient
* Update patient
* Delete patient

Patient records are stored inside the current tenant database.

---

# 04 – Appointment & Scheduling

Provides:

* Create appointments
* Update appointments
* Cancel appointments
* Appointment status management
* Upcoming appointments
* Time-conflict validation
* Role-based access

Appointments are associated with patients and providers within the tenant database.

---

# 05 – Prescription & Pharmacy

Healthcare prescription workflow:

```text
Provider
   ↓
Create Prescription
   ↓
Encrypted Prescription Data
   ↓
Pharmacist
   ↓
Verify / Update Status
```

Provides:

* Prescription creation
* Prescription listing
* Prescription details
* Prescription status
* Pharmacist verification
* Encrypted prescription information

---

# 06 – Dashboard & Reports

Provides aggregated tenant-level information including:

* Total patient count
* Appointment statistics
* Prescription statistics
* Tenant analytics
* Appointment reports
* Prescription reports

Example dashboard data:

```text
Patients
Appointments
Prescriptions
       ↓
   Dashboard
       ↓
 Reports / Analytics
```

---

# 07 – Communication

Provides appointment-based communication functionality:

* Create messages
* Message history
* Appointment notes
* Appointment-level authorization
* Encrypted communication payloads

Messages are associated with appointments and users within the current tenant.

---

# 08 – Billing & Payment

Provides:

* Invoice generation
* Invoice listing
* Invoice details
* Invoice status
* Payment creation
* Payment history
* Billing summary
* Pending amount
* Paid amount
* Tenant-based billing

Example:

```text
Patient
   ↓
Appointment
   ↓
Invoice
   ↓
Payment
   ↓
Billing Summary
```

Billing data is isolated inside each tenant database.

---

# 09 – Staff Management

Provides:

* Create staff
* View staff
* Update staff
* Activate/deactivate staff
* Delete staff

Staff types can include:

```text
Nurse
Receptionist
Other Staff Types
```

The canonical RBAC role for doctors/providers is `Provider`.

---

# 10 – Calendar

Provides appointment-based calendar functionality:

* Appointments by date
* Appointments by date range
* Calendar grid data
* Appointment details for calendar usage

Example:

```text
/api/calendar/date
/api/calendar/range
```

---

# 11 – Settings & Security

Provides security-related account functionality:

* Change password
* Session invalidation
* Refresh-token invalidation
* Refresh-token rotation
* Logout
* CSRF regeneration

When a password is changed:

```text
Change Password
      ↓
Verify Current Password
      ↓
Hash New Password
      ↓
Update Password
      ↓
Revoke Existing Refresh Tokens
```

---

# 🔄 Authentication Flow

## Login

```text
Client
  ↓
Tenant URL
  ↓
Extract Subdomain
  ↓
Master DB
  ↓
Find Active Tenant
  ↓
Get Tenant DB Configuration
  ↓
Connect to Tenant DB
  ↓
Find User
  ↓
Verify Password
  ↓
Load Roles
  ↓
Generate Access + Refresh JWT
  ↓
Store Refresh Token Hash
  ↓
Set HttpOnly Cookies
  ↓
Regenerate CSRF
  ↓
Login Success
```

---

# 🔄 Refresh Token Flow

```text
Client
  ↓
Refresh Token Cookie
  ↓
Validate JWT
  ↓
Validate Tenant
  ↓
Hash Refresh Token
  ↓
Check Database
  ↓
Check Revocation / Expiry
  ↓
Revoke Old Refresh Token
  ↓
Generate New Access Token
  ↓
Generate New Refresh Token
  ↓
Store New Refresh Token Hash
  ↓
Update HttpOnly Cookies
  ↓
Regenerate CSRF
```

This implements **refresh-token rotation**.

---

# 🗄️ Database Structure

## Master Database

The Master DB contains tenant-level information.

Example:

```text
master_db
└── tenants
```

The `tenants` table contains information required to locate and validate each tenant.

---

## Tenant Database

Each hospital gets its own database.

Example:

```text
heal_tenant_3
│
├── users
├── roles
├── user_roles
├── patients
├── staff
├── appointments
├── appointment_notes
├── prescriptions
├── messages
├── invoices
├── payments
└── refresh_tokens
```

The tenant database itself acts as the isolation boundary, so tenant-owned tables do not require a `tenant_id` column.

---

# 📁 Backend Architecture

The backend follows a layered architecture:

```text
backend/
│
├── app/
│   ├── Config/
│   ├── Controllers/
│   ├── Helpers/
│   ├── Middleware/
│   ├── Repositories/
│   ├── Routes/
│   ├── Security/
│   └── Services/
│
├── public/
│   └── index.php
│
├── storage/
│
├── composer.json
└── .env
```

### Request Flow

```text
HTTP Request
     ↓
public/index.php
     ↓
Router
     ↓
Middleware
     ↓
Controller
     ↓
Service
     ↓
Repository
     ↓
Tenant Database / Master Database
     ↓
Response
```

This keeps routing, authorization, business logic, and database operations separated.

---

# 🛠️ Technologies Used

### Backend

* Core PHP
* MySQL
* PDO
* Composer
* Firebase JWT
* OpenSSL

### Frontend (Future Implementation)

* React
* JavaScript
* CryptoJS

### Security

* JWT
* HttpOnly Cookies
* AES-256-CBC
* CSRF Protection
* Password Hashing
* RBAC
* Refresh Token Rotation
* Tenant Isolation

### Development

* Git
* GitHub
* Postman
* WAMP
* MySQL/phpMyAdmin

---

# ⚙️ Installation & Setup

## 1. Clone the Repository

```bash
git clone https://github.com/Joshuanesaraja/Mavericks.git
```

```bash
cd Mavericks/Healthcare-MVP/backend
```

---

## 2. Install PHP Dependencies

```bash
composer install
```

Generate/update Composer autoload files if required:

```bash
composer dump-autoload
```

---

## 3. Environment Configuration

Create your local `.env` file from the example:

```text
.env.example
```

Configure:

```env
DB_HOST=127.0.0.1
DB_PORT=3308
DB_USER=root
DB_PASS=

MASTER_DB_NAME=master_db
EHR_DB_NAME=ehr_db
```

Configure the required:

* JWT secret
* AES encryption key
* Database credentials
* Other environment-specific settings

> **Never commit `.env` or secrets to GitHub.**

---

# ▶️ Running the Backend

From:

```text
Healthcare-MVP/backend
```

run:

```bash
php -S 127.0.0.1:8000 -t public
```

The API will be available at:

```text
http://localhost:8000
```

Tenant APIs use tenant-specific hostnames, for example:

```text
http://testhospital.localhost:8000
```

---

# 🌐 Base URL vs Tenant URL

### Base URL

```text
http://localhost:8000
```

Used primarily for central/landing operations such as tenant registration.

### Tenant URL

```text
http://testhospital.localhost:8000
```

Used for a specific hospital tenant.

The backend extracts:

```text
testhospital
```

from the hostname and uses it to identify the tenant.

---

# 🧪 API Testing

Postman is used for API testing.

The testing environment contains variables such as:

```text
base_url
tenant_url
tenant_id
csrf_token
aes_key
user_id
patient_id
appointment_id
prescription_id
invoice_id
```

Sensitive values should be maintained through the Postman environment rather than hardcoded into requests or scripts.

---

# 🔐 Secure API Request Flow

For authenticated state-changing requests:

```text
Client
  ↓
CSRF Token
  ↓
AES Encryption
  ↓
HttpOnly Access Token Cookie
  ↓
PHP API
  ↓
CSRF Validation
  ↓
AES Decryption
  ↓
JWT Validation
  ↓
Tenant Validation
  ↓
RBAC Validation
  ↓
Controller
  ↓
Service
  ↓
Repository
  ↓
Tenant DB
```

---

# 🔄 Integration Testing Flow

The complete healthcare workflow is tested in the following order:

```text
Login
  ↓
Access Token
  ↓
Create Patient
  ↓
Create Appointment
  ↓
Create Prescription
  ↓
Pharmacist Verifies Prescription
  ↓
Billing / Invoice
  ↓
Dashboard
  ↓
Reports
```

This verifies that the modules work together rather than only testing them individually.

---

# 🛡️ Security Testing

The project includes security validation for:

* Tenant isolation
* JWT validation
* Expired access tokens
* Invalid credentials
* Inactive tenants
* Expired trials
* Refresh-token revocation
* Refresh-token rotation
* Logout
* Password changes
* CSRF validation
* AES encryption/decryption
* RBAC
* HttpOnly token cookies

### Tenant Isolation Example

A JWT belonging to:

```text
testhospital
```

must not be usable against:

```text
abc.localhost
```

The system validates the requested tenant against the tenant information contained in the authenticated token.

---

# 🧪 Testing Results

The implemented modules have been tested through Postman.

### Authentication & Tenant Management

* ✅ Tenant registration
* ✅ Tenant provisioning
* ✅ Tenant-specific login
* ✅ JWT generation
* ✅ HttpOnly cookies
* ✅ Refresh-token hashing
* ✅ Refresh-token rotation
* ✅ Logout
* ✅ Revoked refresh-token rejection
* ✅ Password change
* ✅ Old password rejection
* ✅ Tenant isolation
* ✅ Inactive tenant rejection
* ✅ Expired trial rejection
* ✅ Paid tenant after trial expiry
* ✅ Expired access-token rejection

### User & Role Management

* ✅ Get users
* ✅ Get user
* ✅ Create user
* ✅ Update user
* ✅ Assign role
* ✅ Update user status

### Patient Management

* ✅ Create patient
* ✅ Get patients
* ✅ Get patient
* ✅ Update patient
* ✅ Delete patient
* ✅ Deleted patient verification

### Appointment & Scheduling

* ✅ Appointment creation
* ✅ Appointment retrieval
* ✅ Appointment workflow
* ✅ Appointment authorization
* ✅ Scheduling functionality

### Prescription & Pharmacy

* ✅ Provider creates prescription
* ✅ Encrypted prescription data
* ✅ Pharmacist verification
* ✅ Prescription listing
* ✅ Prescription details
* ✅ Prescription status

### Communication

* ✅ Message creation
* ✅ Message history
* ✅ Appointment notes
* ✅ Appointment authorization
* ✅ Encrypted request/response flow

### Billing

* ✅ Invoice creation
* ✅ Invoice status
* ✅ Payment creation
* ✅ Payment history
* ✅ Billing summary
* ✅ Paid/pending calculation
* ✅ Tenant-based billing

### Staff

* ✅ Staff creation
* ✅ Staff retrieval
* ✅ Staff update
* ✅ Staff status update
* ✅ Staff deletion

### Calendar

* ✅ Calendar by date
* ✅ Calendar by date range

### Dashboard & Reports

* ✅ Dashboard
* ✅ Appointment reports
* ✅ Prescription reports
* ✅ Tenant analytics

### Settings & Security

* ✅ Change password
* ✅ Old password rejection
* ✅ New password authentication
* ✅ Refresh-token invalidation
* ✅ Token rotation
* ✅ Logout
* ✅ CSRF regeneration

---

# 👥 Team Development Workflow

The project follows a Pull Request based GitHub workflow.

```text
main
 │
 ├── Developer 1
 │      ↓
 │    Branch
 │      ↓
 │    Commit
 │      ↓
 │    Push
 │      ↓
 │    Pull Request
 │
 ├── Developer 2
 │      ↓
 │    Branch
 │      ↓
 │    Commit
 │      ↓
 │    Pull Request
 │
 └── Developer 3
        ↓
      Branch
        ↓
      Commit
        ↓
      Pull Request

              ↓
            Review
              ↓
           Approval
              ↓
            Merge
              ↓
             main
              ↓
      Integration Testing
```

---

# 🌿 Git Rules

### Before starting new work

```bash
git switch main
git pull origin main
```

Create a feature branch:

```bash
git switch -c feature/your-feature
```

### Commit

Use meaningful commit messages:

```bash
git add .
git commit -m "Add appointment conflict validation"
```

### Push

```bash
git push -u origin feature/your-feature
```

Then create a Pull Request:

```text
feature/your-feature
        ↓
       main
```

---

# 🚫 Git Rules

The team should **not**:

* Push directly to `main`
* Force push
* Commit `.env`
* Commit passwords
* Commit JWT secrets
* Commit AES keys
* Commit API keys
* Copy another developer's code manually
* Mix unrelated features into one branch

Every logical feature should have its own branch and Pull Request.

---

# 👨‍💻 Development Principles

The project follows these principles:

1. `main` contains the integrated application.
2. Every feature uses its own branch.
3. Every completed feature requires a Pull Request.
4. Pull Requests must be reviewed before merging.
5. Use Controller → Service → Repository architecture.
6. Shared/core files require team coordination.
7. Database access must respect tenant isolation.
8. APIs must follow agreed contracts.
9. Test before creating a Pull Request.
10. Never commit secrets.
11. Fix review comments before merging.
12. Integration testing is performed after merging.
13. Documentation should remain updated.

These rules align with the Task-013 development workflow. 

---

# ✅ Definition of Done

A module is considered complete only after:

```text
Implementation
     ↓
API Testing
     ↓
Security Testing
     ↓
Pull Request
     ↓
Code Review
     ↓
Review Fixes
     ↓
PR Approval
     ↓
Merge
     ↓
Integration Testing
```

This follows the Task-013 definition of completion. 

---

# 🚀 Current Project Status

**Task-013 Backend:** ✅ Implemented

**Pull Request:** ✅ Reviewed and merged

**Main Branch:** ✅ Updated

**Module API Testing:** ✅ Completed

**Security Testing:** ✅ Completed

**Tenant Isolation Testing:** ✅ Completed

**Integration Testing:** 🔄 Final verification

**Frontend Integration:** 🔄 Pending / separate phase

---

# 📌 Important Security Notes

Never commit:

```text
.env
JWT secrets
AES keys
Database passwords
API keys
Production credentials
```

Use:

```text
.env.example
```

for documenting required environment variables without exposing actual secrets.

---

# 📄 Project Structure

```text
Mavericks/
│
├── Healthcare-MVP/
│   │
│   ├── backend/
│   │   ├── app/
│   │   │   ├── Config/
│   │   │   ├── Controllers/
│   │   │   ├── Helpers/
│   │   │   ├── Middleware/
│   │   │   ├── Repositories/
│   │   │   ├── Routes/
│   │   │   ├── Security/
│   │   │   └── Services/
│   │   │
│   │   ├── public/
│   │   │   └── index.php
│   │   │
│   │   ├── storage/
│   │   ├── composer.json
│   │   └── .env.example
│   │
│   ├── database/
│   │   └── schema.sql
│   │
│   └── README.md
│
└── .gitignore
```

---

# 🎯 Final Goal

The Healthcare MVP provides a secure and scalable foundation for a multi-hospital healthcare platform.

The core architectural principle is:

```text
                    MASTER DATABASE
                         │
              Tenant Registration
              Tenant Validation
              Subscription
                         │
          ┌──────────────┼──────────────┐
          ↓              ↓              ↓
     Tenant 1        Tenant 2        Tenant 3
          ↓              ↓              ↓
  heal_tenant_1   heal_tenant_2   heal_tenant_3
          │              │              │
     Healthcare     Healthcare     Healthcare
        Data           Data           Data
```

Each tenant's healthcare data remains isolated within its dedicated database, while the Master Database manages the tenant-level platform information.

---

## 📚 Task-013 Development Reference

The Task-013 preparation specifies the layered API architecture, security requirements, integration flow, GitHub PR workflow, and definition of done used by the team. 

The required security areas include tenant validation, JWT, CSRF, AES, refresh-token storage and rotation, RBAC, and encrypted sensitive data. 

The required team workflow is branch → commit → push → Pull Request → review → merge → `main`. 

---

# 👥 Mavericks Team

**Team:** Mavericks
**Project:** Healthcare MVP
**Task:** Task-013
**Repository:** [Mavericks GitHub Repository](https://github.com/Joshuanesaraja/Mavericks)

---
