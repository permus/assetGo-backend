<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryPart;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Traits\HasPermissions;
use App\Services\{InventoryAuditService, InventoryCacheService, NotificationService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class PartController extends Controller
{
    use HasPermissions;

    protected $auditService;
    protected $cacheService;
    protected $notificationService;

    public function __construct(InventoryAuditService $auditService, InventoryCacheService $cacheService, NotificationService $notificationService)
    {
        $this->auditService = $auditService;
        $this->cacheService = $cacheService;
        $this->notificationService = $notificationService;
    }

    public function overview(Request $request)
    {
        $companyId = $request->user()->company_id;

        return $this->cacheService->getPartsOverview($companyId, function () use ($companyId) {
            // Total parts (all parts including archived)
            $totalParts = \App\Models\InventoryPart::forCompany($companyId)->count();
            
            // Active parts (only non-archived parts)
            $activeParts = \App\Models\InventoryPart::forCompany($companyId)
                ->where('is_archived', false)
                ->count();

            // Low stock: sum available across all locations <= part.reorder_point (and reorder_point > 0)
            // Filter to only include active (non-archived) parts
            $stockAgg = DB::table('inventory_stocks')
                ->select('part_id', DB::raw('SUM(available) as total_available'))
                ->where('company_id', $companyId)
                ->whereIn('part_id', function($query) use ($companyId) {
                    $query->select('id')
                        ->from('inventory_parts')
                        ->where('company_id', $companyId)
                        ->where('is_archived', false);
                })
                ->groupBy('part_id');

            $lowStock = DB::table('inventory_parts')
                ->leftJoinSub($stockAgg, 'agg', function($join) {
                    $join->on('inventory_parts.id', '=', 'agg.part_id');
                })
                ->where('inventory_parts.company_id', $companyId)
                ->where('inventory_parts.is_archived', false)
                ->where('inventory_parts.reorder_point', '>', 0)
                ->whereRaw('COALESCE(agg.total_available, 0) <= inventory_parts.reorder_point')
                ->count();

            // Total value: on_hand * average_cost across all stocks
            // Filter to only include stocks for active (non-archived) parts
            $totalValue = DB::table('inventory_stocks')
                ->join('inventory_parts', 'inventory_stocks.part_id', '=', 'inventory_parts.id')
                ->where('inventory_stocks.company_id', $companyId)
                ->where('inventory_parts.is_archived', false)
                ->select(DB::raw('SUM(inventory_stocks.on_hand * inventory_stocks.average_cost) as value'))
                ->value('value') ?? 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_parts' => $totalParts,
                    'active_parts' => $activeParts,
                    'low_stock_count' => $lowStock,
                    'total_value' => round((float)$totalValue, 2),
                ]
            ]);
        });
    }
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $query = InventoryPart::forCompany($companyId);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('part_number', 'like', "%$search%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Handle archived parts filtering
        if ($request->has('is_archived')) {
            $isArchived = $request->boolean('is_archived');
            $query->where('is_archived', $isArchived);
        } else {
            // Filter archived parts by default unless explicitly requested
            $includeArchived = $request->boolean('include_archived', false);
            if (!$includeArchived) {
                $query->where('is_archived', false);
            }
        }

        $perPage = min($request->get('per_page', 15), 100);
        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->paginate($perPage)
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'part_number' => 'required|string|max:255|unique:inventory_parts,part_number',
            'uom' => 'required|string|max:50',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $data = $request->only(['name','part_number','description','uom','unit_cost','category_id','reorder_point','reorder_qty','barcode']);
        $data['company_id'] = $request->user()->company_id;
        $data['user_id'] = $request->user()->id;
        $part = InventoryPart::create($data);

        // Log the creation
        $this->auditService->logPartCreated(
            $part->id,
            $part->part_number,
            $part->name,
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

        // Send notifications to admins and company owners
        $creator = $request->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'inventory',
                    'action' => 'create_part',
                    'title' => 'Inventory Part Created',
                    'message' => $this->notificationService->formatInventoryMessage('create_part', $part->name),
                    'data' => [
                        'partId' => $part->id,
                        'partName' => $part->name,
                        'partNumber' => $part->part_number,
                        'createdBy' => [
                            'id' => $creator->id,
                            'name' => $creator->first_name . ' ' . $creator->last_name,
                            'userType' => $creator->user_type,
                        ],
                    ],
                    'created_by' => $creator->id,
                ],
                $creator->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send inventory part creation notifications', [
                'part_id' => $part->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(['success' => true, 'data' => $part], 201);
    }

    public function show(Request $request, InventoryPart $part)
    {
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        return response()->json(['success' => true, 'data' => $part]);
    }

    public function update(Request $request, InventoryPart $part)
    {
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'uom' => 'sometimes|required|string|max:50',
            'unit_cost' => 'nullable|numeric|min:0',
        ];
        if ($request->filled('part_number') && $request->part_number !== $part->part_number) {
            $rules['part_number'] = 'string|max:255|unique:inventory_parts,part_number';
        }
        $data = $request->validate($rules);
        $originalData = $part->getOriginal();
        $part->update(array_merge($request->only(['description','category_id','reorder_point','reorder_qty','barcode','status','abc_class']), $data));

        // Log the update
        $this->auditService->logPartUpdated(
            $part->id,
            $part->part_number,
            $part->name,
            $part->getChanges(),
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

        // Send notifications to admins and company owners
        $creator = $request->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'inventory',
                    'action' => 'edit_part',
                    'title' => 'Inventory Part Updated',
                    'message' => $this->notificationService->formatInventoryMessage('edit_part', $part->name),
                    'data' => [
                        'partId' => $part->id,
                        'partName' => $part->name,
                        'partNumber' => $part->part_number,
                        'createdBy' => [
                            'id' => $creator->id,
                            'name' => $creator->first_name . ' ' . $creator->last_name,
                            'userType' => $creator->user_type,
                        ],
                    ],
                    'created_by' => $creator->id,
                ],
                $creator->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send inventory part update notifications', [
                'part_id' => $part->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(['success' => true, 'data' => $part]);
    }

    public function destroy(Request $request, InventoryPart $part)
    {
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Store part data before deletion
        $partId = $part->id;
        $partNumber = $part->part_number;
        $partName = $part->name;

        $part->delete();

        // Log the deletion
        $this->auditService->logPartDeleted(
            $partId,
            $partNumber,
            $partName,
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

        // Send notifications to admins and company owners
        $creator = $request->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'inventory',
                    'action' => 'delete_part',
                    'title' => 'Inventory Part Deleted',
                    'message' => $this->notificationService->formatInventoryMessage('delete_part', $partName),
                    'data' => [
                        'partName' => $partName,
                        'partNumber' => $partNumber,
                        'createdBy' => [
                            'id' => $creator->id,
                            'name' => $creator->first_name . ' ' . $creator->last_name,
                            'userType' => $creator->user_type,
                        ],
                    ],
                    'created_by' => $creator->id,
                ],
                $creator->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send inventory part deletion notifications', [
                'part_name' => $partName,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function archive(Request $request, InventoryPart $part)
    {
        // Check company ownership
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Check permission
        if ($denied = $this->requirePermission('inventory', 'parts_archive')) {
            return $denied;
        }

        // Check if already archived
        if ($part->is_archived) {
            return response()->json([
                'success' => false,
                'message' => 'Part is already archived'
            ], 422);
        }

        // Validate request
        $data = $request->validate([
            'force' => 'sometimes|boolean',
        ]);

        $force = $data['force'] ?? false;

        // Check for open purchase orders with this part
        $openPOStatuses = ['draft', 'pending', 'ordered', 'approved'];
        $affectedPOs = PurchaseOrderItem::where('part_id', $part->id)
            ->whereHas('purchaseOrder', function ($query) use ($openPOStatuses, $request) {
                $query->whereIn('status', $openPOStatuses)
                      ->where('company_id', $request->user()->company_id);
            })
            ->with('purchaseOrder:id,po_number,status')
            ->get();

        // If there are open POs and force is not true, return warning
        if ($affectedPOs->isNotEmpty() && !$force) {
            $poDetails = $affectedPOs->map(function ($item) {
                return [
                    'po_id' => $item->purchase_order_id,
                    'po_number' => $item->purchaseOrder->po_number ?? 'N/A',
                    'status' => $item->purchaseOrder->status ?? 'N/A',
                    'ordered_qty' => $item->ordered_qty,
                    'received_qty' => $item->received_qty,
                ];
            })->toArray();

            return response()->json([
                'success' => false,
                'message' => 'This part is linked to open purchase orders. Set force=true to archive anyway.',
                'affected_purchase_orders' => $poDetails,
                'requires_force' => true,
            ], 422);
        }

        // Archive the part
        $part->is_archived = true;
        $part->save();

        // Prepare affected PO data for logging
        $affectedPOsLog = $affectedPOs->map(function ($item) {
            return [
                'po_id' => $item->purchase_order_id,
                'po_number' => $item->purchaseOrder->po_number ?? 'N/A',
                'status' => $item->purchaseOrder->status ?? 'N/A',
            ];
        })->toArray();

        // Log the archive action
        $this->auditService->logPartArchived(
            $part->id,
            $part->part_number,
            $part->name,
            $affectedPOsLog,
            $force,
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

        // Send notifications to admins and company owners
        $creator = $request->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'inventory',
                    'action' => 'archive_part',
                    'title' => 'Inventory Part Archived',
                    'message' => $this->notificationService->formatInventoryMessage('archive_part', $part->name),
                    'data' => [
                        'partId' => $part->id,
                        'partName' => $part->name,
                        'partNumber' => $part->part_number,
                        'createdBy' => [
                            'id' => $creator->id,
                            'name' => $creator->first_name . ' ' . $creator->last_name,
                            'userType' => $creator->user_type,
                        ],
                    ],
                    'created_by' => $creator->id,
                ],
                $creator->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send inventory part archive notifications', [
                'part_id' => $part->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Part archived successfully',
            'data' => $part,
            'affected_purchase_orders' => $affectedPOsLog,
        ]);
    }

    public function restore(Request $request, InventoryPart $part)
    {
        // Check company ownership
        if ($part->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Check permission
        if ($denied = $this->requirePermission('inventory', 'parts_restore')) {
            return $denied;
        }

        // Check if part is archived
        if (!$part->is_archived) {
            return response()->json([
                'success' => false,
                'message' => 'Part is not archived'
            ], 422);
        }

        // Restore the part
        $part->is_archived = false;
        $part->save();

        // Log the restore action
        $this->auditService->logPartRestored(
            $part->id,
            $part->part_number,
            $part->name,
            $request->user()->id,
            $request->user()->email,
            $request->user()->company_id,
            $request->ip()
        );

        // Clear cache
        $this->cacheService->clearPartCache($request->user()->company_id);

        // Send notifications to admins and company owners
        $creator = $request->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'inventory',
                    'action' => 'restore_part',
                    'title' => 'Inventory Part Restored',
                    'message' => $this->notificationService->formatInventoryMessage('restore_part', $part->name),
                    'data' => [
                        'partId' => $part->id,
                        'partName' => $part->name,
                        'partNumber' => $part->part_number,
                        'createdBy' => [
                            'id' => $creator->id,
                            'name' => $creator->first_name . ' ' . $creator->last_name,
                            'userType' => $creator->user_type,
                        ],
                    ],
                    'created_by' => $creator->id,
                ],
                $creator->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send inventory part restore notifications', [
                'part_id' => $part->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Part restored successfully',
            'data' => $part,
        ]);
    }

    /**
     * Bulk import parts from CSV/XLSX file
     * 
     * POST /api/inventory/parts/import
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkImport(Request $request)
    {
        // Check permission - Only Manager and Admin can import
        if ($denied = $this->requirePermission('inventory', 'create')) {
            return $denied;
        }

        // Validate file upload
        $request->validate([
            'file' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
                        $fail('The file must be a CSV, XLSX, or XLS file.');
                    }
                },
                'max:10240'
            ],
        ]);

        $user = $request->user();
        $companyId = $user->company_id;
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        // Set memory limit for large files
        ini_set('memory_limit', '512M');
        set_time_limit(600); // 10 minutes

        try {
            // Parse file based on extension
            $rows = $this->parseFile($file, $extension);
            
            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is empty or could not be parsed'
                ], 400);
            }

            // Track duplicate part_numbers within the file
            $filePartNumbers = [];
            $duplicateInFile = [];

            // Validate and process each row
            $validRows = [];
            $invalidRows = [];
            $rowNumber = 1; // Start from 1 (header is row 0)

            foreach ($rows as $row) {
                $rowNumber++;
                $errors = [];

                // Normalize header keys (trim, lowercase, replace spaces with underscores)
                $normalizedRow = [];
                foreach ($row as $key => $value) {
                    $normalizedKey = strtolower(trim(str_replace([' ', '-'], '_', $key)));
                    $normalizedRow[$normalizedKey] = $value;
                }

                // Extract required fields
                $partNumber = trim($normalizedRow['part_number'] ?? $normalizedRow['partnumber'] ?? '');
                $name = trim($normalizedRow['name'] ?? $normalizedRow['part_name'] ?? '');

                // Validate required fields
                if (empty($partNumber)) {
                    $errors[] = 'Missing part_number';
                }
                if (empty($name)) {
                    $errors[] = 'Missing name';
                }

                // Check for duplicates within the file
                if (!empty($partNumber)) {
                    if (isset($filePartNumbers[$partNumber])) {
                        $errors[] = 'Duplicate part_number in file';
                        $duplicateInFile[$partNumber] = true;
                    } else {
                        $filePartNumbers[$partNumber] = $rowNumber;
                    }
                }

                // Validate numeric fields
                if (isset($normalizedRow['unit_cost']) && !empty($normalizedRow['unit_cost'])) {
                    if (!is_numeric($normalizedRow['unit_cost']) || $normalizedRow['unit_cost'] < 0) {
                        $errors[] = 'Invalid unit_cost (must be numeric and >= 0)';
                    }
                }

                if (isset($normalizedRow['reorder_point']) && !empty($normalizedRow['reorder_point'])) {
                    if (!is_numeric($normalizedRow['reorder_point']) || $normalizedRow['reorder_point'] < 0) {
                        $errors[] = 'Invalid reorder_point (must be numeric and >= 0)';
                    }
                }

                if (isset($normalizedRow['reorder_qty']) && !empty($normalizedRow['reorder_qty'])) {
                    if (!is_numeric($normalizedRow['reorder_qty']) || $normalizedRow['reorder_qty'] < 0) {
                        $errors[] = 'Invalid reorder_qty (must be numeric and >= 0)';
                    }
                }

                if (isset($normalizedRow['reorder_quantity']) && !empty($normalizedRow['reorder_quantity'])) {
                    if (!is_numeric($normalizedRow['reorder_quantity']) || $normalizedRow['reorder_quantity'] < 0) {
                        $errors[] = 'Invalid reorder_quantity (must be numeric and >= 0)';
                    }
                }

                if (isset($normalizedRow['minimum_stock']) && !empty($normalizedRow['minimum_stock'])) {
                    if (!is_numeric($normalizedRow['minimum_stock']) || $normalizedRow['minimum_stock'] < 0) {
                        $errors[] = 'Invalid minimum_stock (must be numeric and >= 0)';
                    }
                }

                if (isset($normalizedRow['maximum_stock']) && !empty($normalizedRow['maximum_stock'])) {
                    if (!is_numeric($normalizedRow['maximum_stock']) || $normalizedRow['maximum_stock'] < 0) {
                        $errors[] = 'Invalid maximum_stock (must be numeric and >= 0)';
                    }
                }

                // Validate boolean fields
                if (isset($normalizedRow['is_consumable']) && !empty($normalizedRow['is_consumable'])) {
                    $isConsumable = strtolower(trim($normalizedRow['is_consumable']));
                    if (!in_array($isConsumable, ['yes', 'no', 'true', 'false', '1', '0', 'y', 'n'])) {
                        $errors[] = 'Invalid is_consumable (must be yes/no or true/false)';
                    }
                }

                if (isset($normalizedRow['usage_tracking']) && !empty($normalizedRow['usage_tracking'])) {
                    $usageTracking = strtolower(trim($normalizedRow['usage_tracking']));
                    if (!in_array($usageTracking, ['yes', 'no', 'true', 'false', '1', '0', 'y', 'n'])) {
                        $errors[] = 'Invalid usage_tracking (must be yes/no or true/false)';
                    }
                }

                // If there are errors, add to invalid rows
                if (!empty($errors)) {
                    $invalidRows[] = [
                        'row_number' => $rowNumber,
                        'errors' => $errors,
                        'data' => $normalizedRow
                    ];
                } else {
                    // Prepare valid row data
                    $validRows[] = [
                        'row_number' => $rowNumber,
                        'data' => $normalizedRow
                    ];
                }
            }

            // Process valid rows - batch insert/update
            $importedCount = 0;
            $updatedCount = 0;
            $createdCount = 0;

            // Process in batches of 100 for performance
            $batchSize = 100;
            $batches = array_chunk($validRows, $batchSize);

            DB::beginTransaction();

            try {
                foreach ($batches as $batch) {
                    foreach ($batch as $rowData) {
                        $normalizedRow = $rowData['data'];
                        $partNumber = trim($normalizedRow['part_number'] ?? $normalizedRow['partnumber'] ?? '');

                        try {
                            // Find existing part by part_number within company
                            $existingPart = InventoryPart::forCompany($companyId)
                                ->where('part_number', $partNumber)
                                ->first();

                            // Prepare data for insert/update
                            $partData = [
                                'company_id' => $companyId,
                                'user_id' => $user->id,
                                'part_number' => $partNumber,
                                'name' => trim($normalizedRow['name'] ?? $normalizedRow['part_name'] ?? ''),
                                'description' => trim($normalizedRow['description'] ?? $normalizedRow['desc'] ?? null),
                                'manufacturer' => trim($normalizedRow['manufacturer'] ?? null),
                                'maintenance_category' => trim($normalizedRow['maintenance_category'] ?? $normalizedRow['category'] ?? null),
                                'uom' => trim($normalizedRow['uom'] ?? $normalizedRow['unit_of_measure'] ?? 'each'),
                                'unit_cost' => isset($normalizedRow['unit_cost']) && is_numeric($normalizedRow['unit_cost']) 
                                    ? (float) $normalizedRow['unit_cost'] : 0,
                                'category_id' => !empty($normalizedRow['category_id']) && is_numeric($normalizedRow['category_id']) 
                                    ? (int) $normalizedRow['category_id'] : null,
                                'reorder_point' => isset($normalizedRow['reorder_point']) && is_numeric($normalizedRow['reorder_point']) 
                                    ? (int) $normalizedRow['reorder_point'] : 0,
                                'reorder_qty' => isset($normalizedRow['reorder_qty']) && is_numeric($normalizedRow['reorder_qty']) 
                                    ? (int) $normalizedRow['reorder_qty'] 
                                    : (isset($normalizedRow['reorder_quantity']) && is_numeric($normalizedRow['reorder_quantity']) 
                                        ? (int) $normalizedRow['reorder_quantity'] : 0),
                                'minimum_stock' => isset($normalizedRow['minimum_stock']) && is_numeric($normalizedRow['minimum_stock']) 
                                    ? (int) $normalizedRow['minimum_stock'] : null,
                                'maximum_stock' => isset($normalizedRow['maximum_stock']) && is_numeric($normalizedRow['maximum_stock']) 
                                    ? (int) $normalizedRow['maximum_stock'] : null,
                                'barcode' => trim($normalizedRow['barcode'] ?? null),
                                'is_consumable' => $this->parseBoolean($normalizedRow['is_consumable'] ?? null),
                                'usage_tracking' => $this->parseBoolean($normalizedRow['usage_tracking'] ?? null),
                                'status' => trim($normalizedRow['status'] ?? 'active'),
                                'is_archived' => false,
                                'abc_class' => !empty($normalizedRow['abc_class']) ? strtoupper(trim($normalizedRow['abc_class'])) : null,
                            ];

                            if ($existingPart) {
                                // Update existing part
                                $existingPart->update($partData);
                                $updatedCount++;
                                $importedCount++;
                            } else {
                                // Check if part_number exists globally (might belong to different company)
                                $globalPart = InventoryPart::where('part_number', $partNumber)->first();
                                if ($globalPart && $globalPart->company_id !== $companyId) {
                                    // Part exists in different company - add to invalid rows
                                    $invalidRows[] = [
                                        'row_number' => $rowData['row_number'],
                                        'errors' => ['Part number already exists in another company'],
                                        'data' => $normalizedRow
                                    ];
                                    continue; // Skip this row
                                }

                                // Create new part
                                InventoryPart::create($partData);
                                $createdCount++;
                                $importedCount++;
                            }
                        } catch (\Exception $e) {
                            // Handle database errors for this specific row
                            $invalidRows[] = [
                                'row_number' => $rowData['row_number'],
                                'errors' => ['Database error: ' . $e->getMessage()],
                                'data' => $normalizedRow
                            ];
                            continue; // Skip this row and continue with next
                        }
                    }
                }

                DB::commit();

                // Clear cache
                $this->cacheService->clearPartCache($companyId);

                // Log the bulk import
                $this->auditService->logPartsBulkImport(
                    $user->id,
                    $user->email,
                    $companyId,
                    $importedCount,
                    count($invalidRows),
                    $request->ip()
                );

                // Generate error report if there are invalid rows
                $errorReportUrl = null;
                if (!empty($invalidRows)) {
                    $errorReportUrl = $this->generateErrorReport($invalidRows, $user->id);
                }

                // Send notifications to admins and company owners
                try {
                    $this->notificationService->createForAdminsAndOwners(
                        $companyId,
                        [
                            'type' => 'inventory',
                            'action' => 'import_parts',
                            'title' => 'Parts Imported',
                            'message' => $this->notificationService->formatInventoryMessage('import_parts', "{$importedCount} parts"),
                            'data' => [
                                'importedCount' => $importedCount,
                                'createdCount' => $createdCount,
                                'updatedCount' => $updatedCount,
                                'invalidCount' => count($invalidRows),
                                'createdBy' => [
                                    'id' => $user->id,
                                    'name' => $user->first_name . ' ' . $user->last_name,
                                    'userType' => $user->user_type,
                                ],
                            ],
                            'created_by' => $user->id,
                        ],
                        $user->id
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to send inventory parts import notifications', [
                        'imported_count' => $importedCount,
                        'error' => $e->getMessage()
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Import completed',
                    'data' => [
                        'imported_count' => $importedCount,
                        'created_count' => $createdCount,
                        'updated_count' => $updatedCount,
                        'failed_count' => count($invalidRows),
                        'invalid_rows' => $invalidRows,
                        'error_report_url' => $errorReportUrl,
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Bulk import error: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Import failed: ' . $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('File parsing error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to parse file: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Parse CSV or XLSX file
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $extension
     * @return array
     */
    private function parseFile($file, $extension)
    {
        if (in_array($extension, ['csv'])) {
            // Parse CSV
            $handle = fopen($file->getRealPath(), 'r');
            if (!$handle) {
                throw new \Exception('Could not open CSV file');
            }

            $rows = [];
            $header = null;

            while (($row = fgetcsv($handle)) !== false) {
                if ($header === null) {
                    $header = array_map('trim', $row);
                    continue;
                }

                if (count($row) !== count($header)) {
                    // Skip malformed rows
                    continue;
                }

                $rows[] = array_combine($header, $row);
            }

            fclose($handle);
            return $rows;

        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            // Parse Excel using Laravel Excel
            if (!class_exists('Maatwebsite\\Excel\\Facades\\Excel')) {
                throw new \Exception('Excel import not supported. Install maatwebsite/excel.');
            }

            $data = Excel::toArray(null, $file);
            if (empty($data) || empty($data[0])) {
                throw new \Exception('Excel file is empty');
            }

            $rows = $data[0];
            $header = array_map('trim', array_shift($rows));

            return array_map(function($row) use ($header) {
                if (count($row) !== count($header)) {
                    // Pad or trim row to match header length
                    $row = array_slice($row, 0, count($header));
                    $row = array_pad($row, count($header), '');
                }
                return array_combine($header, $row);
            }, $rows);

        } else {
            throw new \Exception('Unsupported file format. Only CSV and XLSX are supported.');
        }
    }

    /**
     * Parse boolean value from string
     * 
     * @param mixed $value
     * @return bool|null
     */
    private function parseBoolean($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['yes', 'true', '1', 'y']) ? true : 
               (in_array($value, ['no', 'false', '0', 'n']) ? false : null);
    }

    /**
     * Generate error report CSV file
     * 
     * @param array $invalidRows
     * @param int $userId
     * @return string|null URL to download the error report
     */
    private function generateErrorReport(array $invalidRows, int $userId)
    {
        try {
            $fileName = 'error_report_' . date('YmdHis') . '_' . $userId . '.csv';
            $filePath = 'imports/error_reports/' . $fileName;

            $handle = fopen(storage_path('app/' . $filePath), 'w');
            
            // Write header
            fputcsv($handle, ['Row Number', 'Errors', 'Data (JSON)']);

            // Write error rows
            foreach ($invalidRows as $row) {
                fputcsv($handle, [
                    $row['row_number'],
                    implode('; ', $row['errors']),
                    json_encode($row['data'])
                ]);
            }

            fclose($handle);

            // Return URL for download
            return '/api/download/import-error-report/' . basename($filePath);

        } catch (\Exception $e) {
            Log::error('Failed to generate error report: ' . $e->getMessage());
            return null;
        }
    }
}


