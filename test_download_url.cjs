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

async function testDownloadUrl() {
    console.log('\n🧪 Testing Download URL Construction');
    console.log('=====================================');

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
            
            // Step 3: Get export status
            console.log('\n📋 Getting export status...');
            await new Promise(resolve => setTimeout(resolve, 3000)); // Wait for completion
            
            const statusResponse = await makeRequest(`${API_BASE}/reports/runs/${runId}`);
            
            if (statusResponse.data.success) {
                const status = statusResponse.data.data;
                console.log(`📊 Status: ${status.status} (${status.status_label})`);
                
                if (status.status === 'success' && status.download_url) {
                    console.log('\n🔗 Download URL Information:');
                    console.log(`📁 Relative URL: ${status.download_url}`);
                    
                    // Construct full URL (same logic as in the service)
                    const baseUrl = 'http://assetgo-backend.test';
                    const fullUrl = status.download_url.startsWith('http') ? status.download_url : `${baseUrl}${status.download_url}`;
                    
                    console.log(`🌐 Full URL: ${fullUrl}`);
                    console.log(`📏 File size: ${status.file_size} bytes`);
                    console.log(`⏱️  Execution time: ${status.execution_time_formatted}`);
                    
                    // Step 4: Test the full URL
                    console.log('\n📥 Testing full URL download...');
                    try {
                        const downloadResponse = await makeRequest(fullUrl);
                        console.log('✅ Full URL download successful!');
                        console.log(`📏 Downloaded ${downloadResponse.data.length || 'unknown'} bytes`);
                        
                        console.log('\n🎉 Download URL test completed successfully!');
                        console.log('📝 The frontend will now use this full URL for downloads.');
                    } catch (downloadError) {
                        console.log('❌ Full URL download failed:', downloadError.message);
                        console.log('🔍 This might be due to CORS or authentication issues.');
                    }
                } else {
                    console.log('❌ Export not ready or no download URL available');
                }
            } else {
                console.log('❌ Failed to get export status:', statusResponse.data);
            }
        } else {
            console.log('❌ Failed to create export:', response.data);
        }
    } catch (error) {
        console.log('❌ Export creation failed:', error.message);
    }
}

// Run the test
testDownloadUrl().catch(console.error);
