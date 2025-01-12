<?php

namespace App\Helpers;

use App\Models\Agent;
use App\Models\AgentTransaction;
use App\Models\Log as ModelsLog;
use App\Models\Member;
use App\Models\OfflineCustomerContactPerson;
use App\Models\OfflineTransaction;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class _Export
{
    public static function generate_member_transaction_slip($data)
    {
        try {
            $transaction = $data;
            if (!$transaction) {
                return null;
            }

            $member = Member::find($transaction->member_id);
            if (!$member) {
                return null;
            }

            $username = $member->username;

            $type = $transaction->type;
            $transactionType = Transaction::TYPE[$transaction->type];
            $description = '';

            if ($type == Transaction::TYPE_DEPOSIT) {
                $description = 'Deposit $' . $transaction->amount . ' has been successful';
            } else if ($type == Transaction::TYPE_WITHDRAWAL) {
                $description = 'Withdrawal $' . $transaction->amount . ' has been successful';
            } else if ($type == Transaction::TYPE_TRANSFER_OUT) {
                if ($transaction->remark) {
                    $pos = strpos($transaction->remark, '(');
                    $trimmedRemark = $pos !== false ? substr($transaction->remark, 0, $pos) : $transaction->remark;
                    $description = $username . " " . lcfirst($trimmedRemark);
                } else {
                    $description = '';
                }
            }


            $currency = $member->currency;
            $amount = $transaction->amount;
            $transactionId = $transaction->unique_id;
            $createdAt = $transaction->created_at;

            $templateHtml = file_get_contents(config('filesystems.s3links.link') . '/public/member_transaction_receipts/template/receipt.blade.php');

            // Modify the content as needed
            $search = ['__transactionType__', '__description__', '__currency__', '__amount__', '__transactionId__', '__createdAt__'];
            $replace = [$transactionType, $description, $currency, $amount, $transactionId, $createdAt];
            $modifiedHtml = str_replace($search, $replace, $templateHtml);

            // Generate PDF from modified HTML
            $pdf = Pdf::loadHTML($modifiedHtml);
            $fileName = $transaction->unique_id .  "_" . time() . "_receipt.pdf";
            $filePath = 'public/member_transaction_receipts/receipt/' . $fileName;
            $pdfContent = $pdf->output();

            // Generate the download URL
            $uploaded = config('filesystems.s3links.link') . '/' . Storage::disk('s3')->put($filePath, $pdfContent);

            if ($uploaded) {
                $downloadUrl = config('filesystems.s3links.link') . '/' . $filePath;
                return $downloadUrl;
            } else {
                return false;
            }
        } catch (Exception $e) {
            ModelsLog::addLog([
                'channel' => ModelsLog::CHANNEL_GENERAL,
                'params' => json_encode('PDF generation error: ' . $e->getMessage() . ' at file: ' . $e->getFile() . ' of line: ' . $e->getLine()),
                'status' => ModelsLog::STATUS_FAILED,
                'message' => 'Failed to generate PDF',
            ]);
            return null;
        }
    }
}
