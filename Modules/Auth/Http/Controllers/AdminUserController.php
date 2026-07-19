<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Events\RoleSynced;
use App\Models\User;
use App\Services\AuditLogger;
use Modules\Auth\Services\UserService;
use Modules\Auth\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    protected $userService;

    public function __construct(
        UserService $userService,
        protected AuditLogger $audit,
    ) {
        $this->userService = $userService;
    }

    /* --- WEB METHODS --- */

    /**
     * Show list of users for approval.
     */
    public function webIndex(Request $request)
    {
        $status = $request->get('status', 'pending');
        $limit = min((int) $request->get('limit', 15), 100);
        $users = $this->userService->listByStatus($status, $limit);

        return view('auth::admin.users', compact('users', 'status'));
    }

    /**
     * Approve a user via Web.
     */
    public function webApprove(Request $request, int $id)
    {
        $target = User::find($id);
        $user = $this->userService->approveUser($id);

        // Dual-source role sync: re-assert the Spatie pivot matches
        // `users.role` after admin mutation. Idempotent — covers the
        // re-approval case (rejected → active) and any future admin
        // flows that change `role`.
        event(new RoleSynced($user, $user->role));

        $this->audit->log(
            action: 'user.approved',
            target: $user,
            metadata: [
                'previous_status' => $target?->getOriginal('status'),
                'via' => 'web',
            ],
        );

        return back()->with('success', 'User approved successfully.');
    }

    /* --- API METHODS --- */

    /**
     * List users by status.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status', 'pending');
        $limit = min((int) $request->get('limit', 15), 100);
        $users = $this->userService->listByStatus($status, $limit);

        return UserResource::collection($users)->response();
    }

    /**
     * Approve a user.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $target = User::find($id);
        $user = $this->userService->approveUser($id);

        // Dual-source role sync: see webApprove().
        event(new RoleSynced($user, $user->role));

        $this->audit->log(
            action: 'user.approved',
            target: $user,
            metadata: [
                'previous_status' => $target?->getOriginal('status'),
                'via' => 'api',
            ],
        );

        return response()->json([
            'message' => 'User approved successfully.',
            'user' => new UserResource($user),
        ]);
    }
}
