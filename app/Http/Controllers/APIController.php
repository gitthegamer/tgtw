<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class APIController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->locale ?? "en");
    }

    public function global()
    {
        return response()->json([
            'products' => Product::fetch(),
        ]);
    }

    public function register(Request $request)
    {
        $number = '';
        for ($i = 0; $i < 10; $i++) {
            $number .= mt_rand(0, 9);
        }
        $phone = (int)$number;

        $user = Member::create([
            'upline_type' => null,
            'upline_id' => null,
            "rank_id" => 1,
            'username' => $request->username,
            'password' => bcrypt($request->password),
            'full_name' => 'test' . Str::random(6),
            'email' => Str::random(6) . '@example.com', //random email
            'phone' => $phone, //random phone
            'balance' => 20,
        ]);
        $user->update(['token' => $token = Str::uuid(), 'last_login_at' => now()]);

        return response()->json([
            'status' => true,
            'message' => __("Authenticate successfully."),
            'data' => $user->getData(),
            'token' => $token,
        ])->withHeaders([
            'Token' => $token,
        ]);
    }

    public function login(Request $request)
    {
        $username = $request->username;
        $password = $request->password;

        $user = Member::where('username', $username)->first();
        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => __("Invalid username or password."),
            ]);
        }

        $user->update(['token' => $token = Str::uuid(), 'last_login_at' => now()]);
        $user->makeHidden(['password']);

        return response()->json([
            'status' => true,
            'message' => __("Login successfully."),
            'data' => $user->getData(),
            'token' => $token,
        ])->withHeaders([
            'Token' => $token,
        ]);
    }

    public function launchGame(Request $request)
    {
        $member = Member::whereNotNull('token')->where('token', $request->header('token') ?? '')->first();
        if (!$member) {
            return response()->json([
                'status' => false,
                'data' => null,
                'launcher' => null,
                'message' => __("Please login to continue!"),
            ]);
        }

        $response = Cache::lock('member.' . $member->id, 5)->get(function () use ($request, $member) {
            if (!$request->header('token')) {
                return response()->json([
                    'status' => false,
                    'data' => $member->getToken(),
                    'launcher' => null,
                    'message' => __("Please try again later!"),
                ]);
            }

            if ($member->token != $request->header('token')) {
                return response()->json([
                    'status' => false,
                    'data' => $member->getToken(),
                    'launcher' => null,
                    'message' => __("Please try again later!"),
                ]);
            }



            if (!$request->filled('game')) {
                $product = Product::where('code', $request->code)->first();
            } else {
                $product = Product::whereHas('games', function ($q) use ($request) {
                    return $q->where('code', $request->game);
                })->where('code', $request->code)->first();
            }

            if (!$product) {
                return response()->json([
                    'status' => false,
                    'data' => $member->getToken(),
                    'launcher' => null,
                    'message' => __("Please contact customer service."),
                ]);
            }


            if ($member->product && $member->product_id != $product->id) {
                $result = $member->withdrawal();
                if (!$result) {
                    return response()->json([
                        'status' => false,
                        'data' => $member->getToken(),
                        'launcher' => null,
                        'message' => __("Your last game is in Maintainance, your balance are not able to transfer to this game. Please try again later!"),
                    ]);
                }
            }

            $member->update(['product_id' => $product->id]);
            $launch = $member->launch($request->game, $request->isMobile);
            if ($launch === product::ERROR_PROVIDER_MAINTENANCE) {
                $member->update(['product_id' => null]);

                return response()->json([
                    'status' => false,
                    'data' => $member->getToken(),
                    'type' => 'maintenance',
                    'launcher' => null,
                    'message' => __("Game in maintenance"),
                ]);
            }
            if ($launch === product::ERROR_INTERNAL_SYSTEM || $launch === product::ERROR_ACCOUNT) {
                return response()->json([
                    'status' => false,
                    'data' => $member->getToken(),
                    'launcher' => null,
                    'message' => __("Please contact customer service."),
                ]);
            }
            if (!$member->member_accounts()->where('product_id', $product->id)->count()) {
                return response()->json([
                    'status' => false,
                    'data' => $member->getToken(),
                    'type' => 'maintenance',
                    'launcher' => null,
                    'message' => __("Game in maintenance"),
                ]);
            }
            $member = Member::find($member->id);

            if ($member->balance >= 1) {
                $result = $member->deposit();
                if (!$result) {
                    return response()->json([
                        'status' => false,
                        'data' => $member->getToken(),
                        'launcher' => null,
                        'message' => __("The Game is in Maintainance, please try again later!"),
                    ]);
                }
            }

            if ($product->hasLobby() && !$request->filled('game')) {
                return response()->json([
                    'status' => true,
                    'message' => __("Lobby Product"),
                    'data' => null,
                    'type' => 'lobby',
                    'launcher' => [
                        'gamelist' => cache()->remember('gamelist_' . $product->id, 60 * 60 * 24, function () use ($product) {
                            return $product->games()->get();
                        }),
                    ]
                ]);
            }

            if ($product->isApp()) {
                return response()->json([
                    'status' => true,
                    'data' => null,
                    'type' => 'app',
                    'launcher' => [
                        'balance' => $product->balance($member),
                        'member_account' => $launch['member_account'],
                        'ios_url' => $product->ios_download,
                        'android_url' => $product->apk_download,
                    ],
                ]);
            }

            if ($product->code == "LK") {
                return response()->json([
                    'status' => true,
                    'data' => $member->getToken(),
                    'type' => 'deeplink',
                    'launcher' => [
                        'balance' => $product->balance($member),
                        'member_account' => $launch['member_account'],
                        'ios_url' => $product->ios_download,
                        'android_url' => $product->apk_download,
                        'url' => $launch['deeplink'],
                    ],
                ]);
            }

            return response()->json([
                'status' => true,
                'data' => $member->getToken(),
                'type' => 'redirect',
                'launcher' => $launch,
                'message' => __("Please click `Launch` to start game"),
                'disclaimer' => $product->disclaimer ?? null,
            ]);
        });

        if (!$response) {
            return response()->json([
                'status' => false,
                'data' => $request->launcher_token,
                'type' => 'maintenance',
                'launcher' => null,
                'message' => __("Please try again later!"),
            ]);
        }

        return $response;
    }

    public function user(Request $request)
    {
        if (!$request->header('token') || $request->header('token') == '') {
            return response()->json([
                'status' => false,
                'message' => __("Unauthenticated."),
                'data' => null,
            ]);
        }

        $member = Member::where('token', $request->header('token') ?? '')->first();
        if (!$member) {
            return response()->json([
                'status' => false,
                'message' => __("Unauthenticated."),
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => __("Authenticate successfully."),
            'data' => $member->getData(),
            'token' => $member->getToken(),
        ])->withHeaders([
            'Token' => $member->getToken(),
        ]);
    }

    public function clear(Request $request)
    {
        if (!$request->header('token') || $request->header('token') == '') {
            return response()->json([
                'status' => false,
                'message' => __("Unauthenticated."),
                'data' => null,
            ]);
        }

        $member = Member::where('token', $request->header('token') ?? '')->first();
        if (!$member) {
            return response()->json([
                'status' => false,
                'message' => __("Unauthenticated."),
                'data' => null,
            ]);
        }

        $member->update(['product_id' => null]);

        return response()->json([
            'status' => true,
            'message' => __("Balance added successfully."),
            'data' => $member->getData(),
        ]);
    }

    public function addBalance(Request $request)
    {
        if (!$request->header('token') || $request->header('token') == '') {
            return response()->json([
                'status' => false,
                'message' => __("Unauthenticated."),
                'data' => null,
            ]);
        }

        $member = Member::where('token', $request->header('token') ?? '')->first();
        if (!$member) {
            return response()->json([
                'status' => false,
                'message' => __("Unauthenticated."),
                'data' => null,
            ]);
        }

        $member->increment('balance', 20);

        return response()->json([
            'status' => true,
            'message' => __("Balance added successfully."),
            'data' => $member->getData(),
        ]);
    }

    public function getGameList(Request $request)
    {
        Artisan::call('data:get_game_lists');

        return response()->json([
            'status' => true,
            'message' => __("Done."),
            'data' => null,
        ]);
    }

    public function withdraw(Request $request)
    {
        if (!$request->header('token') || $request->header('token') == '') {
            return response()->json([
                'status' => false,
                'message' => __("Unauthenticated."),
                'data' => null,
            ]);
        }

        $member = Member::where('token', $request->header('token') ?? '')->first();
        if (!$member) {
            return response()->json([
                'status' => false,
                'message' => __("Unauthenticated."),
                'data' => null,
            ]);
        }
        $result = $member->withdrawal();

        return response()->json([
            'status' => $result,
            'message' => $result ? __("Withdrawal successfully.") : __("Withdrawal failed."),
            'data' => $member->getData(),
        ]);
    }
}
