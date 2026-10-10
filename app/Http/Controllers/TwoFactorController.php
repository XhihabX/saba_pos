<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    protected TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    public function setup(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        if (! in_array($user->role, ['super_admin', 'merchant'])) {
            return response()->json(['message' => '2FA is restricted to Super Admin and Merchant roles.'], 403);
        }

        $secret = $user->two_factor_secret ? decrypt($user->two_factor_secret) : $this->twoFactorService->generateSecretKey();
        $user->two_factor_secret = encrypt($secret);
        $user->save();

        $qrCodeUrl = 'otpauth://totp/IOT%20POS:'.rawurlencode($user->email)."?secret={$secret}&issuer=IOT%20POS";

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'is_confirmed' => ! empty($user->two_factor_confirmed_at),
        ]);
    }

    public function enable(Request $request)
    {
        $user = auth()->user();
        $code = (string) $request->input('code', '');

        if (empty($user->two_factor_secret)) {
            throw ValidationException::withMessages(['code' => ['2FA setup is not initialized.']]);
        }

        $secret = decrypt($user->two_factor_secret);
        if (! $this->twoFactorService->verifyKey($secret, $code)) {
            throw ValidationException::withMessages(['code' => ['Invalid 2FA verification code.']]);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '2FA TOTP enabled successfully.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function challenge()
    {
        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verify(Request $request)
    {
        $user = auth()->user();
        if (! $user) {
            $userId = session('2fa:user_id');
            if ($userId) {
                $user = User::find($userId);
            }
        }

        if (! $user || empty($user->two_factor_secret)) {
            return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
        }

        $code = (string) $request->input('code', '');
        $secret = decrypt($user->two_factor_secret);

        $valid = $this->twoFactorService->verifyKey($secret, $code);

        if (! $valid && ! empty($user->two_factor_recovery_codes)) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true) ?: [];
            if (in_array(strtoupper($code), $recoveryCodes)) {
                $valid = true;
                $recoveryCodes = array_diff($recoveryCodes, [strtoupper($code)]);
                $user->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
                $user->save();
            }
        }

        if (! $valid) {
            throw ValidationException::withMessages(['code' => ['Invalid 2FA code or recovery code.']]);
        }

        session(['2fa:verified' => true]);
        session()->forget('2fa:user_id');

        if (! auth()->check()) {
            auth()->login($user);
        }

        return redirect()->intended('/dashboard');
    }
}
