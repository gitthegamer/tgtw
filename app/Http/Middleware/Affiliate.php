<?php

namespace App\Http\Middleware;

use App\Helpers\Affiliate as HelpersAffiliate;
use App\Models\Link;
use Closure;
use Illuminate\Http\Request;

class Affiliate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->has('ref') && HelpersAffiliate::validate_code($request->ref)) {
            session()->put('ref', $request->ref);
        }
        return $next($request);
    }
}
