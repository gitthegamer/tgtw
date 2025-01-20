<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Support\Facades\Auth;

class Authenticate implements AuthenticatesRequests
{
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$guards
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            foreach ($guards as $guard) {
                if ($this->auth->guard($guard)->check()) {
                    $this->auth->shouldUse($guard);
                } else {
                    if (!$request->expectsJson()) {
                        if ($guard == "admin") {
                            throw new AuthenticationException(
                                'Unauthenticated.',
                                $guards,
                                route('admin.login')
                            );
                        }
                        if ($guard == "agent") {
                            throw new AuthenticationException(
                                'Unauthenticated.',
                                $guards,
                                route('agent.login')
                            );
                        }
                        if ($guard == "web") {
                            throw new AuthenticationException(
                                'Unauthenticated.',
                                $guards,
                                route('home', ['login' => true])
                            );
                        }
                    }
                }
            }

            if (Auth::guard($guard)->user()) {
                if (
                    !Auth::guard($guard)->user()->token ||
                    session()->get('token') != Auth::guard($guard)->user()->token ||
                    !Auth::guard($guard)->user()->isActive()
                ) {
                    Auth::guard($guard)->logout();
                    if (!$request->expectsJson()) {
                        if ($guard == "admin") {
                            throw new AuthenticationException(
                                'Unauthenticated.',
                                $guards,
                                route('admin.login')
                            );
                        }
                        if ($guard == "agent") {
                            throw new AuthenticationException(
                                'Unauthenticated.',
                                $guards,
                                route('agent.login')
                            );
                        }
                        if ($guard == "web") {
                            throw new AuthenticationException(
                                'Unauthenticated.',
                                $guards,
                                route('home', ['login' => true])
                            );
                        }
                    }
                }
            }
        }


        return $next($request);
    }
}
