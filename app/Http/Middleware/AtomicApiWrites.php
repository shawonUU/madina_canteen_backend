<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AtomicApiWrites
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) return $next($request);
        $connection = DB::connection();
        $connection->beginTransaction();
        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 400) $connection->rollBack();
            else $connection->commit();
            return $response;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
