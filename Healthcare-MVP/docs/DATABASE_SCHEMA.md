## 1. tenants

Represents a hospital or clinic in the multi-tenant healthcare system.

Task-013 requirement:
The application must support hospital/clinic-wise data separation.

| Column     | Type            | Constraints                 | Description                            |
| ---------- | --------------- | --------------------------- | -------------------------------------- |
| id         | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique tenant ID                       |
| name       | VARCHAR(150)    | NOT NULL                    | Hospital/clinic name                   |
| type       | VARCHAR(30)     | NOT NULL                    | Tenant type such as hospital or clinic |
| status     | VARCHAR(20)     | NOT NULL                    | Tenant status                          |
| created_at | TIMESTAMP       | NOT NULL                    | Creation timestamp                     |
| updated_at | TIMESTAMP       | NOT NULL                    | Last update timestamp                  |

### Relationships

One tenant can have many:

- Users
- Patients
- Appointments
- Prescriptions
- Staff
- Invoices

The tenant ID must be used to enforce tenant isolation.

## 2. roles

Stores the application's predefined RBAC roles.

Task-013 roles:

- Admin
- Provider (Doctor)
- Nurse
- Patient
- Pharmacist

`Provider` is the canonical RBAC role name for Doctor.
Do not create a separate `Doctor` role.

| Column     | Type            | Constraints                 | Description        |
| ---------- | --------------- | --------------------------- | ------------------ |
| id         | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique role ID     |
| name       | VARCHAR(50)     | NOT NULL, UNIQUE            | Role name          |
| created_at | TIMESTAMP       | NOT NULL                    | Creation timestamp |

### Initial role records

| id  | name       |
| --- | ---------- |
| 1   | Admin      |
| 2   | Provider   |
| 3   | Nurse      |
| 4   | Patient    |
| 5   | Pharmacist |

## 3. users

Stores authenticated users belonging to a tenant.

| Column        | Type            | Constraints                 | Description                |
| ------------- | --------------- | --------------------------- | -------------------------- |
| id            | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique user ID             |
| tenant_id     | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Tenant the user belongs to |
| name          | VARCHAR(150)    | NOT NULL                    | User's name                |
| email         | VARCHAR(255)    | NOT NULL                    | Login email                |
| password_hash | VARCHAR(255)    | NOT NULL                    | Hashed password            |
| status        | VARCHAR(20)     | NOT NULL                    | User account status        |
| created_at    | TIMESTAMP       | NOT NULL                    | Creation timestamp         |
| updated_at    | TIMESTAMP       | NOT NULL                    | Last update timestamp      |

### Relationships

- Each user belongs to one tenant.
- A tenant can have many users.
- Users are connected to roles through `user_roles`.

### Security

Passwords must never be stored as plain text.
Use PHP password hashing and verification.

## 4. user_roles

Maps users to their RBAC roles.

| Column  | Type            | Constraints              | Description |
| ------- | --------------- | ------------------------ | ----------- |
| user_id | BIGINT UNSIGNED | PRIMARY KEY, FOREIGN KEY | User ID     |
| role_id | BIGINT UNSIGNED | PRIMARY KEY, FOREIGN KEY | Role ID     |

### Relationships

users
↓
user_roles
↓
roles

A user can be assigned one or more roles.

## 5. refresh_tokens

Stores hashed refresh tokens for authenticated users.

Task-013 specifies that refresh tokens are stored in the database.

| Column     | Type            | Constraints                 | Description                    |
| ---------- | --------------- | --------------------------- | ------------------------------ |
| id         | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique token record            |
| user_id    | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | User who owns the token        |
| token_hash | VARCHAR(255)    | NOT NULL                    | Hash of refresh token          |
| expires_at | TIMESTAMP       | NOT NULL                    | Token expiration time          |
| created_at | TIMESTAMP       | NOT NULL                    | Creation timestamp             |
| revoked    | BOOLEAN         | NOT NULL DEFAULT FALSE      | Whether token has been revoked |

### Security

The raw refresh token must not be stored in the database.
Only its hash is stored.

Refresh tokens are long-lived and are used to obtain new access tokens.

## 6. patients

Stores patient records belonging to a tenant.

Task-013 requirements:

- Add patient
- Edit patient
- Delete patient
- Encrypted patient medical data
- Tenant-based data access
- Patient appointment linkage
- Soft delete

