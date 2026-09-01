# Task-013 Development Guidelines

## 1. Source of Truth

`Task-013.pdf` is the functional source of truth.

Every developer must implement all requirements for their assigned module.

Do not silently remove, simplify, or change requirements.

---

# 2. Architecture

The project must follow:

```text
React
  ↓
API Gateway (index.php)
  ↓
CSRF Validation
  ↓
AES Decryption
  ↓
JWT/Auth Validation
  ↓
Tenant Validation
  ↓
Role Validation
  ↓
Controller
  ↓
Service
  ↓
Repository
  ↓
MySQL
```

Do not create a different architecture for an individual module.

---

# 3. Naming Convention

## PHP Files and Classes

Use PascalCase.

Examples:

```text
PatientController.php
PatientService.php
PatientRepository.php

AppointmentController.php
AppointmentService.php
AppointmentRepository.php
```

## Methods

Use camelCase.

Examples:

```php
createPatient()
getPatient()
updatePatient()
deletePatient()
```

## Database

Use snake_case.

Examples:

```text
patients
tenant_id
patient_id
created_at
updated_at
deleted_at
```

---

# 4. Fixed Role Decision

## Provider = Doctor

`Provider` is the canonical RBAC role name for Doctor.

Always use:

```text
Provider
```

for:

- Database RBAC
- Authorization
- Middleware
- JWT role claims
- PHP constants/enums
- Permission checks

Do not create a separate `Doctor` RBAC role.

Example:

```php
if ($role === 'Provider') {
    // allowed
}
```

Do not use:

```php
if ($role === 'Doctor') {
    // incorrect
}
```

`Doctor` may be used as a staff type or UI label where appropriate.

---

# 5. Shared Components

Use the common project components:

```text
Config/database.php

Security/AES.php
Security/JWT.php
Security/CSRF.php
Security/Hash.php

Middleware/AuthMiddleware.php
Middleware/CsrfMiddleware.php
Middleware/RateLimit.php
```

Do not create duplicate implementations of these responsibilities.

For example, do not create:

```text
JwtAuth.php
EncryptionHelper.php
CsrfHelper.php
AuthenticationMiddleware.php
```

if the existing architecture already provides the required functionality.

---

# 6. Database Rules

Use the agreed database schema.

Do not independently create duplicate tables, columns, or relationships.

All applicable tenant-owned records must respect tenant isolation.

Passwords must never be stored as plain text.

Use:

```php
password_hash()
password_verify()
```

Raw refresh tokens must not be stored in the database.

Store refresh-token hashes.

---

# 7. Git Workflow

Never develop directly on `main`.

Before starting work:

```bash
git checkout main
git pull origin main
```

Create a feature branch:

```bash
git checkout -b feature/<module-name>
```

Examples:

```text
feature/auth-tenant
feature/user-rbac
feature/patient-api
feature/appointment-api
feature/prescription-api
feature/communication-api
feature/staff-api
feature/calendar-api
feature/dashboard-api
feature/billing-api
feature/settings-security
```

---

# 8. Commit Rules

Use meaningful commits.

Examples:

```text
feat(auth): implement login API
feat(patient): implement patient CRUD
feat(appointment): add conflict validation
feat(prescription): add prescription status flow
```

Avoid meaningless messages such as:

```text
update
changes
final
new code
test
```

---

# 9. Pull Request Process

Never push directly to `main`.

Required workflow:

```text
Feature branch
      ↓
Implementation
      ↓
Testing
      ↓
Commit
      ↓
Push
      ↓
Pull Request
      ↓
Code Review
      ↓
Fix review comments
      ↓
Approval
      ↓
Merge into main
```

At least one other developer should review the PR.

---

# 10. Shared Files

Coordinate with the Team Lead before modifying shared files.

Important shared files include:

```text
JWT.php
AES.php
CSRF.php
Hash.php

AuthMiddleware.php
CsrfMiddleware.php

database.php
api.php
public/index.php
```

Do not overwrite another developer's module.

---

# 11. Tenant Security

Never authorize access using only an ID supplied by the client.

Always verify the authenticated user's tenant against the resource's tenant.

Example:

```text
Tenant A User
      ↓
requests Patient
      ↓
Check patient's tenant_id
      ↓
Must belong to Tenant A
```

Tenant A must never access Tenant B data.

---

# 12. Testing

Before opening a Pull Request, test:

- Successful operations
- Validation failures
- Authentication failures
- Authorization failures
- Tenant isolation
- Invalid input
- Invalid IDs
- Database errors
- Relevant security requirements
- Module-specific Task-013 requirements

---

# 13. Requirement Changes

Do not silently change:

- Architecture
- Database
- Roles
- Permissions
- API conventions
- Security
- Encryption
- Authentication
- Task-013 requirements

Discuss changes with the Team Lead first.

---

# 14. Completion Rule

A module is complete only when:

```text
Every applicable Task-013 requirement
        +
Testing
        +
Security
        +
Tenant isolation
        +
RBAC
        +
Correct architecture
```

are completed.
