<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

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
        public function profile(Request $request)
    {
        return response()->json(['data' => $this->userData($request->user()->load('designation'))]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => $this->userData($user->fresh('designation')),
        ]);
    }

    public function sendPasswordResetOtp(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::with('designation')->where('email', $data['email'])->first();

        // Always return the same response so this endpoint cannot be used to
        // discover which email addresses have an account.
        if ($user?->canApiLogin()) {
            $otp = (string) random_int(100000, 999999);

            DB::table('password_reset_otps')->updateOrInsert(
                ['email' => $user->email],
                [
                    'otp' => Hash::make($otp),
                    'expires_at' => now()->addMinutes(10),
                    'attempts' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            Mail::raw(
                "Your AmTronic password reset OTP is {$otp}. It expires in 10 minutes.",
                fn ($message) => $message->to($user->email)->subject('AmTronic password reset OTP'),
            );
        }

        return response()->json([
            'message' => 'If the email is eligible, a password reset OTP has been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);
        $record = DB::table('password_reset_otps')->where('email', $data['email'])->first();

        if (! $record || now()->greaterThan($record->expires_at) || $record->attempts >= 5) {
            if ($record) {
                DB::table('password_reset_otps')->where('email', $data['email'])->delete();
            }

            return response()->json(['message' => 'The OTP is invalid or has expired.'], 422);
        }

        if (! Hash::check($data['otp'], $record->otp)) {
            DB::table('password_reset_otps')->where('email', $data['email'])->increment('attempts');

            return response()->json(['message' => 'The OTP is invalid or has expired.'], 422);
        }

        $user = User::with('designation')->where('email', $data['email'])->first();
        if (! $user?->canApiLogin()) {
            DB::table('password_reset_otps')->where('email', $data['email'])->delete();

            return response()->json(['message' => 'The OTP is invalid or has expired.'], 422);
        }

        DB::transaction(function () use ($user, $data) {
            $user->update(['password' => $data['password'], 'api_token' => null]);
            DB::table('password_reset_otps')->where('email', $data['email'])->delete();
        });

        return response()->json(['message' => 'Password reset successfully.']);
    }


    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'designation' => $user->designation?->name,
            'status' => $user->status,
        ];
    }
}