| Column         | Type            | Constraints                 | Description                    |
| -------------- | --------------- | --------------------------- | ------------------------------ |
| id             | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique patient ID              |
| tenant_id      | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Tenant that owns the patient   |
| user_id        | BIGINT UNSIGNED | NULL, FOREIGN KEY           | Optional linked user account   |
| encrypted_data | TEXT            | NOT NULL                    | Encrypted patient medical data |
| created_at     | TIMESTAMP       | NOT NULL                    | Creation timestamp             |
| updated_at     | TIMESTAMP       | NOT NULL                    | Last update timestamp          |
| deleted_at     | TIMESTAMP       | NULL                        | Soft-delete timestamp          |

### Relationships

- Each patient belongs to one tenant.
- A patient may optionally be linked to a user account.
- A patient can have multiple appointments.
- A patient can have multiple prescriptions.
- A patient can have billing records.

### Security

Patient medical data must be encrypted.

All patient queries must enforce tenant isolation.

Soft-deleted patients must not appear in normal patient queries.

## 7. appointments

Stores appointments between patients and Providers (Doctors).

Task-013 requirements:

- Create appointments
- Update appointments
- Cancel appointments
- Appointment status tracking
- Time-overlap conflict validation
- Upcoming appointment APIs
- Role-based access

| Column       | Type            | Constraints                 | Description                                   |
| ------------ | --------------- | --------------------------- | --------------------------------------------- |
| id           | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique appointment ID                         |
| tenant_id    | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Tenant that owns the appointment              |
| patient_id   | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Patient associated with appointment           |
| provider_id  | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Provider (Doctor) associated with appointment |
| start_at     | DATETIME        | NOT NULL                    | Appointment start time                        |
| end_at       | DATETIME        | NOT NULL                    | Appointment end time                          |
| status       | VARCHAR(30)     | NOT NULL                    | Appointment status                            |
| reason       | TEXT            | NULL                        | Appointment reason                            |
| created_at   | TIMESTAMP       | NOT NULL                    | Creation timestamp                            |
| updated_at   | TIMESTAMP       | NOT NULL                    | Last update timestamp                         |
| cancelled_at | TIMESTAMP       | NULL                        | Cancellation timestamp                        |

### Relationships

- Each appointment belongs to one tenant.
- Each appointment belongs to one patient.
- Each appointment belongs to one Provider (Doctor).
- A patient can have many appointments.
- A Provider can have many appointments.

### Security

Appointment access must respect tenant isolation and role-based access.

The system must reject appointments that overlap with an existing appointment for the same Provider.

## 8. prescriptions

Stores prescriptions created by Providers (Doctors) for patients and handled by Pharmacists.

Task-013 requirements:

- Provider creates prescription
- Pharmacist verifies prescription
- Pharmacist updates status
- Prescription status APIs
- Encrypted prescription data

| Column         | Type            | Constraints                 | Description                                      |
| -------------- | --------------- | --------------------------- | ------------------------------------------------ |
| id             | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique prescription ID                           |
| tenant_id      | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Tenant that owns the prescription                |
| patient_id     | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Patient receiving the prescription               |
| provider_id    | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Provider (Doctor) who created the prescription   |
| pharmacist_id  | BIGINT UNSIGNED | NULL, FOREIGN KEY           | Pharmacist who verifies/handles the prescription |
| encrypted_data | TEXT            | NOT NULL                    | Encrypted prescription information               |
| status         | VARCHAR(30)     | NOT NULL                    | Prescription status                              |
| created_at     | TIMESTAMP       | NOT NULL                    | Creation timestamp                               |
| updated_at     | TIMESTAMP       | NOT NULL                    | Last update timestamp                            |

### Relationships

- Each prescription belongs to one tenant.
- Each prescription belongs to one patient.
- Each prescription is created by one Provider.
- A prescription may be handled by a Pharmacist.
- A patient can have multiple prescriptions.

### Security

Prescription data must be encrypted.

Prescription access must respect tenant isolation and role-based permissions.

Only authorized Providers and Pharmacists should perform the operations allowed by Task-013.

## 9. appointment_notes

Stores notes associated with appointments.

Task-013 requirements:

- Appointment-based notes
- Role-based visibility

| Column            | Type            | Constraints                 | Description                          |
| ----------------- | --------------- | --------------------------- | ------------------------------------ |
| id                | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique note ID                       |
| tenant_id         | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Tenant that owns the note            |
| appointment_id    | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Appointment associated with the note |
| user_id           | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | User who created the note            |
| encrypted_content | TEXT            | NOT NULL                    | Encrypted note content               |
| created_at        | TIMESTAMP       | NOT NULL                    | Creation timestamp                   |
| updated_at        | TIMESTAMP       | NOT NULL                    | Last update timestamp                |

### Relationships

- Each note belongs to one tenant.
- Each note belongs to one appointment.
- Each note is created by one user.
- An appointment can have multiple notes.

