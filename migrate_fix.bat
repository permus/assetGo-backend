@echo off
echo Fixing migration file order...

REM Move location types before locations
move "database\migrations\2025_07_17_095259_create_location_types_table.php" "database\migrations\2014_10_12_000003_create_location_types_table.php"

REM Move locations after companies
move "database\migrations\2025_07_17_095223_create_locations_table.php" "database\migrations\2014_10_12_000007_create_locations_table.php"

REM Move asset categories before assets
move "database\migrations\2025_07_18_035747_create_asset_categories_table.php" "database\migrations\2014_10_12_000008_create_asset_categories_table.php"

REM Move departments before assets
move "database\migrations\2025_07_27_121344_create_departments_table.php" "database\migrations\2014_10_12_000009_create_departments_table.php"

REM Move assets after dependencies
move "database\migrations\2025_07_18_035748_create_assets_table.php" "database\migrations\2014_10_12_000010_create_assets_table.php"

REM Move user_roles after roles and permissions
move "database\migrations\2025_08_04_000003_create_user_roles_table.php" "database\migrations\2014_10_12_000006_create_user_roles_table.php"

echo Migration files reorganized!
pause
