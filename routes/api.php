<?php

use App\Helpers\_CMD368;
use App\Helpers\_NextSpin;
use App\Http\Controllers\APIController;
use App\Http\Controllers\Frontend\TransactionController;
use App\Modules\_ACE333Controller;
use App\Modules\_MG88Controller;
use App\Modules\_NextSpinController;
use App\Modules\_CMD368Controller;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::any('/mega888', [_MG88Controller::class, 'callback']);
Route::any('/ace333', [_ACE333Controller::class, 'callback']);
Route::any('/nextspin', [_NextSpinController::class, 'callback']);
Route::any('/cmd368', [_CMD368Controller::class, 'callback']);

Route::post('/global', [APIController::class, 'global']);
Route::post('/login', [APIController::class, 'login']);
Route::post('/register', [APIController::class, 'register']);
Route::post('/launch', [APIController::class, 'launchGame']);
Route::post('/user', [APIController::class, 'user']);
Route::post('/clear', [APIController::class, 'clear']);
Route::post('/add_balance', [APIController::class, 'addBalance']);
Route::post('/get_game_list', [APIController::class, 'getGameList']);
Route::post('/withdraw', [APIController::class, 'withdraw']);

