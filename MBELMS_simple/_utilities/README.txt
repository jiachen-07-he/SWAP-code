UTILITY FILES - DO NOT EXPOSE TO WEB

This directory contains administrative utility scripts that should NEVER be accessible via web browser.

Files:
- check_users.php: Check database users
- test_login.php: Test login functionality
- fix_staff_password.php: Reset user passwords

SECURITY WARNING:
These files are blocked by .htaccess and should only be run via command line:
  php check_users.php
  php test_login.php
  php fix_staff_password.php

Do NOT delete the .htaccess file in this directory.
