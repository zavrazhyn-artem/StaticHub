<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDiscordSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');
        $body = $request->getContent();

        if (!$signature || !$timestamp) {
            return response('Unauthorized', 401);
        }

        $publicKey = config('services.discord.public_key');

        if (!$publicKey) {
            return response('Discord public key not configured', 500);
        }

        try {
            $binarySignature = hex2bin($signature);
            $binaryPublicKey = hex2bin($publicKey);

            $msg = $timestamp . $body;

            if (!sodium_crypto_sign_verify_detached($binarySignature, $msg, $binaryPublicKey)) {
                return response('Invalid signature', 401);
            }
        } catch (\Exception $e) {
            return response('Invalid signature data', 401);
        }

        return $next($request);
    }
}
