<?php

declare(strict_types=1);

namespace App\Tasks\Discord;

use Illuminate\Support\Facades\Log;

class VerifyDiscordSignatureTask
{
    /**
     * Verify the signature of an incoming Discord interaction.
     */
    public function verify(string $signature, string $timestamp, string $body): bool
    {
        $publicKey = (string) config('services.discord.public_key');

        if (!$publicKey) {
            Log::error('Discord public key not configured');
            return false;
        }

        try {
            $binarySignature = hex2bin($signature);
            $binaryPublicKey = hex2bin($publicKey);

            if ($binarySignature === false || $binaryPublicKey === false) {
                return false;
            }

            $msg = $timestamp . $body;

            return sodium_crypto_sign_verify_detached($binarySignature, $msg, $binaryPublicKey);
        } catch (\Exception $e) {
            Log::error('Discord signature verification exception', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
