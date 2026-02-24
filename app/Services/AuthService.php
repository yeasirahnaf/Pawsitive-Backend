<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const MAX_ATTEMPTS     = 5;
    private const LOCKOUT_MINUTES  = 15;

    /**
     * Register a new customer and return a Sanctum token.
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'], // hashed by cast
            'phone'    => $data['phone'] ?? null,
            'role'     => 'customer',
        ]);

        $token = $user->createToken('auth', ['role:customer'])->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Authenticate a user. Enforces 5-attempt lockout (15-minute window).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        // ─── Lockout check ────────────────────────────────────────────────────
        if ($user->isLocked()) {
            $minutes = (int) now()->diffInMinutes($user->locked_until, false) * -1;
            throw ValidationException::withMessages([
                'email' => ["Account is locked. Try again in {$minutes} minute(s)."],
            ]);
        }

        // ─── Password verification ────────────────────────────────────────────
        if (! Hash::check($password, $user->password)) {
            $attempts = $user->failed_login_attempts + 1;

            $update = ['failed_login_attempts' => $attempts];

            if ($attempts >= self::MAX_ATTEMPTS) {
                $update['locked_until']          = now()->addMinutes(self::LOCKOUT_MINUTES);
                $update['failed_login_attempts'] = 0; // reset after lockout
            }

            $user->update($update);

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        // ─── Reset counters on success ────────────────────────────────────────
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);

        $ability = "role:{$user->role}";
        $token   = $user->createToken('auth', [$ability])->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Revoke the current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
