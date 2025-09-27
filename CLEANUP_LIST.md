# Files to DELETE before deployment
development_files_to_remove.txt

## Debug Files
- debug_payment.php
- payment/test_unique_payment.php
- payment/debug_payment.php
- payment/check_data.php
- payment/test_simple_payment.php

## Backup Files  
- payment/process_payment_backup.php
- payment/process_payment_simple.php
- payment/process_payment_fixed.php
- confirm_booking_old.php

## Test/Development Files
- check_requirements.php
- check_db_structure.php
- check_tables.php
- debug_reset.php

## Schema Files (keep schema.sql but don't upload .sql to production)
- Keep schema.sql for reference but don't deploy to production

## Old/Unused Files
- register.php (duplicate of auth/register.php)
- login.php (duplicate of auth/Login.php)
- event_old.php