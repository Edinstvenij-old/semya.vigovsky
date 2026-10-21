<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** TOTP (Google Authenticator, Aegis, 1Password …) with one-time recovery codes. */
class TwoFactor
{
    private const SESSION_KEY = 'two_factor_verified_at';

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        return strlen($code) === 6 && $this->google2fa->verifyKey($secret, $code, 1);
    }

    /** Accepts either a TOTP code or an unused recovery code (which is then consumed). */
    public function verifyUser(User $user, string $code): bool
    {
        if ($this->verifyCode($user->two_factor_secret, $code)) {
            return true;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $normalized = strtoupper(trim($code));

        foreach ($codes as $i => $recovery) {
            if (hash_equals($recovery, $normalized)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function recoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    public function markSessionVerified(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->timestamp);
    }

    public function sessionVerified(Request $request): bool
    {
        $at = $request->session()->get(self::SESSION_KEY);

        return $at && now()->timestamp - $at < config('familyhub.two_factor_ttl') * 60;
    }

    public function forgetSession(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
