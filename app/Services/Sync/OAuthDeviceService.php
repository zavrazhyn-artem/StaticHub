<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Exceptions\OAuthDeviceException;
use App\Models\OAuthDeviceCode;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\RandomException;

/**
 * RFC 8628 — OAuth 2.0 Device Authorization Grant.
 *
 * Flow:
 *   1. Bridge calls requestCode() — gets {device_code, user_code, ...},
 *      shows user_code in its UI and opens the browser to verification_uri.
 *   2. User signs in to blastr.pro, lands on /oauth/device, types or
 *      confirms the user_code, then approve()/deny().
 *   3. Bridge polls poll($deviceCode) every `interval` seconds until it
 *      receives 'granted' (with PAT) or a terminal error.
 */
final class OAuthDeviceService
{
    /** Code lifetime in seconds — RFC default is 600. */
    public const EXPIRES_IN = 600;

    /** Minimum seconds between polls — bridge gets `slow_down` if it polls faster. */
    public const POLL_INTERVAL = 5;

    /** No I/O/0/1 — Crockford-style, easy to dictate over voice or read from a screen. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** "ABCD-EFGH" — 8 chars + dash for readability. */
    private const USER_CODE_LENGTH = 8;

    /**
     * @param  list<string>  $scopes  Sanctum abilities to attach to the issued PAT.
     * @return array{device_code:string, user_code:string, verification_uri:string, verification_uri_complete:string, expires_in:int, interval:int}
     */
    public function requestCode(string $clientName, array $scopes): array
    {
        $deviceCode = $this->generateDeviceCode();
        $userCode   = $this->generateUniqueUserCode();

        OAuthDeviceCode::create([
            'device_code_hash' => $this->hashDeviceCode($deviceCode),
            'user_code'        => $userCode,
            'client_name'      => $clientName,
            'scope'            => $scopes,
            'expires_at'       => CarbonImmutable::now()->addSeconds(self::EXPIRES_IN),
        ]);

        $verificationUri = url('/oauth/device');

        return [
            'device_code'              => $deviceCode,
            'user_code'                => $userCode,
            'verification_uri'         => $verificationUri,
            'verification_uri_complete' => $verificationUri . '?user_code=' . $userCode,
            'expires_in'               => self::EXPIRES_IN,
            'interval'                 => self::POLL_INTERVAL,
        ];
    }

    /**
     * Status discriminator returned to the controller, which maps each value
     * to the corresponding RFC 8628 HTTP response.
     *
     * @return array{
     *   status: 'pending'|'slow_down'|'expired'|'denied'|'granted',
     *   access_token?: string,
     *   token_type?: string,
     *   scope?: list<string>,
     *   user?: User
     * }
     */
    public function poll(string $deviceCode): array
    {
        return DB::transaction(function () use ($deviceCode) {
            $row = OAuthDeviceCode::query()
                ->lockForUpdate()
                ->findByDeviceCodeHash($this->hashDeviceCode($deviceCode));

            if ($row === null) {
                return ['status' => 'expired'];
            }

            if ($row->isExpired()) {
                $row->delete();
                return ['status' => 'expired'];
            }

            // Throttle: enforce minimum interval between polls per RFC.
            if ($row->last_polled_at !== null
                && $row->last_polled_at->diffInSeconds(now()) < self::POLL_INTERVAL) {
                return ['status' => 'slow_down'];
            }

            if ($row->isDenied()) {
                $row->delete();
                return ['status' => 'denied'];
            }

            if (! $row->isApproved()) {
                $row->update(['last_polled_at' => now()]);
                return ['status' => 'pending'];
            }

            // Approved → mint the PAT, then burn the code so it can't be
            // exchanged twice. The transaction keeps these atomic.
            $user  = $row->user;
            $token = $user->createToken($row->client_name, $row->scope);

            $row->delete();

            return [
                'status'       => 'granted',
                'access_token' => $token->plainTextToken,
                'token_type'   => 'Bearer',
                'scope'        => $row->scope,
                'user'         => $user,
            ];
        });
    }

    public function findPendingByUserCode(string $userCode): ?OAuthDeviceCode
    {
        return OAuthDeviceCode::query()->findPendingByUserCode($this->normalizeUserCode($userCode));
    }

    /**
     * Marks a pending code as approved. Atomic update guards against a
     * second concurrent approve/deny on the same code.
     */
    public function approve(string $userCode, User $user): OAuthDeviceCode
    {
        $code = $this->findPendingByUserCode($userCode)
            ?? throw OAuthDeviceException::userCodeNotFound();

        $affected = OAuthDeviceCode::query()
            ->whereKey($code->id)
            ->whereNull('approved_at')
            ->whereNull('denied_at')
            ->update([
                'approved_at' => now(),
                'user_id'     => $user->id,
            ]);

        if ($affected === 0) {
            throw OAuthDeviceException::userCodeNotFound();
        }

        return $code->refresh();
    }

    public function deny(string $userCode, User $user): OAuthDeviceCode
    {
        $code = $this->findPendingByUserCode($userCode)
            ?? throw OAuthDeviceException::userCodeNotFound();

        $affected = OAuthDeviceCode::query()
            ->whereKey($code->id)
            ->whereNull('approved_at')
            ->whereNull('denied_at')
            ->update([
                'denied_at' => now(),
                'user_id'   => $user->id,
            ]);

        if ($affected === 0) {
            throw OAuthDeviceException::userCodeNotFound();
        }

        return $code->refresh();
    }

    /**
     * Strips dashes and uppercases — accept "abcd-efgh", "ABCDEFGH", "abcdefgh".
     */
    public function normalizeUserCode(string $input): string
    {
        return strtoupper(str_replace('-', '', trim($input)));
    }

    public function formatUserCode(string $userCode): string
    {
        return substr($userCode, 0, 4) . '-' . substr($userCode, 4);
    }

    /**
     * @throws RandomException
     */
    private function generateDeviceCode(): string
    {
        // 256 bits → URL-safe base64. Plaintext goes only to the bridge;
        // we store sha256(device_code) and never read this value again.
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function hashDeviceCode(string $deviceCode): string
    {
        return hash('sha256', $deviceCode);
    }

    /**
     * @throws OAuthDeviceException
     * @throws RandomException
     */
    private function generateUniqueUserCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = $this->randomUserCode();
            $exists = OAuthDeviceCode::query()
                ->where('user_code', $code)
                ->where('expires_at', '>', now())
                ->exists();

            if (! $exists) {
                return $code;
            }
        }

        throw OAuthDeviceException::couldNotGenerateUniqueCode();
    }

    /**
     * @throws RandomException
     */
    private function randomUserCode(): string
    {
        $alphabetLen = strlen(self::ALPHABET);
        $code = '';
        for ($i = 0; $i < self::USER_CODE_LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $alphabetLen - 1)];
        }
        return $code;
    }
}
