@echo off
echo ========================================
echo Seeding Module Definitions
echo ========================================
echo.

php artisan db:seed --class=ModuleDefinitionsSeeder

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo SUCCESS! Modules seeded!
    echo ========================================
    echo.
    echo New modules added:
    echo - Locations (key: locations)
    echo - Roles (key: roles)
    echo.
    echo These modules will now appear in Settings -^> Modules
) else (
    echo.
    echo ========================================
    echo ERROR! Seeding failed!
    echo ========================================
)

echo.
pause
