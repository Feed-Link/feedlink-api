<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    protected AdminUserService $service;

    public function __construct(AdminUserService $service)
    {
        $this->service = $service;
        $this->middleware(['auth:api', 'admin']);
    }

    /**
     * Get all users with filtering and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $params = $request->all();
        $users = $this->service->getUsersList($params);

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    /**
     * Get user details with all related data.
     *
     * @param string $userId
     * @return JsonResponse
     */
    public function show(string $userId): JsonResponse
    {
        $user = $this->service->getUserDetails($userId);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $user,
        ]);
    }

    /**
     * Store a new user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'contact' => 'nullable|string',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $user = $this->service->createUser($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'data' => $user,
        ], 201);
    }

    /**
     * Update user information.
     *
     * @param Request $request
     * @param string $userId
     * @return JsonResponse
     */
    public function update(Request $request, string $userId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'contact' => 'nullable|string',
        ]);

        $user = $this->service->updateUser($userId, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $user,
        ]);
    }

    /**
     * Delete a user.
     *
     * @param string $userId
     * @return JsonResponse
     */
    public function destroy(string $userId): JsonResponse
    {
        $this->service->deleteUser($userId);

        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get dashboard statistics.
     *
     * @return JsonResponse
     */
    public function getStats(): JsonResponse
    {
        $stats = $this->service->getStats();

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }
}
