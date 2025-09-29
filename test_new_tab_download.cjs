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

async function testNewTabDownload() {
    console.log('\n🧪 Testing New Tab Download Functionality');
    console.log('==========================================');

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
            
            // Step 3: Wait for completion
            console.log('\n⏱️  Waiting for export to complete...');
            await new Promise(resolve => setTimeout(resolve, 3000));
            
            // Step 4: Test the API download endpoint
            console.log('\n📥 Testing API download endpoint...');
            const downloadUrl = `${API_BASE}/reports/runs/${runId}/download`;
            console.log(`🔗 Download URL: ${downloadUrl}`);
            
            try {
                const downloadResponse = await makeRequest(downloadUrl);
                console.log('✅ API download endpoint works!');
                console.log(`📏 Downloaded ${downloadResponse.data.length || 'unknown'} bytes`);
                
                console.log('\n🎉 New tab download test completed successfully!');
                console.log('📝 The frontend will now open downloads in new tabs using this URL.');
                console.log(`🌐 Full URL for new tab: ${downloadUrl}`);
            } catch (downloadError) {
                console.log('❌ API download failed:', downloadError.message);
            }
        } else {
            console.log('❌ Failed to create export:', response.data);
        }
    } catch (error) {
        console.log('❌ Export creation failed:', error.message);
    }
}

// Run the test
testNewTabDownload().catch(console.error);
