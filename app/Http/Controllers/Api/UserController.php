<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users dengan filter
     */
    public function index(Request $request)
    {
        $request->merge([
            'role' => $request->filled('role') ? $request->role : null,
            'search' => $request->filled('search') ? $request->search : null,
            'is_active' => $request->has('is_active') && $request->is_active !== '' ? $request->is_active : null,
            'per_page' => $request->filled('per_page') ? (int) $request->per_page : null,
            'sort_by' => $request->filled('sort_by') ? $request->sort_by : null,
            'sort_order' => $request->filled('sort_order') ? $request->sort_order : null,
        ]);

        $validator = Validator::make($request->all(), [
            'role' => 'nullable|in:admin,owner,kasir',
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string',
            'sort_by' => 'nullable|in:name,email,role,is_active,created_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();

        $query = User::query();

        // Filter by role
        if (!empty($filters['role'] ?? null)) {
            $query->where('role', $filters['role']);
        }

        // Filter by active status
        if (!is_null($filters['is_active'] ?? null)) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Search by name or email
        if (!empty($filters['search'] ?? null)) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        $users = $query->paginate($perPage);

        // Hide password dari response
        $users->getCollection()->makeHidden(['password', 'remember_token']);

        return response()->json([
            'success' => true,
            'data' => $users
        ], 200);
    }

    /**
     * Get single user detail
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Hide sensitive data
        $user->makeHidden(['password', 'remember_token']);

        // Get user statistics
        $stats = [
            'total_transactions' => $user->transactions()->count(),
            'total_sales' => $user->transactions()->sum('total_amount'),
            'total_purchases' => $user->purchases()->count(),
            'total_purchase_amount' => $user->purchases()->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'statistics' => $stats
            ]
        ], 200);
    }

    /**
     * Create new user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,owner,kasir',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'Nama harus diisi',
            'email.required' => 'Email harus diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah digunakan',
            'password.required' => 'Password harus diisi',
            'password.min' => 'Password minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
            'role.required' => 'Role harus dipilih',
            'role.in' => 'Role tidak valid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_active' => $request->get('is_active', true),
        ]);

        // Hide password
        $user->makeHidden(['password', 'remember_token']);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan',
            'data' => $user
        ], 201);
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Prevent admin from changing their own role
        if ($user->id === $request->user()->id && $request->has('role')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat mengubah role Anda sendiri'
            ], 403);
        }

        // Prevent admin from deactivating themselves
        if ($user->id === $request->user()->id && $request->has('is_active') && !$request->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($id)],
            'password' => 'sometimes|string|min:8|confirmed',
            'role' => 'sometimes|in:admin,owner,kasir',
            'is_active' => 'boolean',
        ], [
            'name.required' => 'Nama harus diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah digunakan',
            'password.min' => 'Password minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
            'role.in' => 'Role tidak valid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'email', 'role', 'is_active']);

        // Update password jika diisi
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // Hide password
        $user->makeHidden(['password', 'remember_token']);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diupdate',
            'data' => $user
        ], 200);
    }

    /**
     * Delete user
     */
    public function destroy(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Prevent admin from deleting themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri'
            ], 403);
        }

        // Check if user has transactions
        $transactionCount = $user->transactions()->count();
        if ($transactionCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "User tidak dapat dihapus karena memiliki {$transactionCount} transaksi. Nonaktifkan user sebagai gantinya."
            ], 400);
        }

        $userName = $user->name;
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => "User {$userName} berhasil dihapus"
        ], 200);
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // Prevent admin from deactivating themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat mengubah status akun Anda sendiri'
            ], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'aktif' : 'nonaktif';

        return response()->json([
            'success' => true,
            'message' => "User berhasil di{$status}kan",
            'data' => $user->makeHidden(['password', 'remember_token'])
        ], 200);
    }

    /**
     * Get user roles (for dropdown)
     */
    public function roles()
    {
        $roles = [
            ['value' => 'admin', 'label' => 'Admin', 'description' => 'Full access ke semua fitur'],
            ['value' => 'owner', 'label' => 'Owner', 'description' => 'Akses ke laporan, pembelian, dan manajemen stok'],
            ['value' => 'kasir', 'label' => 'Kasir', 'description' => 'Akses ke kasir dan produk saja'],
        ];

        return response()->json([
            'success' => true,
            'data' => $roles
        ], 200);
    }

    /**
     * Get user statistics summary (for dashboard)
     */
    public function statistics()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'by_role' => [
                'admin' => User::where('role', 'admin')->count(),
                'owner' => User::where('role', 'owner')->count(),
                'kasir' => User::where('role', 'kasir')->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ], 200);
    }
}