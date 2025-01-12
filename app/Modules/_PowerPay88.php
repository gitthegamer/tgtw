<?php

namespace App\Modules;

use App\Jobs\ProcessTransaction;
use App\Models\Gateway;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Log;

class _PowerPay88
{
    const MERCHANT = "PA049";
    const SECURITY_CODE = "jBr8Q4qrPKMjXeNOTRJq";
    const DEPOSIT_SUBMISSION = "https://api.securepaymentapi.com/MerchantTransfer";
    const IP = "111.111.111.111";
    const Success = "000", Failed = "001", Approved = "006", Rejected = "007", Canceled = "008", Pending = "009";

    public static function start(Gateway $gateway)
    {
        $reference = $gateway->transaction->id;
        $customer = $gateway->transaction->member_id;
        $security_code = SELF::SECURITY_CODE;
        $currency = $gateway->currency;
        $datetime_hashed = $gateway->transaction->created_at->format('YmdHis');
        $datetime = $gateway->transaction->created_at->format('Y-m-d h:i:sA');
        $amount = number_format($gateway->transaction->amount, 2, '.', '');
        $client_ip = SELF::IP;
        $key = strtoupper(md5((SELF::MERCHANT .
            $reference .
            $customer .
            $amount .
            $currency .
            $datetime_hashed .
            $security_code .
            $client_ip
        )));

    
    

        echo "<form id='powerpay88' method='post' action='" . SELF::DEPOSIT_SUBMISSION . "'>";
        echo '<input type="hidden" name="Merchant" value="' . SELF::MERCHANT . '" />';
        echo '<input type="hidden" name="Currency" value="' . $currency . '" />';
        echo '<input type="hidden" name="Customer" value="' . $customer . '" />';
        echo '<input type="hidden" name="Reference" value="' . $reference . '" />';
        echo '<input type="hidden" name="Key" value="' . $key . '" />';
        echo '<input type="hidden" name="Amount" value="' . $amount  . '" />';
        echo '<input type="hidden" name="Note" value="" />';
        echo '<input type="hidden" name="Datetime" value="' . $datetime . '" />';
        echo '<input type="hidden" name="FrontURI" value="' . env("APP_URL") . "?success=" . __("Deposit has submitted. Please wait for our verification.") . "&token=" . $gateway->transaction->member->token . '"/>';
        echo ' <input type="hidden" name="BackURI" value="' . route('transaction.powerpay88.callback') . '" /> ';
        echo '<input type="hidden"name="Language" value="en‐us" />';
        echo '<input type="hidden" name="Bank" value="' . $gateway->transaction->payment->identify . '" />';
        echo '<input type="hidden" name="ClientIP" value="' . $client_ip . '" /> </form>';
        echo "</form>";
        echo "<script type='text/javascript'>";
        echo "document.getElementById('powerpay88').submit();";
        echo "</script>";
        echo "We are redirect you to the payment page... Please wait..";
        return true;
    }

    public static function callback($request)
    {
        try {
            $transaction = Transaction::findOrFail($request->Reference);
            if ($transaction->status != Transaction::STATUS_PENDING) {
                exit();
            }
            if ($request->Status == SELF::Pending) {
                exit();
            }

            $gateway = $transaction->gateway;
            $gateway->amount = $request->Amount;
            $gateway->trxno = $request->ID;
            $gateway->status = SELF::status($request->Status);
            $gateway->message = $request->Status;
            $gateway->fee = 0;
            $gateway->save();
            if ($gateway->status == Gateway::STATUS_IN_PROGRESS) {
                exit();
            }
            ProcessTransaction::dispatch($transaction, $gateway->status === Gateway::STATUS_SUCCESS)->onQueue('transactions');
            $transaction->remark = $request->remark;
            $transaction->action_by = "PowerPay88";
            $transaction->action_at = date('Y-m-d H:i:s');
            $transaction->save();
        } catch (Exception $e) {
        }
    }

    public static function verify(Transaction $transaction)
    {
        // Dont have verify api
        return false;
    }

    public static function status($status)
    {
        switch ($status) {
            case SELF::Success:
                return Gateway::STATUS_SUCCESS;
            case SELF::Failed:
                return Gateway::STATUS_FAIL;
            case SELF::Approved:
                return Gateway::STATUS_SUCCESS;
            case SELF::Rejected:
                return Gateway::STATUS_FAIL;
            case SELF::Canceled:
                return Gateway::STATUS_FAIL;
            case SELF::Pending:
                return Gateway::STATUS_IN_PROGRESS;
            default:
                return Gateway::STATUS_IN_PROGRESS;
        }
    }
}
