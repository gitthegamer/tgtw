<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\Member;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Login
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
        if ($request->filled('member') && request()->filled('token')) {
            $admin = User::where('token', $request->token)->first();
            if ($admin) {
                $member = Member::where('token', $request->member)->first();
                if ($member) {
                    Auth::login($member);
                    session()->put('token', $member->token);
                }
            }
        }

        if (auth()->user() && auth()->user()->status == 2) {
            Auth::logout();
        }

        return $next($request);
    }
}
