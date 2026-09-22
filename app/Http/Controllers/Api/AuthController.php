<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::with('designation')->where('email', $credentials['email'])->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 422);
        }

        if (! $user->canApiLogin()) {
            return response()->json(['message' => 'This account is not enabled for API login.'], 403);
        }

        $plainToken = Str::random(80);
        $user->forceFill(['api_token' => hash('sha256', $plainToken)])->save();

        return response()->json([
            'message' => 'Login successful.',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => $this->userData($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->forceFill(['api_token' => null])->save();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'designation' => $user->designation?->name,
        ];
    }
}
