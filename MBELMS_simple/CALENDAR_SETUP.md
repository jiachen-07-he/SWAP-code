# Calendar Booking Feature Setup

## 📅 New Feature: Time Slot Booking System

This feature allows users to book machines for specific time slots instead of all-day bookings.

## Setup Instructions

### Step 1: Update Database Schema

Run this SQL file to add time slot support to your existing database:

```bash
mysql -u root mbelms_db < add_time_slots.sql
```

Or manually run the SQL:
```sql
ALTER TABLE machine_bookings
ADD COLUMN start_time DATETIME NULL AFTER status,
ADD COLUMN end_time DATETIME NULL AFTER start_time;

ALTER TABLE machine_bookings
ADD INDEX idx_time_slot (machine_id, start_time, end_time);
```

### Step 2: Access the Calendar

1. Log in to your account
2. Click on "📅 Calendar" in the top navigation
3. Select a machine from the dropdown
4. Choose a date
5. Click "Book" on any available time slot

## Features

✅ **Visual Time Slot Calendar**
- See all available time slots for the day
- Color-coded availability (green = available, red = booked)
- Shows who booked each slot

✅ **Smart Conflict Detection**
- Prevents double-booking
- Checks for overlapping time slots
- Real-time availability updates

✅ **Flexible Time Slots**
- 1-hour blocks from 8 AM to 5 PM
- Can be easily customized in `calendar_booking.php`

✅ **User-Friendly Interface**
- Date picker for easy date selection
- One-click booking
- Confirmation dialogs

## Customization

### Change Time Slots

Edit the `$timeSlots` array in `pages/calendar_booking.php`:

```php
$timeSlots = [
    '08:00', '09:00', '10:00', '11:00', '12:00',
    '13:00', '14:00', '15:00', '16:00', '17:00'
];
```

### Change Time Slot Duration

Currently set to 1-hour blocks. Modify the array to use 30-minute blocks:

```php
$timeSlots = [
    '08:00', '08:30', '09:00', '09:30', '10:00', ...
];
```

## Testing

1. **Create a booking**: Select a machine, date, and book a time slot
2. **Check conflicts**: Try to book an overlapping time slot (should be blocked)
3. **View from dashboard**: Existing bookings show time ranges
4. **Cancel booking**: Cancel from dashboard, then check calendar (slot should become available)

## Files Modified/Created

- ✅ `pages/calendar_booking.php` - Main calendar interface
- ✅ `add_time_slots.sql` - Database migration
- ✅ `database.sql` - Updated schema for new installations
- ✅ `includes/header.php` - Added navigation link

## Notes

- Old bookings without time slots can be updated with default times (8 AM - 5 PM)
- The calendar supports same-day and future bookings
- Past dates are disabled in the date picker
- All bookings are logged in audit_logs for tracking
