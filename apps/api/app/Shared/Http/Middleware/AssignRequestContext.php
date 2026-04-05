<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = trim((string) $request->headers->get('X-Request-Id', ''));
        $traceId = trim((string) $request->headers->get('X-Trace-Id', ''));

        if ($requestId === '') {
            $requestId = 'req_' . Str::random(12);
        }

        if ($traceId === '') {
            $traceId = $requestId;
        }

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('trace_id', $traceId);

        Context::flush()->add([
            'request_id' => $requestId,
            'trace_id' => $traceId,
            'request_method' => $request->method(),
            'request_path' => $request->path(),
        ]);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-Id', $requestId);
            $response->headers->set('X-Trace-Id', $traceId);

            return $response;
        } finally {
            Context::flush();
        }
    }
}
