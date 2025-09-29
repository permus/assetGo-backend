const axios = require('axios');

const API_BASE = 'http://assetgo-backend.test/api';
const FRONTEND_URL = 'http://localhost:4200';

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

async function testAutoDownload() {
    console.log('\n🧪 Testing Auto-Download Functionality');
    console.log('=====================================');

    // Step 1: Login
    const loginSuccess = await login();
    if (!loginSuccess) {
        console.log('❌ Cannot proceed without authentication');
        return;
    }

    // Step 2: Create an export
    console.log('\n📊 Creating export for auto-download test...');
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
            
            // Step 3: Monitor the export status
            console.log('\n⏱️  Monitoring export status...');
            let attempts = 0;
            const maxAttempts = 15; // 30 seconds max
            
            while (attempts < maxAttempts) {
                await new Promise(resolve => setTimeout(resolve, 2000)); // Wait 2 seconds
                attempts++;
                
                try {
                    const statusResponse = await makeRequest(`${API_BASE}/reports/runs/${runId}`);
                    
                    if (statusResponse.data.success) {
                        const status = statusResponse.data.data;
                        console.log(`📋 Status check ${attempts}/${maxAttempts}: ${status.status} (${status.status_label})`);
                        
                        if (status.status === 'success') {
                            console.log('🎉 Export completed successfully!');
                            console.log(`📁 Download URL: ${status.download_url}`);
                            console.log(`📏 File size: ${status.file_size} bytes`);
                            console.log(`⏱️  Execution time: ${status.execution_time_formatted}`);
                            
                            // Step 4: Test download
                            console.log('\n📥 Testing download...');
                            try {
                                const downloadResponse = await makeRequest(`${API_BASE}/reports/runs/${runId}/download`);
                                console.log('✅ Download successful!');
                                console.log(`📏 Downloaded ${downloadResponse.data.length || 'unknown'} bytes`);
                                
                                console.log('\n🎉 Auto-download test completed successfully!');
                                console.log('📝 Note: In the frontend, the file should download automatically when the export completes.');
                                return;
                            } catch (downloadError) {
                                console.log('❌ Download failed:', downloadError.message);
                                return;
                            }
                        } else if (status.status === 'failed') {
                            console.log('❌ Export failed:', status.error_message || 'Unknown error');
                            return;
                        }
                    } else {
                        console.log(`❌ Status check failed: ${statusResponse.data.error || 'Unknown error'}`);
                    }
                } catch (statusError) {
                    console.log(`❌ Status check error: ${statusError.message}`);
                }
            }
            
            console.log('⏰ Export did not complete within the expected time');
        } else {
            console.log('❌ Failed to create export:', response.data);
        }
    } catch (error) {
        console.log('❌ Export creation failed:', error.message);
    }
}

// Run the test
testAutoDownload().catch(console.error);
