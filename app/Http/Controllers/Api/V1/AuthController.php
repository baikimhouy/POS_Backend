<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** SPA flow: GET /sanctum/csrf-cookie first, then POST here. Session cookie, no tokens in localStorage. */
    public function login(LoginRequest $request): UserResource
    {
        $attempt = Auth::guard('web')->attempt([
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'is_active' => true, // deactivated users cannot sign in
        ]);

        if (! $attempt) {
            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        $request->session()->regenerate();

        return new UserResource($request->user('web'));
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
