@echo off
echo Fixing foreign key migration order...

REM Move foreign key migrations to run AFTER all base tables are created
move "database\migrations\2014_10_12_000001_add_foreign_keys_to_users_table.php" "database\migrations\2014_10_12_000020_add_foreign_keys_to_users_table.php"
move "database\migrations\2014_10_12_000003_add_foreign_keys_to_companies_table.php" "database\migrations\2014_10_12_000021_add_foreign_keys_to_companies_table.php"

REM Also fix duplicate timestamp for location_types
move "database\migrations\2014_10_12_000003_create_location_types_table.php" "database\migrations\2014_10_12_000001_create_location_types_table.php"

echo.
echo Migration order fixed!
echo.
echo New order:
echo 000000 - create_users_table
echo 000001 - create_location_types_table
echo 000002 - create_companies_table
echo 000004 - create_roles_table
echo 000005 - create_permissions_table
echo ...
echo 000020 - add_foreign_keys_to_users_table
echo 000021 - add_foreign_keys_to_companies_table
echo.
pause
