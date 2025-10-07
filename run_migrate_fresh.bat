@echo off
echo ========================================
echo Running Fresh Migration (Drop All Tables)
echo ========================================
echo.
echo WARNING: This will DROP ALL TABLES in your database!
echo All data will be lost!
echo.
echo Press Ctrl+C to cancel, or
pause

REM Try to find PHP in common Laragon locations
if exist "D:\laragon-2025\laragon\bin\php\php-8.2.19-Win32-vs16-x64\php.exe" (
    set PHP_PATH=D:\laragon-2025\laragon\bin\php\php-8.2.19-Win32-vs16-x64\php.exe
) else if exist "D:\laragon\bin\php\php.exe" (
    set PHP_PATH=D:\laragon\bin\php\php.exe
) else (
    echo ERROR: PHP not found!
    echo Please run this command manually:
    echo php artisan migrate:fresh --seed
    pause
    exit /b 1
)

echo Using PHP: %PHP_PATH%
echo.

"%PHP_PATH%" artisan migrate:fresh --seed

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo SUCCESS! Fresh migration completed!
    echo ========================================
) else (
    echo.
    echo ========================================
    echo ERROR! Migration failed!
    echo ========================================
    echo Please check the error messages above.
)

echo.
pause
