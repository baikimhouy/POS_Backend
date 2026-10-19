<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 20), 100));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $this->audit->log('user.created', $user, null, ['email' => $user->email, 'role' => $user->role->value]);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): UserResource|JsonResponse
    {
        $active = $request->boolean('is_active');

        if ($user->is($request->user()) && ! $active) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
                'errors' => ['is_active' => ['You cannot deactivate your own account.']],
            ], 422);
        }

        $before = ['is_active' => $user->is_active];
        $user->update(['is_active' => $active]);

        $this->audit->log('user.status_changed', $user, $before, ['is_active' => $active]);

        return new UserResource($user);
    }
}
