<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\Member;
use Closure;
use Illuminate\Http\Request;

class MultiLanguage
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
        if ($request->filled('language')) {
            $language = $request->language;
        } else {
            $language = $request->segment(1) ?? "en";
        }

        if (Language::where('code', $language)->first()) {
            session()->put('language', $language);
            if ($member = Member::where('token', $request->token)->first()) {
                $member->update(['language' => $language]);
            }
        } else {
            if ($member = Member::where('token', $language)->first()) {
                session()->put('language', $member->language);
            }
        }

        if (!in_array($language, ["en", "cn", "bm"])) {
            $language = "en";
        }
        app()->setLocale($language);

        return $next($request);
    }
}
