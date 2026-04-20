<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Auth\Services\UserService;
use Modules\Auth\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /* --- WEB METHODS --- */

    /**
     * Show list of users for approval.
     */
    public function webIndex(Request $request)
    {
        $status = $request->get('status', 'pending');
        $users = $this->userService->listByStatus($status, $request->get('limit', 15));

        return view('auth::admin.users', compact('users', 'status'));
    }

    /**
     * Approve a user via Web.
     */
    public function webApprove(int $id)
    {
        $this->userService->approveUser($id);

        return back()->with('success', 'User approved successfully.');
    }

    /* --- API METHODS --- */

    /**
     * List users by status.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status', 'pending');
        $users = $this->userService->listByStatus($status, $request->get('limit', 15));

        return UserResource::collection($users)->response();
    }

    /**
     * Approve a user.
     */
    public function approve(int $id): JsonResponse
    {
        $user = $this->userService->approveUser($id);

        return response()->json([
            'message' => 'User approved successfully.',
            'user' => new UserResource($user),
        ]);
    }
}
