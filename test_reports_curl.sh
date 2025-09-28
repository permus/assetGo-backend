#!/bin/bash

# AssetGo Reports Module - Backend Testing Script (cURL)
# This script tests the Reports Module API endpoints using cURL

# Configuration
BASE_URL="http://localhost:8000/api"
TEST_USER_EMAIL="admin@example.com"
TEST_USER_PASSWORD="password"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to print colored output
print_status() {
    local status=$1
    local message=$2
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✅ $message${NC}"
    elif [ "$status" = "FAIL" ]; then
        echo -e "${RED}❌ $message${NC}"
    else
        echo -e "${YELLOW}ℹ️  $message${NC}"
    fi
}

# Function to make API request
make_request() {
    local method=$1
    local endpoint=$2
    local data=$3
    local token=$4
    
    local url="$BASE_URL$endpoint"
    local headers=()
    
    if [ -n "$token" ]; then
        headers+=("-H" "Authorization: Bearer $token")
    fi
    
    headers+=("-H" "Content-Type: application/json")
    headers+=("-H" "Accept: application/json")
    
    if [ "$method" = "GET" ]; then
        curl -s -w "\n%{http_code}" "${headers[@]}" "$url"
    else
        curl -s -w "\n%{http_code}" -X "$method" "${headers[@]}" -d "$data" "$url"
    fi
}

# Function to test endpoint
test_endpoint() {
    local test_name=$1
    local method=$2
    local endpoint=$3
    local data=$4
    local token=$5
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo -e "${BLUE}Testing: $test_name${NC}"
    
    local response=$(make_request "$method" "$endpoint" "$data" "$token")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    if [ "$http_code" = "200" ]; then
        # Check if response has success: true
        if echo "$body" | grep -q '"success":true'; then
            print_status "PASS" "$test_name - Success"
            PASSED_TESTS=$((PASSED_TESTS + 1))
        else
            print_status "FAIL" "$test_name - Invalid response format"
            FAILED_TESTS=$((FAILED_TESTS + 1))
        fi
    else
        print_status "FAIL" "$test_name - HTTP $http_code"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo "Response: $body"
    fi
    echo
}

# Function to authenticate and get token
authenticate() {
    echo -e "${YELLOW}🔐 Authenticating...${NC}"
    
    local auth_data="{\"email\":\"$TEST_USER_EMAIL\",\"password\":\"$TEST_USER_PASSWORD\"}"
    local response=$(make_request "POST" "/login" "$auth_data")
    local http_code=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | head -n -1)
    
    if [ "$http_code" = "200" ]; then
        # Extract token from response
        AUTH_TOKEN=$(echo "$body" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
        if [ -n "$AUTH_TOKEN" ]; then
            print_status "PASS" "Authentication successful"
            echo "Token: ${AUTH_TOKEN:0:20}..."
            return 0
        else
            print_status "FAIL" "No token in response"
            return 1
        fi
    else
        print_status "FAIL" "Authentication failed - HTTP $http_code"
        echo "Response: $body"
        return 1
    fi
}

# Main test execution
main() {
    echo -e "${BLUE}🚀 Starting AssetGo Reports Module Backend Tests${NC}"
    echo "=================================================="
    echo
    echo "Base URL: $BASE_URL"
    echo "Test User: $TEST_USER_EMAIL"
    echo
    
    # Authenticate first
    if ! authenticate; then
        echo -e "${RED}❌ Authentication failed. Cannot proceed with tests.${NC}"
        exit 1
    fi
    
    echo
    echo -e "${YELLOW}📊 Testing Asset Reports...${NC}"
    echo "=============================="
    
    # Test Asset Reports
    test_endpoint "Asset Summary Report" "GET" "/reports/assets/summary" "" "$AUTH_TOKEN"
    test_endpoint "Asset Utilization Report" "GET" "/reports/assets/utilization" "" "$AUTH_TOKEN"
    test_endpoint "Asset Depreciation Report" "GET" "/reports/assets/depreciation" "" "$AUTH_TOKEN"
    test_endpoint "Asset Warranty Report" "GET" "/reports/assets/warranty" "" "$AUTH_TOKEN"
    test_endpoint "Asset Compliance Report" "GET" "/reports/assets/compliance" "" "$AUTH_TOKEN"
    test_endpoint "Available Asset Reports" "GET" "/reports/assets/available" "" "$AUTH_TOKEN"
    
    echo -e "${YELLOW}🔧 Testing Maintenance Reports...${NC}"
    echo "=================================="
    
    # Test Maintenance Reports
    test_endpoint "Maintenance Summary Report" "GET" "/reports/maintenance/summary" "" "$AUTH_TOKEN"
    test_endpoint "Maintenance Compliance Report" "GET" "/reports/maintenance/compliance" "" "$AUTH_TOKEN"
    test_endpoint "Maintenance Costs Report" "GET" "/reports/maintenance/costs" "" "$AUTH_TOKEN"
    test_endpoint "Downtime Analysis Report" "GET" "/reports/maintenance/downtime" "" "$AUTH_TOKEN"
    test_endpoint "Failure Analysis Report" "GET" "/reports/maintenance/failure-analysis" "" "$AUTH_TOKEN"
    test_endpoint "Technician Performance Report" "GET" "/reports/maintenance/technician-performance" "" "$AUTH_TOKEN"
    test_endpoint "Available Maintenance Reports" "GET" "/reports/maintenance/available" "" "$AUTH_TOKEN"
    
    echo -e "${YELLOW}📤 Testing Export Functionality...${NC}"
    echo "=================================="
    
    # Test Export
    local export_data='{"report_key":"assets.summary","format":"json","params":{"page":1,"page_size":10}}'
    test_endpoint "Export Request" "POST" "/reports/export" "$export_data" "$AUTH_TOKEN"
    
    # Test Export History
    test_endpoint "Export History" "GET" "/reports/history" "" "$AUTH_TOKEN"
    
    echo -e "${YELLOW}🔍 Testing with Parameters...${NC}"
    echo "============================="
    
    # Test with parameters
    test_endpoint "Asset Summary with Filters" "GET" "/reports/assets/summary?date_from=2024-01-01&date_to=2024-12-31&page=1&page_size=5" "" "$AUTH_TOKEN"
    
    echo -e "${YELLOW}⚠️  Testing Error Handling...${NC}"
    echo "============================="
    
    # Test invalid export format
    local invalid_export='{"report_key":"assets.summary","format":"invalid_format","params":{}}'
    local response=$(make_request "POST" "/reports/export" "$invalid_export" "$AUTH_TOKEN")
    local http_code=$(echo "$response" | tail -n1)
    
    if [ "$http_code" = "422" ] || [ "$http_code" = "400" ]; then
        print_status "PASS" "Invalid export format properly rejected"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        print_status "FAIL" "Invalid export format not properly rejected"
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    echo
    
    # Generate summary
    echo -e "${BLUE}📋 Test Summary${NC}"
    echo "=============="
    echo "Total Tests: $TOTAL_TESTS"
    echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
    echo -e "Failed: ${RED}$FAILED_TESTS${NC}"
    
    local success_rate=$((PASSED_TESTS * 100 / TOTAL_TESTS))
    echo "Success Rate: $success_rate%"
    echo
    
    if [ $FAILED_TESTS -eq 0 ]; then
        echo -e "${GREEN}🎉 All tests passed! The Reports Module is working correctly.${NC}"
    else
        echo -e "${RED}⚠️  Some tests failed. Please check the implementation.${NC}"
    fi
}

# Run the tests
main
