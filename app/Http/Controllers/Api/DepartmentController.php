<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments.
     */
    public function index(Request $request)
    {
        $query = Department::with(['company', 'manager', 'createdBy']);

        // Filter by active status
        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%")
                  ->orWhere('code', 'like', "%$search%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $departments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'departments' => $departments->items(),
                'pagination' => [
                    'current_page' => $departments->currentPage(),
                    'last_page' => $departments->lastPage(),
                    'per_page' => $departments->perPage(),
                    'total' => $departments->total(),
                    'from' => $departments->firstItem(),
                    'to' => $departments->lastItem(),
                ],
            ]
        ]);
    }

    /**
     * Display the specified department.
     */
    public function show(Department $department)
    {
        $department->load(['company', 'manager', 'createdBy', 'assets', 'users']);
        return response()->json([
            'success' => true,
            'data' => [
                'department' => $department,
            ]
        ]);
    }

    /**
     * Store a newly created department.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'company_id' => 'required|exists:companies,id',
            'manager_id' => 'nullable|exists:users,id',
            'code' => 'nullable|string|max:10',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Check for unique name within company
        $existingDepartment = Department::where('company_id', $request->company_id)
            ->where('name', $request->name)
            ->first();

        if ($existingDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'A department with this name already exists in this company.'
            ], 422);
        }

        $department = Department::create([
            'name' => $request->name,
            'description' => $request->description,
            'company_id' => $request->company_id,
            'user_id' => $request->user()->id ?? null,
            'manager_id' => $request->manager_id,
            'code' => $request->code,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->sort_order ?? 0,
        ]);

        $department->load(['company', 'manager', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'data' => $department
        ], 201);
    }

    /**
     * Update the specified department.
     */
    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'company_id' => 'sometimes|required|exists:companies,id',
            'manager_id' => 'nullable|exists:users,id',
            'code' => 'nullable|string|max:10',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Check for unique name within company (excluding current department)
        $companyId = $request->company_id ?? $department->company_id;
        $existingDepartment = Department::where('company_id', $companyId)
            ->where('name', $request->name)
            ->where('id', '!=', $department->id)
            ->first();

        if ($existingDepartment) {
            return response()->json([
                'success' => false,
                'message' => 'A department with this name already exists in this company.'
            ], 422);
        }

        $department->update([
            'name' => $request->name,
            'description' => $request->description,
            'company_id' => $companyId,
            'manager_id' => $request->manager_id,
            'code' => $request->code,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->sort_order ?? $department->sort_order,
        ]);

        $department->load(['company', 'manager', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully',
            'data' => $department
        ]);
    }

    /**
     * Remove the specified department.
     */
    public function destroy(Department $department)
    {
        // Check if department is being used by any assets or users
        if ($department->assets()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department that has assets assigned to it.'
            ], 400);
        }

        if ($department->users()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department that has users assigned to it.'
            ], 400);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully',
        ]);
    }

    /**
     * Get all active departments for dropdown/select.
     */
    public function list()
    {
        $departments = Department::with(['manager', 'createdBy'])
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }
} 