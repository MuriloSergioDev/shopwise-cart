<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shopwise\Kit\Auth\VerifyJwt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `auth.jwt.optional`: guests send no token and pass through with no user; a request that does send
 * one is verified by the kit's `auth.jwt` (an invalid or expired token is still a 401, so the BFF refreshes it).
 */
final readonly class OptionalJwt
{
    public function __construct(private VerifyJwt $verifyJwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() === null) {
            /** @var Response */
            return $next($request);
        }

        return $this->verifyJwt->handle($request, $next);
    }
}
