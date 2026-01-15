-- Add time slot support to machine_bookings table

-- Add start_time and end_time columns
ALTER TABLE machine_bookings
ADD COLUMN start_time DATETIME NULL AFTER status,
ADD COLUMN end_time DATETIME NULL AFTER start_time;

-- Update existing bookings to have default times (optional)
-- This sets existing bookings to full day bookings (8 AM to 5 PM on creation date)
UPDATE machine_bookings
SET
    start_time = CONCAT(DATE(created_at), ' 08:00:00'),
    end_time = CONCAT(DATE(created_at), ' 17:00:00')
WHERE start_time IS NULL AND end_time IS NULL;

-- Add index for performance
ALTER TABLE machine_bookings
ADD INDEX idx_time_slot (machine_id, start_time, end_time);

-- Note: Run this SQL file to update your database:
-- mysql -u root mbelms_db < add_time_slots.sql
