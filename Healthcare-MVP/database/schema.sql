-- =========================================================
-- TASK-013 HEALTHCARE MVP
-- TWO DATABASE ARCHITECTURE
-- =========================================================
--
-- master_db
--   -> Central tenant registry
--
-- ehr_db
--   -> Shared healthcare database
--   -> All tenant-owned data contains tenant_id
--
-- IMPORTANT:
-- There are NO cross-database foreign keys.
-- tenant_id in ehr_db is validated by PHP against master_db.
-- =========================================================


-- =========================================================
-- 1. MASTER DATABASE
-- =========================================================

CREATE DATABASE IF NOT EXISTS master_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE master_db;


-- =========================================================
-- TENANTS
-- =========================================================

CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================================================
-- INITIAL TENANTS
-- =========================================================
--
-- Tenants are created/managed in master_db.
-- Application registration does NOT create tenants.
-- =========================================================

INSERT INTO tenants (
    tenant_code,
    name,
    status
)
VALUES
    ('TENANT001', 'ABC Healthcare', 'active'),
    ('TENANT002', 'XYZ Healthcare', 'active')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = VALUES(status);


-- =========================================================
-- 2. SHARED EHR DATABASE
-- =========================================================

CREATE DATABASE IF NOT EXISTS ehr_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ehr_db;


-- =========================================================
-- 1. ROLES
-- =========================================================

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================================================
-- 2. USERS
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_users_tenant_id (tenant_id)
) ENGINE=InnoDB;


-- =========================================================
-- 3. USER ROLES
-- =========================================================

CREATE TABLE IF NOT EXISTS user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,

    PRIMARY KEY (user_id, role_id),

    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id)
        REFERENCES roles(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =========================================================
-- 4. REFRESH TOKENS
-- =========================================================

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked BOOLEAN NOT NULL DEFAULT FALSE,

    CONSTRAINT fk_refresh_tokens_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_refresh_tokens_user_id (user_id),
    INDEX idx_refresh_tokens_tenant_id (tenant_id),
    INDEX idx_refresh_tokens_expires_at (expires_at),
    INDEX idx_refresh_tokens_revoked (revoked)
) ENGINE=InnoDB;


-- =========================================================
-- 5. PATIENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS patients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    encrypted_data TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    CONSTRAINT fk_patients_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_patients_tenant_id (tenant_id),
    INDEX idx_patients_user_id (user_id),
    INDEX idx_patients_deleted_at (deleted_at)
) ENGINE=InnoDB;


-- =========================================================
-- 6. APPOINTMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS appointments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    provider_id BIGINT UNSIGNED NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL,
    reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,

    CONSTRAINT fk_appointments_patient
        FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_appointments_provider
        FOREIGN KEY (provider_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_appointments_tenant_id (tenant_id),
    INDEX idx_appointments_patient_id (patient_id),
    INDEX idx_appointments_provider_id (provider_id),
    INDEX idx_appointments_start_at (start_at),
    INDEX idx_appointments_status (status)
) ENGINE=InnoDB;


-- =========================================================
-- 7. PRESCRIPTIONS
-- =========================================================

CREATE TABLE IF NOT EXISTS prescriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    provider_id BIGINT UNSIGNED NOT NULL,
    pharmacist_id BIGINT UNSIGNED NULL,
    encrypted_data TEXT NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_prescriptions_patient
        FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_prescriptions_provider
        FOREIGN KEY (provider_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_prescriptions_pharmacist
        FOREIGN KEY (pharmacist_id)
        REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_prescriptions_tenant_id (tenant_id),
    INDEX idx_prescriptions_patient_id (patient_id),
    INDEX idx_prescriptions_provider_id (provider_id),
    INDEX idx_prescriptions_status (status)
) ENGINE=InnoDB;


-- =========================================================
-- 8. APPOINTMENT NOTES
-- =========================================================

CREATE TABLE IF NOT EXISTS appointment_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    encrypted_content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_notes_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_notes_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_notes_tenant_id (tenant_id),
    INDEX idx_notes_appointment_id (appointment_id),
    INDEX idx_notes_user_id (user_id)
) ENGINE=InnoDB;


-- =========================================================
-- 9. MESSAGES
-- =========================================================

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    receiver_id BIGINT UNSIGNED NOT NULL,
    encrypted_content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_messages_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_messages_sender
        FOREIGN KEY (sender_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_messages_receiver
        FOREIGN KEY (receiver_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_messages_tenant_id (tenant_id),
    INDEX idx_messages_appointment_id (appointment_id),
    INDEX idx_messages_sender_id (sender_id),
    INDEX idx_messages_receiver_id (receiver_id)
) ENGINE=InnoDB;


-- =========================================================
-- 10. INVOICES
-- =========================================================

CREATE TABLE IF NOT EXISTS invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NULL,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_invoices_patient
        FOREIGN KEY (patient_id)
        REFERENCES patients(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_invoices_appointment
        FOREIGN KEY (appointment_id)
        REFERENCES appointments(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    INDEX idx_invoices_tenant_id (tenant_id),
    INDEX idx_invoices_patient_id (patient_id),
    INDEX idx_invoices_status (status)
) ENGINE=InnoDB;


-- =========================================================
-- 11. PAYMENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_payments_invoice
        FOREIGN KEY (invoice_id)
        REFERENCES invoices(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_payments_tenant_id (tenant_id),
    INDEX idx_payments_invoice_id (invoice_id),
    INDEX idx_payments_status (status)
) ENGINE=InnoDB;


-- =========================================================
-- 12. STAFF
-- =========================================================

CREATE TABLE IF NOT EXISTS staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    staff_type VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,

    CONSTRAINT fk_staff_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    INDEX idx_staff_tenant_id (tenant_id),
    INDEX idx_staff_status (status),
    INDEX idx_staff_deleted_at (deleted_at)
) ENGINE=InnoDB;


-- =========================================================
-- INITIAL RBAC ROLES
-- =========================================================
--
-- Provider = Doctor
--
-- Canonical RBAC roles:
-- Admin
-- Provider
-- Nurse
-- Patient
-- Pharmacist
-- =========================================================

INSERT INTO roles (name)
VALUES
    ('Admin'),
    ('Provider'),
    ('Nurse'),
    ('Patient'),
    ('Pharmacist')
ON DUPLICATE KEY UPDATE
    name = VALUES(name);