<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Only trust the email for account matching when Google has verified it
            $raw = $googleUser->getRaw();
            $emailVerified = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! $emailVerified || ! $googleUser->getEmail()) {
                return redirect()->route('login')->with('message', 'Email akun Google Anda belum terverifikasi.');
            }

            // Prefer the stable Google ID, then fall back to email for first-time linking
            $user = User::where('google_id', $googleUser->getId())->first()
                ?? User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // If user exists but doesn't have google_id, link it
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'google_token' => $googleUser->token,
                    ]);
                }
            } else {
                // Create new user
                $user = new User([
                    'name' => $googleUser->getName() ?? 'Affiliate User',
                    'email' => $googleUser->getEmail(),
                    'password' => null, // Password is null for OAuth users
                    'google_id' => $googleUser->getId(),
                    'google_token' => $googleUser->token,
                ]);
                $user->role = UserRole::AFFILIATE; // Default to affiliate (role is not mass assignable)
                $user->save();
            }

            // Login user
            Auth::login($user, true);

            return redirect()->route('dashboard');

        } catch (\Exception $e) {
            Log::error('Google OAuth Error: ' . $e->getMessage());
            return redirect()->route('login')->with('message', 'Terjadi kesalahan saat login dengan Google. Silakan coba lagi.');
        }
    }
}
