<?php

namespace App\Http\Middleware;

use App\Tasks\Discord\VerifyDiscordSignatureTask;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDiscordSignature
{
    public function __construct(
        protected VerifyDiscordSignatureTask $verifySignatureTask
    ) {}

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

        if (!$this->verifySignatureTask->verify($signature, $timestamp, $body)) {
            return response('Invalid signature', 401);
        }

        return $next($request);
    }
}
