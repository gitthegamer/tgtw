<?php

namespace App\Modules;

use App\Jobs\ProcessTransaction;
use App\Models\Gateway;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Log;

class _Miipay
{
    const DEPOSIT_URL = "https://pay.miiipay.com/epayment/entry.aspx";
    const SERIAL_KEY = "58573ff2-4715-44f5-b2fa-25ec085f916d";
    const BO_URL = 'merchant.miipay.com';
    const USERNAME = "WM001-31";
    const PASSWORD = "Slot4u@2023";

    public static function start(Gateway $gateway)
    {
        $MerchantCode = "WM001-31";
        $RefNo = $gateway->transaction->id;
        $Amount = number_format($gateway->transaction->amount, 2, '.', '');
        $Username = $gateway->transaction->member->username;
        $UserEmail = $gateway->transaction->member->id . "@gmail.com";
        $UserContact = $gateway->transaction->member->id;
        $Currency = $gateway->currency;
        $Signature = hash('sha256', SELF::SERIAL_KEY . $MerchantCode . $RefNo . str_replace(".", "", $Amount), true);

        $params = [
            'MerchantCode' => $MerchantCode,
            'RefNo' => $RefNo,
            'Amount' => $Amount,
            'Username' => $Username,
            'UserEmail' => $UserEmail,
            'UserContact' => $UserContact,
            'Currency' => $Currency,
            'Remark' => "",
            'Signature' => $Signature,
            'ResponseURL' => env("APP_URL") . "?success=" . __("Deposit has submitted. Please wait for our verification.") . "&token=" . $gateway->transaction->member->token,
            'BackendURL' => route('transaction.miipay.callback'),
        ];
        echo "<form id='miipay' method='post' action='" . SELF::DEPOSIT_URL . "'>";
        foreach ($params as $key => $value) {
            echo "<input type='hidden' name='" . $key . "' value='" . $value . "' />";
        }
        echo "</form>";
        echo "<script type='text/javascript'>";
        echo "document.getElementById('miipay').submit();";
        echo "</script>";
        echo "We are redirect you to the payment page... Please wait..";
        return true;
    }

    public static function callback($request)
    {
        try {
            $transaction = Transaction::findOrFail($request->RefNo);
            if ($transaction->status != Transaction::STATUS_PENDING) {
                exit();
            }

            $MerchantCode = "WM001-31";
            $Signature = hash('sha256', SELF::SERIAL_KEY . $MerchantCode . $request->RefNo . str_replace(".", "", $request->Amount) . $request->Status, true);

            if ($Signature != $request->Signature) {
                exit();
            }
            $gateway = $transaction->gateway;
            $gateway->amount = $request->Amount;
            $gateway->trxno = $request->TransID;
            $gateway->status = SELF::status($request->Status);
            $gateway->message = $request->ErrDesc;
            $gateway->fee = 0;
            $gateway->save();
            if ($gateway->status == Gateway::STATUS_IN_PROGRESS) {
                exit();
            }
            ProcessTransaction::dispatch($transaction, $gateway->status === Gateway::STATUS_SUCCESS)->onQueue('transactions');
            $transaction->remark = $request->ErrDesc;
            $transaction->action_by = "miipay";
            $transaction->action_at = date('Y-m-d H:i:s');
            $transaction->save();
        } catch (Exception $e) {
        }
        echo "_RECEIVE_";
    }

    public static function verify(Transaction $transaction)
    {
        // Dont have verify api
        return false;
    }

    public static function status($status)
    {
        switch ($status) {
            case 1:
                return Gateway::STATUS_SUCCESS;
            case 0:
                return Gateway::STATUS_FAIL;
            default:
                return Gateway::STATUS_IN_PROGRESS;
        }
    }
}
