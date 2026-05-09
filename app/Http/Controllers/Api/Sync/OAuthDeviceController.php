<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\OAuthDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RFC 8628 device-flow endpoints used by the BlastR Desktop bridge.
 *
 * Both endpoints are unauthenticated — anyone can mint a code and poll on
 * it; the actual authorization happens in the browser during approve().
 * The poll endpoint is throttled (route definition) to prevent abuse and
 * to enforce the slow_down semantics in addition to the per-code interval
 * check inside the service.
 */
final class OAuthDeviceController extends Controller
{
    public function __construct(
        private readonly OAuthDeviceService $svc
    ) {}

    public function requestCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_name' => 'required|string|max:80',
            'scope'       => 'sometimes|array',
            'scope.*'     => 'string|max:64',
        ]);

        $payload = $this->svc->requestCode(
            $data['client_name'],
            $data['scope'] ?? ['sync:read', 'sync:write'],
        );

        return response()->json($payload);
    }

    public function poll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_code' => 'required|string',
            'grant_type'  => 'sometimes|in:urn:ietf:params:oauth:grant-type:device_code',
        ]);

        $result = $this->svc->poll($data['device_code']);

        return match ($result['status']) {
            'pending'   => response()->json(['error' => 'authorization_pending'], 400),
            'slow_down' => response()->json(['error' => 'slow_down'], 400),
            'expired'   => response()->json(['error' => 'expired_token'], 400),
            'denied'    => response()->json(['error' => 'access_denied'], 400),
            'granted'   => response()->json([
                'access_token' => $result['access_token'],
                'token_type'   => $result['token_type'],
                'scope'        => implode(' ', $result['scope']),
                'user'         => [
                    'id'        => $result['user']->id,
                    'name'      => $result['user']->name,
                    'battletag' => $result['user']->battletag,
                    'avatar'    => $result['user']->avatar,
                ],
            ]),
        };
    }
}
