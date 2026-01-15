-- Add 'user' role to the users table

-- Modify the role ENUM to include 'user'
ALTER TABLE users
MODIFY COLUMN role ENUM('admin','staff','user') NOT NULL DEFAULT 'user';

-- Note: 'user' role will have limited permissions:
-- - Can book machines
-- - Can view availability
-- - Cannot borrow equipment
-- - Cannot access admin features
