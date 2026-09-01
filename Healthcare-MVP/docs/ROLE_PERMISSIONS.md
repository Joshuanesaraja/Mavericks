# Task-013 Roles & Permissions

## Canonical RBAC Roles

Task-013 defines the following roles:

- Admin
- Provider
- Nurse
- Patient
- Pharmacist

---

# Fixed Team Decision

## Provider = Doctor

`Provider` is the canonical RBAC role name for Doctor.

Use:

```text
Provider
```

for all backend authorization and RBAC checks.

Do not create:

```text
Doctor
```

as a separate RBAC role.

Example:

```text
Provider → Doctor
```

The word `Doctor` may be used as a staff type or UI label where appropriate.

---

# Receptionist

Task-013 mentions Receptionist under Staff and Calendar requirements.

For the current implementation:

```text
Receptionist
```

may be represented as a staff type where required.

Do not create `Receptionist` as a separate RBAC role unless the Team Lead explicitly approves it.

---

# Module Access

| Module                             | Allowed Roles                      |
| ---------------------------------- | ---------------------------------- |
| Authentication & Tenant Management | All                                |
| User & Role Management             | Admin                              |
| Patient Management API             | Provider, Nurse                    |
| Appointment & Scheduling API       | Provider, Nurse, Patient           |
| Prescription & Pharmacy API        | Provider, Pharmacist               |
| Dashboard & Reports API            | Provider, Admin                    |
| Communication (Notes / Basic Chat) | Provider, Nurse                    |
| Billing & Payment API              | Admin, Provider, Patient           |
| Staff Management API               | Admin                              |
| Calendar API                       | Admin, Receptionist, Nurse, Doctor |
| Settings & Security API            | All                                |

---

# Calendar Role Mapping

Task-013 refers to `Doctor` in the Calendar requirements.

Because:

```text
Provider = Doctor
```

the internal RBAC role is:

```text
Provider
```

Therefore, Calendar authorization must map:

```text
Doctor → Provider
```

Do not create a separate Doctor RBAC role.

---

# Authorization Rules

1. Authentication must be validated before protected operations.
2. Role authorization must be enforced through the common middleware/authorization layer.
3. Tenant isolation must always be enforced for tenant-owned data.
4. Having a valid role does not allow access to another tenant's data.
5. Developers must not independently invent new permissions.
6. Any permission change must be discussed with the Team Lead.

---

# Admin

Admin has access to the modules where Task-013 explicitly specifies Admin.

Admin is responsible for:

- User and role management
- Staff management
- Dashboard/report access
- Billing access
- Other explicitly permitted administrative operations

---

# Provider (Doctor)

Provider represents the Doctor role.

The internal role name is:

```text
Provider
```

Provider has access to the modules where Task-013 specifies Provider, including:

- Patient management
- Appointment management
- Prescription creation
- Dashboard/report access
- Communication
- Billing
- Calendar
- Other explicitly permitted Provider operations

---

# Nurse

Nurse has access only to functionality where Task-013 specifies Nurse.

Examples include:

- Patient management
- Appointment management
- Communication
- Calendar

---

# Patient

Patient has access only to functionality where Task-013 specifies Patient.

Examples include:

- Appointments
- Billing
- Their authorized profile/healthcare data

Patient access must always be restricted to authorized patient data.

---

# Pharmacist

Pharmacist has access to the Prescription & Pharmacy functionality specified by Task-013.

Examples:

- Verify prescriptions
- Update prescription status
- Retrieve permitted prescription status information

---

# Important Security Rule

Role validation alone is not sufficient.

Every protected request must consider:

```text
Authentication
      ↓
Tenant
      ↓
Role
      ↓
Resource ownership
```

A user must satisfy all applicable checks before accessing protected data.

---

# Important

If an endpoint requires a role not listed in this document:

**Stop and discuss it with the Team Lead before implementing a new permission model.**
