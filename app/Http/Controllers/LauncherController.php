<?php

namespace App\Http\Controllers;

use App\Models\MemberAccount;
use App\Models\Product;
use Illuminate\Http\Request;

class LauncherController extends Controller
{
    public function playtech($username, $password)
    {
        $member_account = MemberAccount::where('username', $username)->where('password', $password)->firstOrFail();

        if ($member_account->product->code != "PT") {
            abort(404);
        }

        $file = fopen(storage_path("games/playtech.csv"), "r");
        $gamelists = [];

        try {
            while (!feof($file)) {
                $game = fgetcsv($file);
                if ($game) {
                    if ($member_account->product->category == Product::CATEGORY_SLOTS && $game[0] != "Live Games") {
                        $gamelists[] = $game;
                    }

                    if ($member_account->product->category == Product::CATEGORY_LIVE && $game[0] == "Live Games") {
                        $gamelists[] = $game;
                    }
                }
            }
        } catch (\Throwable $e) {
            
        }

        return view('launcher.playtech', [
            'member_account' => $member_account,
            'gamelists' => $gamelists,
        ]);
    }
}
