# Test AI Image Analysis API

## Using Postman

1. **Import the collection:**
   - Use `postman_collection.json`
   - Or create a new request

2. **Configure the request:**
   ```
   POST http://localhost:8000/api/ai/image-recognition/analyze
   Authorization: Bearer YOUR_TOKEN_HERE
   Content-Type: application/json
   ```

3. **Request Body:**
   ```json
   {
     "images": [
       "base64_encoded_image_string_here"
     ]
   }
   ```

4. **Get a base64 image:**
   - Go to: https://www.base64-image.de/
   - Upload a test image (asset photo)
   - Copy the base64 string (without the `data:image/...;base64,` prefix)
   - Paste into the request body

5. **Expected Response:**
   ```json
   {
     "success": true,
     "data": {
       "assetType": "Laptop",
       "confidence": 85,
       "manufacturer": "Dell",
       "model": "XPS 15",
       "serialNumber": "ABC123",
       "assetTag": null,
       "condition": "Good",
       "recommendations": [
         "Schedule regular maintenance",
         "Check battery health",
         "Update firmware"
       ],
       "evidence": {
         "fieldsFound": ["manufacturer", "model", "serialNumber"],
         "imagesUsed": 1,
         "notes": "High confidence on manufacturer and model"
       }
     }
   }
   ```

## Using cURL (Windows PowerShell)

```powershell
# Get your auth token first
$token = "YOUR_TOKEN_HERE"

# Prepare the request (with a small test image)
$body = @{
    images = @("YOUR_BASE64_IMAGE_HERE")
} | ConvertTo-Json

# Send request
Invoke-RestMethod -Uri "http://localhost:8000/api/ai/image-recognition/analyze" `
    -Method Post `
    -Headers @{
        "Authorization" = "Bearer $token"
        "Content-Type" = "application/json"
    } `
    -Body $body
```

## Common Test Images

For testing, use images with visible:
- ✅ Asset nameplates with model/serial numbers
- ✅ Equipment labels
- ✅ Laptops showing brand/model
- ✅ Industrial machinery with identification plates
- ❌ Avoid: Blurry images, poor lighting, no visible text

