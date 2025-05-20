<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Mail\PasswordResetMail;

class AuthPasswordController extends Controller
{
    /**
     * Send a reset link to the given user.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if the email exists in the users table
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            // Don't reveal that the user doesn't exist
            return response()->json(['status' => 'If this is a valid account email, you will receive a password reset email.']);
        }
        
        // Generate a token
        $token = Str::random(64);
        
        // Store the token in the password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $token,
                'created_at' => Carbon::now()
            ]
        );
        
        // Create the reset URL
        $resetUrl = url('/reset-password') . '?token=' . $token . '&email=' . urlencode($request->email);
        
        // Send the password reset email
        try {
            Mail::to($request->email)->send(new PasswordResetMail($resetUrl, $request->email));
        } catch (\Exception $e) {
            // Log the error but don't expose it to the user
            Log::error('Failed to send password reset email: ' . $e->getMessage());
        }
        
        // For development, return the token and URL
        return response()->json([
            'status' => 'If this is a valid account email, you will receive a password reset email.',
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        // Find the token record
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        // Check if token exists and is valid (not expired)
        if (!$tokenRecord) {
            return response()->json(['email' => 'Invalid or expired token'], 400);
        }

        // Check if token is not older than 60 minutes
        if (Carbon::parse($tokenRecord->created_at)->addMinutes(60)->isPast()) {
            // Delete expired token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['email' => 'Token has expired'], 400);
        }

        // Find the user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['email' => 'User not found'], 404);
        }

        // Update the user's password
        $user->password = Hash::make($request->password);
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Delete the token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Fire password reset event
        event(new PasswordReset($user));

        return response()->json(['status' => 'Password has been reset successfully']);
    }
}
