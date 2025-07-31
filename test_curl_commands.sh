#!/bin/bash

# Test script for Public Asset API endpoints
# This script contains curl commands to test the API

BASE_URL="https://assetgo.themeai.com/api"

echo "=== Public Asset API Test Commands ==="
echo "Base URL: $BASE_URL"
echo ""

# Test 1: Get specific asset with ID 34
echo "1. Testing Asset ID 34:"
echo "curl -X GET \"$BASE_URL/assets/34/public\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -H \"Accept: application/json\""
echo ""

curl -X GET "$BASE_URL/assets/34/public" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\nResponse Time: %{time_total}s\n"

echo ""
echo "----------------------------------------"
echo ""

# Test 2: Get assets list
echo "2. Testing Assets List:"
echo "curl -X GET \"$BASE_URL/assets/public?per_page=5\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -H \"Accept: application/json\""
echo ""

curl -X GET "$BASE_URL/assets/public?per_page=5" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\nResponse Time: %{time_total}s\n"

echo ""
echo "----------------------------------------"
echo ""

# Test 3: Get assets with search
echo "3. Testing Assets with Search:"
echo "curl -X GET \"$BASE_URL/assets/public?search=laptop&per_page=3\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -H \"Accept: application/json\""
echo ""

curl -X GET "$BASE_URL/assets/public?search=laptop&per_page=3" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\nResponse Time: %{time_total}s\n"

echo ""
echo "----------------------------------------"
echo ""

# Test 4: Test non-existent asset
echo "4. Testing Non-existent Asset (ID 99999):"
echo "curl -X GET \"$BASE_URL/assets/99999/public\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -H \"Accept: application/json\""
echo ""

curl -X GET "$BASE_URL/assets/99999/public" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\nResponse Time: %{time_total}s\n"

echo ""
echo "----------------------------------------"
echo ""

# Test 5: Get assets with company filter
echo "5. Testing Assets with Company Filter:"
echo "curl -X GET \"$BASE_URL/assets/public?company_slug=acme-corp&per_page=3\" \\"
echo "  -H \"Content-Type: application/json\" \\"
echo "  -H \"Accept: application/json\""
echo ""

curl -X GET "$BASE_URL/assets/public?company_slug=acme-corp&per_page=3" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\nResponse Time: %{time_total}s\n"

echo ""
echo "=== Test Completed ==="
echo ""
echo "To run individual tests, copy and paste the curl commands above."
echo "Make sure to replace the BASE_URL if your API is hosted elsewhere." 