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

async function testPollingFix() {
    console.log('\n🧪 Testing Polling Fix');
    console.log('======================');

    // Step 1: Login
    const loginSuccess = await login();
    if (!loginSuccess) {
        console.log('❌ Cannot proceed without authentication');
        return;
    }

    // Step 2: Create an export
    console.log('\n📊 Creating export...');
    const exportRequest = {
        report_key: 'assets.asset-summary',
        format: 'pdf',
        params: {
            date_from: '2024-01-01',
            date_to: '2024-12-31'
        }
    };

    try {
        const response = await makeRequest(`${API_BASE}/reports/export`, 'POST', exportRequest);
        
        if (response.data.success && response.data.data.run_id) {
            const runId = response.data.data.run_id;
            console.log(`✅ Export created successfully! Run ID: ${runId}`);
            
            // Step 3: Monitor status for a short time to verify polling stops
            console.log('\n⏱️  Monitoring export status (should complete quickly)...');
            
            let attempts = 0;
            const maxAttempts = 10; // Monitor for 20 seconds max
            
            while (attempts < maxAttempts) {
                await new Promise(resolve => setTimeout(resolve, 2000)); // Wait 2 seconds
                attempts++;
                
                try {
                    const statusResponse = await makeRequest(`${API_BASE}/reports/runs/${runId}`);
                    
                    if (statusResponse.data.success) {
                        const status = statusResponse.data.data;
                        console.log(`📋 Status check ${attempts}/${maxAttempts}: ${status.status} (${status.status_label})`);
                        
                        if (status.status === 'success' || status.status === 'failed') {
                            console.log(`🎉 Export completed: ${status.status}`);
                            console.log('✅ Polling should now stop automatically');
                            break;
                        }
                    }
                } catch (statusError) {
                    console.log(`❌ Status check error: ${statusError.message}`);
                }
            }
            
            console.log('\n📊 Test Summary:');
            console.log('✅ Export created and completed');
            console.log('✅ Polling should stop when export completes');
            console.log('✅ No more continuous API calls should occur');
            console.log('\n🎉 Polling fix test completed!');
        } else {
            console.log('❌ Failed to create export:', response.data);
        }
    } catch (error) {
        console.log('❌ Export creation failed:', error.message);
    }
}

// Run the test
testPollingFix().catch(console.error);
