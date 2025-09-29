// Test script for export download functionality
const https = require('https');
const http = require('http');

const API_BASE = 'http://assetgo-backend.test/api';
const AUTH_TOKEN = '25|UASEvOcvt2gPyxOluUTkxdQJGaBAPBDlPpsVuhBp6a0b8fae';

// Helper function to make HTTP requests
function makeRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const requestOptions = {
            hostname: urlObj.hostname,
            port: urlObj.port || 80,
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: {
                'Authorization': `Bearer ${AUTH_TOKEN}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...options.headers
            }
        };

        const req = http.request(requestOptions, (res) => {
            let data = '';
            res.on('data', (chunk) => data += chunk);
            res.on('end', () => {
                try {
                    const jsonData = JSON.parse(data);
                    resolve({ status: res.statusCode, data: jsonData });
                } catch (e) {
                    resolve({ status: res.statusCode, data: data });
                }
            });
        });

        req.on('error', reject);
        
        if (options.body) {
            req.write(JSON.stringify(options.body));
        }
        
        req.end();
    });
}

// Test export creation
async function testExportCreation() {
    console.log('🚀 Testing Export Creation...');
    
    const exportData = {
        report_key: 'assets.asset-summary',
        format: 'pdf',
        params: {
            date_from: '2024-01-01',
            date_to: '2024-12-31'
        }
    };

    try {
        const response = await makeRequest(`${API_BASE}/reports/export`, {
            method: 'POST',
            body: exportData
        });

        console.log('📊 Export Creation Response:', response);
        
        if (response.status === 200 && response.data.success) {
            console.log('✅ Export created successfully!');
            console.log('📋 Run ID:', response.data.data.run_id);
            return response.data.data.run_id;
        } else {
            console.log('❌ Export creation failed:', response.data);
            return null;
        }
    } catch (error) {
        console.log('❌ Export creation error:', error.message);
        return null;
    }
}

// Test export status checking
async function testExportStatus(runId) {
    console.log(`\n🔍 Testing Export Status for Run ID: ${runId}...`);
    
    try {
        const response = await makeRequest(`${API_BASE}/reports/runs/${runId}`);
        
        console.log('📊 Export Status Response:', response);
        
        if (response.status === 200 && response.data.success) {
            const exportData = response.data.data;
            console.log('✅ Export status retrieved successfully!');
            console.log('📋 Status:', exportData.status);
            console.log('📋 Status Label:', exportData.status_label);
            console.log('📋 Row Count:', exportData.row_count);
            console.log('📋 Execution Time:', exportData.execution_time_formatted);
            
            if (exportData.status === 'success') {
                console.log('✅ Export completed successfully!');
                console.log('📁 Download URL:', exportData.download_url);
                console.log('📏 File Size:', exportData.file_size, 'bytes');
                return exportData;
            } else if (exportData.status === 'failed') {
                console.log('❌ Export failed:', exportData.error_message);
                return null;
            } else {
                console.log('⏳ Export still in progress...');
                return null;
            }
        } else {
            console.log('❌ Failed to get export status:', response.data);
            return null;
        }
    } catch (error) {
        console.log('❌ Export status error:', error.message);
        return null;
    }
}

// Test download functionality
async function testDownload(downloadUrl) {
    console.log(`\n📥 Testing Download from: ${downloadUrl}...`);
    
    try {
        const response = await makeRequest(`${API_BASE}${downloadUrl}`);
        
        console.log('📊 Download Response Status:', response.status);
        
        if (response.status === 200) {
            console.log('✅ Download successful!');
            console.log('📏 Response size:', response.data.length || 'Unknown');
            return true;
        } else {
            console.log('❌ Download failed:', response.data);
            return false;
        }
    } catch (error) {
        console.log('❌ Download error:', error.message);
        return false;
    }
}

// Poll export status until completion
async function pollExportStatus(runId, maxAttempts = 10) {
    console.log(`\n⏳ Polling export status (max ${maxAttempts} attempts)...`);
    
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        console.log(`\n🔄 Attempt ${attempt}/${maxAttempts}:`);
        
        const exportData = await testExportStatus(runId);
        
        if (exportData && exportData.status === 'success') {
            return exportData;
        } else if (exportData && exportData.status === 'failed') {
            return null;
        }
        
        // Wait 2 seconds before next attempt
        if (attempt < maxAttempts) {
            console.log('⏱️  Waiting 2 seconds before next check...');
            await new Promise(resolve => setTimeout(resolve, 2000));
        }
    }
    
    console.log('⏰ Timeout: Export did not complete within expected time');
    return null;
}

// Main test function
async function runExportDownloadTest() {
    console.log('🧪 Starting Export Download Flow Test');
    console.log('=====================================\n');
    
    // Step 1: Create export
    const runId = await testExportCreation();
    if (!runId) {
        console.log('❌ Test failed: Could not create export');
        return;
    }
    
    // Step 2: Poll status until completion
    const completedExport = await pollExportStatus(runId);
    if (!completedExport) {
        console.log('❌ Test failed: Export did not complete successfully');
        return;
    }
    
    // Step 3: Test download
    if (completedExport.download_url) {
        const downloadSuccess = await testDownload(completedExport.download_url);
        if (downloadSuccess) {
            console.log('\n🎉 All tests passed! Export and download functionality is working correctly.');
        } else {
            console.log('\n❌ Test failed: Download functionality not working');
        }
    } else {
        console.log('\n❌ Test failed: No download URL available');
    }
}

// Run the test
runExportDownloadTest().catch(console.error);
