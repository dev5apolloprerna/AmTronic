<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddApiResponseStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::addStatus($next($request));
    }

    /**
     * Add a predictable boolean status to JSON API responses while leaving
     * streamed responses, such as generated PDFs, unchanged.
     */
    public static function addStatus(Response $response): Response
    {
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        $payload = is_array($payload) && ! array_is_list($payload)
            ? $payload
            : ['data' => $payload];

        $response->setData([
            'status' => $response->isSuccessful(),
            ...$payload,
        ]);

        return $response;
    }
}
