<?php

namespace App\Http\Middleware;

use App\Services\MealieClient;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateMealieToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseUrl = config('mealie.base_url');

        if (empty($baseUrl)) {
            Log::error('MEALIE_BASE_URL is not set. Configure it in .env and run: php artisan config:clear');

            return response()->json([
                'error' => 'Server misconfigured: MEALIE_BASE_URL is not set. Edit .env on the server and run "php artisan config:clear".',
            ], 500);
        }

        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'error' => 'Missing or malformed Authorization header. Expected: Authorization: Bearer <Mealie API token>',
            ], 401);
        }

        try {
            $check = Http::withHeaders(['Authorization' => $header])
                ->acceptJson()
                ->timeout(5)
                ->get($baseUrl.'/api/users/self');
        } catch (ConnectionException $e) {
            Log::error('Could not reach Mealie API', [
                'base_url' => $baseUrl,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => "Could not reach Mealie at {$baseUrl}. Verify MEALIE_BASE_URL and that the host is reachable from this server.",
            ], 502);
        }

        if ($check->status() === 401) {
            return response()->json([
                'error' => 'Mealie rejected the token. Generate a long-lived API token on the Mealie User Profile page (Profile → Manage API Tokens) and send it as "Authorization: Bearer <token>".',
            ], 401);
        }

        if ($check->failed()) {
            Log::error('Mealie API returned unexpected status', [
                'status' => $check->status(),
                'body' => $check->body(),
            ]);

            return response()->json([
                'error' => "Mealie API returned HTTP {$check->status()}",
            ], 502);
        }

        app()->instance(MealieClient::class, new MealieClient($header));

        return $next($request);
    }
}
