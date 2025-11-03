<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Display a listing of admins.
     */
    public function index(Request $request)
    {
        $query = Admin::query();

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $admins = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'admins' => $admins->items(),
                'pagination' => [
                    'current_page' => $admins->currentPage(),
                    'last_page' => $admins->lastPage(),
                    'per_page' => $admins->perPage(),
                    'total' => $admins->total(),
                    'from' => $admins->firstItem(),
                    'to' => $admins->lastItem(),
                ],
            ]
        ]);
    }

    /**
     * Store a newly created admin.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:8',
        ]);

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin created successfully',
            'data' => [
                'admin' => $admin,
            ]
        ], 201);
    }

    /**
     * Display the specified admin.
     */
    public function show(Admin $admin)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'admin' => $admin,
            ]
        ]);
    }

    /**
     * Update the specified admin.
     */
    public function update(Request $request, Admin $admin)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admins,email,' . $admin->id,
            'password' => 'sometimes|string|min:8',
        ]);

        $data = $request->only(['name', 'email']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $admin->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Admin updated successfully',
            'data' => [
                'admin' => $admin->fresh(),
            ]
        ]);
    }

    /**
     * Remove the specified admin.
     */
    public function destroy(Admin $admin)
    {
        $admin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin deleted successfully',
        ]);
    }
}
