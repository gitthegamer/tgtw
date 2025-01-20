<?php

namespace App\Http\Controllers\External;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class APIController extends Controller
{
    const KEY = "370d6ac9-f579-4e67-8f1a-c2f5f665c4e3";

    /**
     * Member Info API
     *
     * @bodyParam   token    string  required required|invalid_token (md5(KEY+TIME)) Example: c07c857bed34d08e22ed7adc55e877ed
     * @bodyParam   time    string  required required (TIMESTAMP) Example: 1649689933
     * @bodyParam   username    string  required required|min:4|max:255 (Member Username | Auto register)    Example: demo
     *
     * @response {
     *  "status": true,
     *  "message": "member.success", // {type}.{status}
     *  "data": {
     *   "username" => "test0"
     *   "point" => "100.00"
     *  }
     * }
     * 
     * @response status=200 scenario="ERROR" {
     *  "status": false,
     *  "message": "token.invalid" // {params}.{error}
     *  "data" : null,
     * }
     */
    public function member_info(Request $request)
    {
        $request = $request->json()->all();
        Log::channel("apis")->debug("Member Info API : " . json_encode($request));

        $validator = Validator::make($request, [
            "token" => "required",
            "time" => "required",
            "username" => "required|string|min:4|max:20",
        ], [
            "token.required" => "token.required",
            "time.required" => "time.required",
            "username.required" => "username.required",
            "username.string" => "username.string",
            "username.min" => "username.min",
            "username.max" => "username.max",
        ]);

        if (!$this->verify_token($request)) {
            return response()->json([
                'status' => false,
                'message' => "token.invalid_token",
                'data' => null,
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'data' => null,
            ]);
        }

        $member = Member::select(['username', 'point'])->where('username', $request['username'])->first();
        if (!$member) {
            return response()->json([
                'status' => false,
                'message' => 'member.not_exist',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => "member.success",
            'data' => $member,
        ]);
    }


    /**
     * Member Deduct Point API
     *
     * @bodyParam   token    string  required required|invalid_token (md5(KEY+TIME)) Example: c07c857bed34d08e22ed7adc55e877ed
     * @bodyParam   time    string  required required (TIMESTAMP) Example: 1649689933
     * @bodyParam   username    string  required required|min:4|max:255 (Member Username | Auto register)    Example: demo
     * @bodyParam   amount    numeric  required required|min:1|max:100000 (Transaction Amount)  Example: 100
     * @bodyParam   remark    string  required required    Example: Redeem abc
     *
     * @response {
     *  "status": true,
     *  "message": "deduct.success", // {type}.{status}
     *  "data": {
     *   "username" => "test0"
     *   "point" => "0.00"
     *  }
     * }
     * 
     * @response status=200 scenario="ERROR" {
     *  "status": false,
     *  "message": "token.invalid" // {params}.{error}
     *  "data" : null,
     * }
     */
    public function member_deduct_point(Request $request)
    {
        $request = $request->json()->all();
        Log::channel("apis")->debug("Member Deduct Point API : " . json_encode($request));

        $validator = Validator::make($request, [
            "token" => "required",
            "time" => "required",
            "username" => "required|string|min:4|max:20",
            "amount" => 'required|numeric|min:1|max:100000',
            "remark" => 'required|string',
        ], [
            "token.required" => "token.required",
            "time.required" => "time.required",
            "username.required" => "username.required",
            "username.string" => "username.string",
            "username.min" => "username.min",
            "username.max" => "username.max",
            "amount.required" => "amount.required",
            "amount.numeric" => "amount.numeric",
            "amount.min" => "amount.min",
            "amount.max" => "amount.max",
            "remark.required" => "remark.required",
            "remark.string" => "remark.string",
        ]);

        if (!$this->verify_token($request)) {
            return response()->json([
                'status' => false,
                'message' => "token.invalid_token",
                'data' => null,
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'data' => null,
            ]);
        }

        $member = Member::select(['id', 'username', 'point'])->where('username', $request['username'])->first();
        if (!$member) {
            return response()->json([
                'status' => false,
                'message' => 'member.not_exist',
                'data' => null,
            ]);
        }

        if (($member->point - $request['amount']) < 0) {
            return response()->json([
                'status' => false,
                'message' => 'member.point_not_enough',
                'data' => null,
            ]);
        }

        $member->decrement('point', $request['amount']);
        $member->member_points()->create([
            'member_id' => $member->id,
            'amount' => $request['amount'] * -1,
            'remark' => "Member Deduct Point API : " . $request['remark'],
        ]);

        $member = Member::select(['id', 'username', 'point'])->where('username', $request['username'])->first();
        return response()->json([
            'status' => true,
            'message' => "member.success",
            'data' => $member,
        ]);
    }

    public function verify_token($request)
    {
        if (time() > $request['time'] + 60) {
            Log::channel("apis")->debug("Token Expired");
            return false;
        }

        $token = md5(SELF::KEY . $request['time']);
        if ($token != $request['token']) {
            Log::channel("apis")->debug("Incorrect Hash");
            return false;
        }

        return md5(SELF::KEY . $request['time']) == $request['token'];
    }
}