### Security

Note visibility must follow the role-based permissions defined for the Communication module.

## 10. messages

Stores messages associated with appointments.

Task-013 requirements:

- Message history APIs
- Encrypted message storage
- Role-based visibility

| Column            | Type            | Constraints                 | Description                             |
| ----------------- | --------------- | --------------------------- | --------------------------------------- |
| id                | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique message ID                       |
| tenant_id         | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Tenant that owns the message            |
| appointment_id    | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Appointment associated with the message |
| sender_id         | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | User who sent the message               |
| receiver_id       | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | User who receives the message           |
| encrypted_content | TEXT            | NOT NULL                    | Encrypted message content               |
| created_at        | TIMESTAMP       | NOT NULL                    | Creation timestamp                      |

### Relationships

- Each message belongs to one tenant.
- Each message belongs to one appointment.
- Each message has one sender.
- Each message has one receiver.
- An appointment can have multiple messages.

### Security

Message content must be encrypted.

Message visibility must follow role-based access rules.

## 11. invoices

Stores invoices generated for patients within a tenant.

Task-013 requirements:

- Generate invoices
- Update payment status
- Pending/Paid summary APIs
- Tenant-based billing data

| Column         | Type            | Constraints                 | Description                        |
| -------------- | --------------- | --------------------------- | ---------------------------------- |
| id             | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique invoice ID                  |
| tenant_id      | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Tenant that owns the invoice       |
| patient_id     | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Patient being billed               |
| appointment_id | BIGINT UNSIGNED | NULL, FOREIGN KEY           | Related appointment, if applicable |
| invoice_number | VARCHAR(50)     | NOT NULL, UNIQUE            | Business invoice reference         |
| amount         | DECIMAL(12,2)   | NOT NULL                    | Invoice amount                     |
| status         | VARCHAR(20)     | NOT NULL                    | Payment/invoice status             |
| created_at     | TIMESTAMP       | NOT NULL                    | Creation timestamp                 |
| updated_at     | TIMESTAMP       | NOT NULL                    | Last update timestamp              |

### Relationships

- Each invoice belongs to one tenant.
- Each invoice belongs to one patient.
- An invoice may be associated with an appointment.
- A patient can have multiple invoices.
- An invoice can have payment records.

### Security

Billing data must respect tenant isolation and role-based access.

## 12. payments

Stores payment records associated with invoices.

| Column     | Type            | Constraints                 | Description                  |
| ---------- | --------------- | --------------------------- | ---------------------------- |
| id         | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique payment ID            |
| invoice_id | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY       | Invoice being paid           |
| amount     | DECIMAL(12,2)   | NOT NULL                    | Payment amount               |
| status     | VARCHAR(20)     | NOT NULL                    | Payment status               |
| paid_at    | TIMESTAMP       | NULL                        | Payment completion timestamp |
| created_at | TIMESTAMP       | NOT NULL                    | Creation timestamp           |

### Relationships

- Each payment belongs to one invoice.
- An invoice can have one or more payment records.

### Tenant isolation

Payment access must be restricted through the tenant ownership of the associated invoice.

## 13. staff

Stores staff members belonging to a tenant.

Task-013 requirements:

- Add staff
- Edit staff
- Delete staff
- Role assignment
- Active/inactive status
- Tenant-based staff segregation

Staff types mentioned in Task-013:

- Doctor
- Nurse
- Receptionist
- Pharmacist

RBAC rule:

- Doctor uses the canonical RBAC role `Provider`.
- Do not create a separate `Doctor` RBAC role.

| Column     | Type            | Constraints                   | Description                    |
| ---------- | --------------- | ----------------------------- | ------------------------------ |
| id         | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT   | Unique staff record ID         |
| tenant_id  | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY         | Tenant the staff belongs to    |
| user_id    | BIGINT UNSIGNED | NOT NULL, FOREIGN KEY, UNIQUE | Associated user account        |
| staff_type | VARCHAR(30)     | NOT NULL                      | Staff classification           |
| status     | VARCHAR(20)     | NOT NULL                      | Active/inactive status         |
| created_at | TIMESTAMP       | NOT NULL                      | Creation timestamp             |
| updated_at | TIMESTAMP       | NOT NULL                      | Last update timestamp          |
| deleted_at | TIMESTAMP       | NULL                          | Optional soft-delete timestamp |

### Relationships

- Each staff record belongs to one tenant.
- Each staff record is associated with one user.
- A user can have one staff record.
- Staff role assignment is handled through the RBAC system.

### Security

Staff access must respect tenant isolation and role-based permissions.
