-- Update admin credentials to simple login
-- Email: admin@admin.com | Password: admin123

USE notary_management;

UPDATE users
SET email = 'admin@admin.com',
    password = '$2y$10$pjAbMHgW7P621hNTLaAP..AnVdnx6EWDig26cQHn0jqt/nBT.g.aW',
    first_name = 'Admin',
    last_name = 'User'
WHERE role = 'admin'
LIMIT 1;
