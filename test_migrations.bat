@echo off
echo ========================================
echo Migration Test Script
echo ========================================
echo.

echo Step 1: Checking migration status...
php artisan migrate:status
echo.

echo ========================================
echo Step 2: Running migrate:refresh...
echo ========================================
echo WARNING: This will drop all tables and recreate them!
echo Press Ctrl+C to cancel, or
pause

php artisan migrate:refresh --seed

echo.
echo ========================================
echo Step 3: Checking final migration status...
echo ========================================
php artisan migrate:status

echo.
echo ========================================
echo Migration test complete!
echo ========================================
echo.
echo Check the output above for any errors.
echo If all migrations show "Ran", the fix was successful!
echo.
pause
