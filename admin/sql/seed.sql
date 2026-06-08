-- Notary Management System - Seed Data
-- Default admin: admin@admin.com / admin123

USE notary_management;

-- Company settings with branding
INSERT INTO company_settings (
    company_name, primary_color, secondary_color, dark_accent,
    font_family, description, office_email, office_phone
) VALUES (
    'Premier Notary Services',
    '#3aafa9',
    '#00182c',
    '#000000',
    'Montserrat',
    'Professional notary and legal document services.',
    'info@premiernotary.com',
    '+1 (555) 123-4567'
);

-- Default admin user (password: admin123)
INSERT INTO users (email, password, role, first_name, last_name, phone, status)
VALUES (
    'admin@admin.com',
    '$2y$10$pjAbMHgW7P621hNTLaAP..AnVdnx6EWDig26cQHn0jqt/nBT.g.aW',
    'admin',
    'Admin',
    'User',
    '+1 (555) 000-0001',
    'active'
);

-- Sample client users (password: Client@123)
INSERT INTO users (email, password, role, first_name, last_name, phone, status) VALUES
('john.smith@email.com', '$2y$10$SSDKvQ0qFi9jFgOsXRTaDeqpyzThYrxQfjM2cDOSqP4pP/8oTnKpm', 'client', 'John', 'Smith', '+1 (555) 111-2222', 'active'),
('sarah.johnson@email.com', '$2y$10$SSDKvQ0qFi9jFgOsXRTaDeqpyzThYrxQfjM2cDOSqP4pP/8oTnKpm', 'client', 'Sarah', 'Johnson', '+1 (555) 333-4444', 'active'),
('michael.brown@email.com', '$2y$10$SSDKvQ0qFi9jFgOsXRTaDeqpyzThYrxQfjM2cDOSqP4pP/8oTnKpm', 'client', 'Michael', 'Brown', '+1 (555) 555-6666', 'active');

INSERT INTO clients (user_id, company_name, address, city, state, zip_code, country) VALUES
(2, 'Smith Enterprises LLC', '123 Business Ave', 'New York', 'NY', '10001', 'USA'),
(3, 'Johnson Legal Group', '456 Court Street', 'Los Angeles', 'CA', '90001', 'USA'),
(4, NULL, '789 Main Road', 'Chicago', 'IL', '60601', 'USA');

-- Sample cases
INSERT INTO cases (case_number, title, description, service_type, service_fee, client_id, assigned_admin_id, priority, deadline, status) VALUES
('CASE-2026-001', 'Property Deed Notarization', 'Notarization of residential property deed transfer.', 'Real Estate Notarization', 350.00, 1, 1, 'high', '2026-06-15', 'in_progress'),
('CASE-2026-002', 'Power of Attorney Document', 'Draft and notarize power of attorney for healthcare decisions.', 'Legal Document Notarization', 275.00, 2, 1, 'medium', '2026-06-20', 'pending'),
('CASE-2026-003', 'Affidavit of Identity', 'Identity verification affidavit for court proceedings.', 'Affidavit Services', 150.00, 3, 1, 'low', '2026-06-10', 'waiting_for_client'),
('CASE-2026-004', 'Business Contract Notarization', 'Multi-party business agreement notarization.', 'Corporate Notarization', 500.00, 1, 1, 'urgent', '2026-06-05', 'in_progress'),
('CASE-2026-005', 'Loan Document Signing', 'Mortgage loan document signing and notarization.', 'Loan Signing', 425.00, 2, 1, 'high', '2026-05-30', 'completed');

-- Sample invoices
INSERT INTO invoices (invoice_number, case_id, client_id, amount, tax_rate, tax_amount, total, status, due_date) VALUES
('INV-2026-001', 1, 1, 350.00, 8.00, 28.00, 378.00, 'paid', '2026-05-15'),
('INV-2026-002', 2, 2, 275.00, 8.00, 22.00, 297.00, 'pending', '2026-06-15'),
('INV-2026-003', 3, 3, 150.00, 8.00, 12.00, 162.00, 'overdue', '2026-05-01'),
('INV-2026-004', 4, 1, 500.00, 8.00, 40.00, 540.00, 'partially_paid', '2026-06-01'),
('INV-2026-005', 5, 2, 425.00, 8.00, 34.00, 459.00, 'paid', '2026-05-20');

-- Sample payments
INSERT INTO payments (invoice_id, amount, payment_method, status, paid_at) VALUES
(1, 378.00, 'stripe', 'completed', '2026-05-10 14:30:00'),
(4, 270.00, 'bank_transfer', 'completed', '2026-05-25 09:15:00'),
(5, 459.00, 'stripe', 'completed', '2026-05-18 16:45:00');

-- Sample receipts
INSERT INTO receipts (receipt_number, payment_id, invoice_id, client_id, amount) VALUES
('RCP-2026-001', 1, 1, 1, 378.00),
('RCP-2026-002', 2, 4, 1, 270.00),
('RCP-2026-003', 3, 5, 2, 459.00);

-- Sample appointments
INSERT INTO appointments (case_id, client_id, admin_id, title, description, start_time, end_time, location, status) VALUES
(1, 1, 1, 'Deed Signing Meeting', 'Final deed signing and notarization session.', '2026-06-02 10:00:00', '2026-06-02 11:00:00', 'Office - Room A', 'confirmed'),
(2, 2, 1, 'POA Consultation', 'Initial consultation for power of attorney.', '2026-06-04 14:00:00', '2026-06-04 15:00:00', 'Virtual - Zoom', 'scheduled'),
(4, 1, 1, 'Contract Review', 'Review business contract before notarization.', '2026-06-06 09:30:00', '2026-06-06 10:30:00', 'Office - Room B', 'scheduled'),
(5, 2, 1, 'Loan Signing Session', 'Complete mortgage loan document signing.', '2026-05-28 13:00:00', '2026-05-28 15:00:00', 'Client Location', 'confirmed');

-- Sample notifications
INSERT INTO notifications (user_id, title, message, type, is_read, link) VALUES
(1, 'New Payment Received', 'Payment of $378.00 received for Invoice INV-2026-001.', 'payment', 0, '/admin/pages/dashboard.php'),
(1, 'Upcoming Appointment', 'Deed Signing Meeting scheduled for June 2, 2026 at 10:00 AM.', 'appointment', 0, NULL),
(1, 'Overdue Invoice', 'Invoice INV-2026-003 is overdue. Please follow up with client.', 'invoice', 0, NULL),
(1, 'Case Updated', 'Case CASE-2026-004 status changed to In Progress.', 'case', 1, NULL),
(1, 'New Client Registered', 'Michael Brown has been added as a new client.', 'account', 1, NULL);

-- Sample audit logs
INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, details) VALUES
(1, 'login', 'user', 1, '127.0.0.1', '{"message": "Admin login successful"}'),
(1, 'create', 'case', 1, '127.0.0.1', '{"case_number": "CASE-2026-001"}'),
(1, 'create', 'invoice', 1, '127.0.0.1', '{"invoice_number": "INV-2026-001"}');
