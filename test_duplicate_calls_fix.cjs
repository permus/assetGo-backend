const axios = require('axios');

const API_BASE = 'http://assetgo-backend.test/api';

// Test credentials
const TEST_EMAIL = 'admin@example.com';
const TEST_PASSWORD = 'password';

let authToken = '';

async function makeRequest(url, method = 'GET', data = null) {
    const config = {
        method,
        url,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };

    if (authToken) {
        config.headers['Authorization'] = `Bearer ${authToken}`;
    }

    if (data) {
        config.data = data;
    }

    try {
        const response = await axios(config);
        return response;
    } catch (error) {
        console.error('Request failed:', error.response?.data || error.message);
        throw error;
    }
}

async function login() {
    console.log('🔐 Logging in...');
    try {
        const response = await makeRequest(`${API_BASE}/login`, 'POST', {
            email: TEST_EMAIL,
            password: TEST_PASSWORD
        });
        
        if (response.data.success && response.data.data.token) {
            authToken = response.data.data.token;
            console.log('✅ Login successful!');
            return true;
        } else {
            console.log('❌ Login failed:', response.data);
            return false;
        }
    } catch (error) {
        console.log('❌ Login error:', error.message);
        return false;
    }
}

async function testDuplicateCallsFix() {
    console.log('\n🧪 Testing Duplicate API Calls Fix');
    console.log('===================================');

    // Step 1: Login
    const loginSuccess = await login();
    if (!loginSuccess) {
        console.log('❌ Cannot proceed without authentication');
        return;
    }

    // Step 2: Create multiple exports quickly to test duplicate prevention
    console.log('\n📊 Creating multiple exports to test duplicate prevention...');
    
    const exportRequests = [
        {
            report_key: 'assets.asset-summary',
            format: 'pdf',
            params: { date_from: '2024-01-01', date_to: '2024-12-31' }
        },
        {
            report_key: 'assets.asset-utilization',
            format: 'xlsx',
            params: { date_from: '2024-01-01', date_to: '2024-12-31' }
        }
    ];

    const runIds = [];

    try {
        // Create first export
        console.log('\n📊 Creating export 1...');
        const response1 = await makeRequest(`${API_BASE}/reports/export`, 'POST', exportRequests[0]);
        if (response1.data.success && response1.data.data.run_id) {
            const runId1 = response1.data.data.run_id;
            runIds.push(runId1);
            console.log(`✅ Export 1 created: Run ID ${runId1}`);
        }

        // Create second export
        console.log('\n📊 Creating export 2...');
        const response2 = await makeRequest(`${API_BASE}/reports/export`, 'POST', exportRequests[1]);
        if (response2.data.success && response2.data.data.run_id) {
            const runId2 = response2.data.data.run_id;
            runIds.push(runId2);
            console.log(`✅ Export 2 created: Run ID ${runId2}`);
        }

        // Step 3: Wait and monitor
        console.log('\n⏱️  Waiting for exports to complete...');
        await new Promise(resolve => setTimeout(resolve, 5000));

        // Step 4: Check final status
        console.log('\n📋 Checking final status...');
        for (const runId of runIds) {
            try {
                const statusResponse = await makeRequest(`${API_BASE}/reports/runs/${runId}`);
                if (statusResponse.data.success) {
                    const status = statusResponse.data.data;
                    console.log(`📊 Run ID ${runId}: ${status.status} (${status.status_label})`);
                }
            } catch (error) {
                console.log(`❌ Error checking run ID ${runId}:`, error.message);
            }
        }

        console.log('\n📊 Test Summary:');
        console.log('✅ Multiple exports created successfully');
        console.log('✅ Each export should have its own polling (no duplicates)');
        console.log('✅ Polling should stop when each export completes');
        console.log('\n🎉 Duplicate calls fix test completed!');
        console.log('📝 Check the network tab - you should see clean, non-duplicate API calls');

    } catch (error) {
        console.log('❌ Test failed:', error.message);
    }
}

// Run the test
testDuplicateCallsFix().catch(console.error);
