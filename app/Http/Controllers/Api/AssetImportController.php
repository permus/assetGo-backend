<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AssetImportController extends Controller
{
    /**
     * Step 1: File Upload
     * Endpoint: POST /api/assets/import/upload
     */
    public function upload(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileType = $file->getClientOriginalExtension();
        $fileSize = $file->getSize();
        $uuid = (string) \Str::uuid();
        $storedName = $uuid . '.' . $fileType;
        $storedPath = $file->storeAs('imports', $storedName);

        $session = \App\Models\ImportSession::create([
            'uuid' => $uuid,
            'company_id' => $companyId,
            'user_id' => $user->id,
            'status' => 'pending',
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'uploaded_at' => now(),
            'meta' => [
                'path' => $storedPath,
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'file_id' => $session->uuid,
                'original_name' => $originalName,
                'size' => $fileSize,
                'uploaded_at' => $session->uploaded_at->toIso8601String(),
            ]
        ]);
    }

    /**
     * Step 2: Analyze Spreadsheet
     * Endpoint: POST /api/assets/import/analyze
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'file_id' => 'required|uuid|exists:import_sessions,uuid',
        ]);
        $session = \App\Models\ImportSession::where('uuid', $request->file_id)->firstOrFail();
        $path = $session->meta['path'] ?? null;
        if (!$path || !\Storage::exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found.'
            ], 404);
        }
        $fullPath = storage_path('app/' . $path);
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $rows = [];
        $headers = [];
        // Try Laravel Excel for xlsx/xls, fallback to native CSV
        if (in_array($ext, ['xlsx', 'xls'])) {
            if (class_exists('Maatwebsite\\Excel\\Facades\\Excel')) {
                $data = \Maatwebsite\Excel\Facades\Excel::toArray(null, $fullPath)[0] ?? [];
                $headers = array_map('trim', array_shift($data));
                $rows = $data;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel import not supported. Install maatwebsite/excel.'
                ], 500);
            }
        } else {
            $handle = fopen($fullPath, 'r');
            if ($handle) {
                $headers = array_map('trim', fgetcsv($handle));
                while (($row = fgetcsv($handle)) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }
        }
        // Prepare sample data and mapping suggestions
        $samples = [];
        foreach ($headers as $i => $header) {
            $samples[$header] = $rows[0][$i] ?? null;
        }
        // Suggest mapping to system fields (simple heuristic)
        $systemFields = [
            'name' => ['name', 'asset name'],
            'description' => ['description'],
            'category' => ['category'],
            'serial_number' => ['serial', 'serial number'],
            'model' => ['model', 'model number'],
            'manufacturer' => ['manufacturer'],
            'purchase_date' => ['purchase date'],
            'purchase_price' => ['purchase price', 'cost'],
            'location' => ['location', 'location code', 'location path'],
            'status' => ['status'],
            'tags' => ['tags'],
            'department' => ['department'],
        ];
        $mapping = [];
        foreach ($headers as $header) {
            $found = false;
            foreach ($systemFields as $sysField => $aliases) {
                foreach ($aliases as $alias) {
                    if (stripos($header, $alias) !== false) {
                        $mapping[$header] = $sysField;
                        $found = true;
                        break 2;
                    }
                }
            }
            if (!$found) {
                $mapping[$header] = null;
            }
        }
        // Confidence score (simple: high if all required fields mapped)
        $required = ['name'];
        $missing = [];
        foreach ($required as $req) {
            if (!in_array($req, $mapping)) {
                $missing[] = $req;
            }
        }
        $confidence = count($missing) === 0 ? 'high' : (count($missing) < count($required) ? 'medium' : 'low');
        return response()->json([
            'success' => true,
            'data' => [
                'headers' => $headers,
                'sample' => $samples,
                'mapping_suggestions' => $mapping,
                'confidence' => $confidence,
                'missing_required_fields' => $missing,
            ]
        ]);
    }

    /**
     * Step 3: Get Field Mappings
     * Endpoint: GET /api/assets/import/mappings/{file_id}
     */
    public function getMappings($file_id)
    {
        $session = \App\Models\ImportSession::where('uuid', $file_id)->firstOrFail();
        $mapping = \App\Models\ImportMapping::where('import_session_id', $session->id)->first();
        if ($mapping) {
            return response()->json([
                'success' => true,
                'data' => [
                    'mappings' => $mapping->mappings,
                    'user_overrides' => $mapping->user_overrides,
                ]
            ]);
        }
        // If no mapping saved, suggest based on analyze
        $analyzeRequest = new \Illuminate\Http\Request(['file_id' => $file_id]);
        $analyzeResponse = $this->analyze($analyzeRequest);
        $data = $analyzeResponse->getData(true)['data'] ?? [];
        return response()->json([
            'success' => true,
            'data' => [
                'mappings' => $data['mapping_suggestions'] ?? [],
                'user_overrides' => null,
            ]
        ]);
    }

    /**
     * Step 3: Save Field Mappings
     * Endpoint: PUT /api/assets/import/mappings/{file_id}
     */
    public function saveMappings(Request $request, $file_id)
    {
        $session = \App\Models\ImportSession::where('uuid', $file_id)->firstOrFail();
        $request->validate([
            'mappings' => 'required|array',
            'user_overrides' => 'nullable|array',
        ]);
        $mapping = \App\Models\ImportMapping::updateOrCreate(
            ['import_session_id' => $session->id],
            [
                'mappings' => $request->mappings,
                'user_overrides' => $request->user_overrides,
            ]
        );
        return response()->json([
            'success' => true,
            'data' => [
                'mappings' => $mapping->mappings,
                'user_overrides' => $mapping->user_overrides,
            ]
        ]);
    }

    /**
     * Step 4: Conflict Detection
     * Endpoint: POST /api/assets/import/conflicts/{file_id}
     */
    public function detectConflicts(Request $request, $file_id)
    {
        $session = \App\Models\ImportSession::where('uuid', $file_id)->firstOrFail();
        $mapping = \App\Models\ImportMapping::where('import_session_id', $session->id)->first();
        $path = $session->meta['path'] ?? null;
        if (!$mapping || !$path || !\Storage::exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Mapping or file not found.'
            ], 404);
        }
        $fullPath = storage_path('app/' . $path);
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $rows = [];
        $headers = [];
        if (in_array($ext, ['xlsx', 'xls'])) {
            if (class_exists('Maatwebsite\\Excel\\Facades\\Excel')) {
                $data = \Maatwebsite\Excel\Facades\Excel::toArray(null, $fullPath)[0] ?? [];
                $headers = array_map('trim', array_shift($data));
                $rows = $data;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel import not supported. Install maatwebsite/excel.'
                ], 500);
            }
        } else {
            $handle = fopen($fullPath, 'r');
            if ($handle) {
                $headers = array_map('trim', fgetcsv($handle));
                while (($row = fgetcsv($handle)) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }
        }
        // Build mapped data
        $fieldMap = $mapping->mappings;
        $dataRows = [];
        foreach ($rows as $i => $row) {
            $item = [];
            foreach ($headers as $j => $header) {
                $dbField = $fieldMap[$header] ?? null;
                if ($dbField) {
                    $item[$dbField] = $row[$j] ?? null;
                }
            }
            $dataRows[] = $item;
        }
        // Conflict checks
        $conflicts = [
            'Asset IDs' => [],
            'Serial Numbers' => [],
            'Locations' => [],
            'Statuses' => [],
            'Data Quality' => [],
        ];
        $assetIdSet = [];
        $serialSet = [];
        $existingAssetIds = \App\Models\Asset::pluck('asset_id')->toArray();
        $existingSerials = \App\Models\Asset::pluck('serial_number')->toArray();
        $validStatuses = ['active', 'inactive', 'archived'];
        $companyId = $session->company_id;
        $locations = \App\Models\Location::where('company_id', $companyId)->pluck('id', 'name')->toArray();
        foreach ($dataRows as $i => $row) {
            $rowNum = $i + 2; // +2 for header and 1-based index
            // Asset ID conflict
            if (!empty($row['asset_id'])) {
                if (in_array($row['asset_id'], $existingAssetIds)) {
                    $conflicts['Asset IDs'][] = [ 'row' => $rowNum, 'value' => $row['asset_id'], 'issue' => 'Already exists' ];
                }
                if (in_array($row['asset_id'], $assetIdSet)) {
                    $conflicts['Asset IDs'][] = [ 'row' => $rowNum, 'value' => $row['asset_id'], 'issue' => 'Duplicate in file' ];
                }
                $assetIdSet[] = $row['asset_id'];
            }
            // Serial number conflict
            if (!empty($row['serial_number'])) {
                if (in_array($row['serial_number'], $existingSerials)) {
                    $conflicts['Serial Numbers'][] = [ 'row' => $rowNum, 'value' => $row['serial_number'], 'issue' => 'Already exists' ];
                }
                if (in_array($row['serial_number'], $serialSet)) {
                    $conflicts['Serial Numbers'][] = [ 'row' => $rowNum, 'value' => $row['serial_number'], 'issue' => 'Duplicate in file' ];
                }
                $serialSet[] = $row['serial_number'];
            }
            // Location conflict
            if (!empty($row['location']) && !isset($locations[$row['location']])) {
                $conflicts['Locations'][] = [ 'row' => $rowNum, 'value' => $row['location'], 'issue' => 'Not found' ];
            }
            // Status conflict
            if (!empty($row['status']) && !in_array(strtolower($row['status']), $validStatuses)) {
                $conflicts['Statuses'][] = [ 'row' => $rowNum, 'value' => $row['status'], 'issue' => 'Invalid status' ];
            }
            // Data quality: missing required fields
            if (empty($row['name'])) {
                $conflicts['Data Quality'][] = [ 'row' => $rowNum, 'value' => '', 'issue' => 'Missing asset name' ];
            }
        }
        // Remove empty groups
        $conflicts = array_filter($conflicts, fn($arr) => count($arr));
        return response()->json([
            'success' => true,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Step 5: Resolve Conflicts
     * Endpoint: POST /api/assets/import/resolve-conflicts/{file_id}
     */
    public function resolveConflicts(Request $request, $file_id)
    {
        $session = \App\Models\ImportSession::where('uuid', $file_id)->firstOrFail();
        $request->validate([
            'resolutions' => 'required|array', // e.g., { 'Asset IDs': [...], 'Serial Numbers': [...], ... }
        ]);
        // Store resolutions in session meta
        $meta = $session->meta ?? [];
        $meta['conflict_resolutions'] = $request->resolutions;
        $session->meta = $meta;
        $session->save();
        return response()->json([
            'success' => true,
            'message' => 'Conflict resolutions saved.',
            'data' => [
                'resolutions' => $request->resolutions
            ]
        ]);
    }

    /**
     * Step 6: Final Import
     * Endpoint: POST /api/assets/import/execute/{file_id}
     */
    public function executeImport(Request $request, $file_id)
    {
        $session = \App\Models\ImportSession::where('uuid', $file_id)->firstOrFail();
        // 1. Load file, mapping, and resolutions
        // 2. Parse and map data rows
        // 3. Apply conflict resolutions
        // 4. For each row: validate, create/update asset, attach tags, generate QR, log activity
        // 5. Track imported, skipped, errors
        // 6. Generate error report if needed
        // 7. Return summary
        return response()->json([
            'success' => true,
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
            'error_report_url' => null
        ]);
    }

    /**
     * Step 7: Import Progress
     * Endpoint: GET /api/assets/import/progress/{file_id}
     */
    public function importProgress($file_id)
    {
        $session = \App\Models\ImportSession::where('uuid', $file_id)->firstOrFail();
        $meta = $session->meta ?? [];
        return response()->json([
            'success' => true,
            'status' => $session->status,
            'metrics' => [
                'total_rows' => $meta['total_rows'] ?? null,
                'processed' => $meta['processed'] ?? null,
                'imported' => $meta['imported'] ?? null,
                'errors' => $meta['errors'] ?? null,
            ]
        ]);
    }

    /**
     * Step 8: Download Template
     * Endpoint: GET /api/assets/import/template
     */
    public function downloadTemplate()
    {
        $templatePath = public_path('asset-import-template.xlsx');
        if (!file_exists($templatePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Template file not found.'
            ], 404);
        }
        return response()->download($templatePath, 'asset-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="asset-import-template.xlsx"'
        ]);
    }
}
