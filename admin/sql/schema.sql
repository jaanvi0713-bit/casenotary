-- Notary Management System - Database Schema
-- Run this in MySQL (phpMyAdmin or CLI)

CREATE DATABASE IF NOT EXISTS notary_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE notary_management;

-- ============================================================
-- USERS (Admin & Client accounts)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('admin', 'client') NOT NULL DEFAULT 'client',
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    phone           VARCHAR(30) DEFAULT NULL,
    avatar          VARCHAR(255) DEFAULT NULL,
    status          ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME DEFAULT NULL,
    last_login      DATETIME DEFAULT NULL,
    remember_token  VARCHAR(100) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- CLIENTS (Extended profile for client-role users)
-- ============================================================
CREATE TABLE IF NOT EXISTS clients (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    company_name    VARCHAR(255) DEFAULT NULL,
    address         TEXT DEFAULT NULL,
    city            VARCHAR(100) DEFAULT NULL,
    state           VARCHAR(100) DEFAULT NULL,
    zip_code        VARCHAR(20) DEFAULT NULL,
    country         VARCHAR(100) DEFAULT 'USA',
    notes           TEXT DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- CASES
-- ============================================================
CREATE TABLE IF NOT EXISTS cases (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number     VARCHAR(50) NOT NULL UNIQUE,
    title           VARCHAR(255) NOT NULL,
    description     TEXT DEFAULT NULL,
    service_type    VARCHAR(150) NOT NULL,
    service_fee     DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    services        JSON DEFAULT NULL,
    client_id       INT UNSIGNED NOT NULL,
    assigned_admin_id INT UNSIGNED DEFAULT NULL,
    priority        ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    deadline        DATE DEFAULT NULL,
    status          ENUM('pending', 'in_progress', 'waiting_for_client', 'completed', 'closed') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_cases_status (status),
    INDEX idx_cases_client (client_id),
    INDEX idx_cases_deadline (deadline)
) ENGINE=InnoDB;

-- ============================================================
-- DOCUMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS documents (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id         INT UNSIGNED DEFAULT NULL,
    uploaded_by     INT UNSIGNED NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    original_name   VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    file_type       VARCHAR(50) NOT NULL,
    file_size       INT UNSIGNED NOT NULL DEFAULT 0,
    mime_type       VARCHAR(100) DEFAULT NULL,
    description     TEXT DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_documents_case (case_id)
) ENGINE=InnoDB;

-- ============================================================
-- INVOICES
-- ============================================================
CREATE TABLE IF NOT EXISTS invoices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number  VARCHAR(50) NOT NULL UNIQUE,
    case_id         INT UNSIGNED DEFAULT NULL,
    client_id       INT UNSIGNED NOT NULL,
    amount          DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_rate        DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    tax_amount      DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    total           DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    status          ENUM('pending', 'paid', 'partially_paid', 'overdue') NOT NULL DEFAULT 'pending',
    due_date        DATE NOT NULL,
    notes           TEXT DEFAULT NULL,
    pdf_path        VARCHAR(500) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_client (client_id)
) ENGINE=InnoDB;

-- ============================================================
-- PAYMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id      INT UNSIGNED NOT NULL,
    amount          DECIMAL(12, 2) NOT NULL,
    payment_method  ENUM('stripe', 'cash', 'check', 'bank_transfer', 'other') NOT NULL DEFAULT 'stripe',
    stripe_payment_id VARCHAR(255) DEFAULT NULL,
    status          ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    paid_at         DATETIME DEFAULT NULL,
    notes           TEXT DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_payments_invoice (invoice_id)
) ENGINE=InnoDB;

-- ============================================================
-- RECEIPTS
-- ============================================================
CREATE TABLE IF NOT EXISTS receipts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_number  VARCHAR(50) NOT NULL UNIQUE,
    payment_id      INT UNSIGNED NOT NULL,
    invoice_id      INT UNSIGNED NOT NULL,
    client_id       INT UNSIGNED NOT NULL,
    amount          DECIMAL(12, 2) NOT NULL,
    pdf_path        VARCHAR(500) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- APPOINTMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS appointments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id         INT UNSIGNED DEFAULT NULL,
    client_id       INT UNSIGNED NOT NULL,
    admin_id        INT UNSIGNED DEFAULT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT DEFAULT NULL,
    start_time      DATETIME NOT NULL,
    end_time        DATETIME NOT NULL,
    location        VARCHAR(255) DEFAULT NULL,
    status          ENUM('requested', 'scheduled', 'confirmed', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled',
    google_event_id VARCHAR(255) DEFAULT NULL,
    outlook_event_id VARCHAR(255) DEFAULT NULL,
    reminder_sent   TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_appointments_start (start_time),
    INDEX idx_appointments_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    type            ENUM('invoice', 'payment', 'appointment', 'document', 'case', 'account', 'system') NOT NULL DEFAULT 'system',
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    link            VARCHAR(500) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_read (is_read)
) ENGINE=InnoDB;

-- ============================================================
-- COMPANY SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS company_settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name    VARCHAR(255) NOT NULL DEFAULT 'Your Company',
    logo            VARCHAR(500) DEFAULT NULL,
    favicon         VARCHAR(500) DEFAULT NULL,
    primary_color   VARCHAR(7) NOT NULL DEFAULT '#3aafa9',
    secondary_color VARCHAR(7) NOT NULL DEFAULT '#00182c',
    dark_accent     VARCHAR(7) NOT NULL DEFAULT '#000000',
    font_family     VARCHAR(100) NOT NULL DEFAULT 'Montserrat',
    description     TEXT DEFAULT NULL,
    office_email    VARCHAR(255) DEFAULT NULL,
    office_phone    VARCHAR(30) DEFAULT NULL,
    business_hours  TEXT DEFAULT NULL,
    address         TEXT DEFAULT NULL,
    company_website VARCHAR(500) DEFAULT NULL,
    registration_number VARCHAR(100) DEFAULT NULL,
    tax_vat_number  VARCHAR(100) DEFAULT NULL,
    facebook_url    VARCHAR(500) DEFAULT NULL,
    instagram_url   VARCHAR(500) DEFAULT NULL,
    linkedin_url    VARCHAR(500) DEFAULT NULL,
    smtp_host       VARCHAR(255) DEFAULT NULL,
    smtp_port       INT UNSIGNED DEFAULT 587,
    smtp_username   VARCHAR(255) DEFAULT NULL,
    smtp_password   VARCHAR(255) DEFAULT NULL,
    smtp_encryption ENUM('tls', 'ssl', 'none') DEFAULT 'tls',
    stripe_public_key VARCHAR(255) DEFAULT NULL,
    stripe_secret_key VARCHAR(255) DEFAULT NULL,
    google_calendar_id VARCHAR(255) DEFAULT NULL,
    outlook_calendar_id VARCHAR(255) DEFAULT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- PASSWORD RESETS
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL,
    token           VARCHAR(255) NOT NULL,
    expires_at      DATETIME NOT NULL,
    used            TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_resets_email (email),
    INDEX idx_password_resets_token (token)
) ENGINE=InnoDB;

-- ============================================================
-- AUDIT LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    action          VARCHAR(100) NOT NULL,
    entity_type     VARCHAR(50) DEFAULT NULL,
    entity_id       INT UNSIGNED DEFAULT NULL,
    ip_address      VARCHAR(45) DEFAULT NULL,
    user_agent      VARCHAR(500) DEFAULT NULL,
    details         JSON DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- PROPOSALS & QUOTATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS proposals (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id         INT UNSIGNED NOT NULL,
    proposal_number VARCHAR(50) NOT NULL UNIQUE,
    title           VARCHAR(255) NOT NULL,
    content         TEXT NOT NULL,
    amount          DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    status          ENUM('draft', 'sent', 'accepted', 'rejected') NOT NULL DEFAULT 'draft',
    pdf_path        VARCHAR(500) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS quotations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id         INT UNSIGNED NOT NULL,
    quotation_number VARCHAR(50) NOT NULL UNIQUE,
    title           VARCHAR(255) NOT NULL,
    line_items      JSON NOT NULL,
    subtotal        DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    tax_rate        DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    total           DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    status          ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'draft',
    valid_until     DATE DEFAULT NULL,
    pdf_path        VARCHAR(500) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB;
