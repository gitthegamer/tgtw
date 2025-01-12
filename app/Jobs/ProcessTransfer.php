<?php

namespace App\Jobs;

use App\Models\Transfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTransfer implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $transfer;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Transfer $transfer)
    {
        $this->transfer = Transfer::find($transfer->id);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->transfer->status == Transfer::STATUS_IN_PROGRESS) {
            if (!$this->process()) {
                ProcessTransfer::dispatch($this->transfer)->delay(now()->addMinute());
            }
        }
    }

    public function process()
    {
        $response = $this->transfer->product->checkTransaction($this->transfer);

        if ($response == false) {
            $this->transfer->update(['status' => Transfer::STATUS_IN_PROGRESS, 'message' => "Connection Error"]);
            return false;
        }

        if ($response['status'] == Transfer::STATUS_IGNORE) {
            $this->transfer->update(['status' => Transfer::STATUS_IGNORE, 'message' => $response['remark']]);
            return false;
        }

        if ($response['status'] == Transfer::STATUS_IN_PROGRESS) {
            $this->transfer->update(['status' => Transfer::STATUS_IN_PROGRESS, 'message' => $response['remark']]);
            return false;
        }

        if ($response['status'] == Transfer::STATUS_FAIL) {
            if ($this->transfer->type == Transfer::TYPE_DEPOSIT) {
                $this->transfer->member->increment('balance', $this->transfer->amount);
            }

            $this->transfer->update(['status' => Transfer::STATUS_FAIL, 'message' => $response['remark']]);
            return false;
        }

        if ($response['status'] == Transfer::STATUS_SUCCESS) {
            if ($this->transfer->type == Transfer::TYPE_WITHDRAWAL) {
                $this->transfer->member->increment('balance', $this->transfer->amount);
            }
            $this->transfer->update(['status' => Transfer::STATUS_SUCCESS, 'message' => $response['remark']]);
            return false;
        }

        Log::debug("Unknown Process Transfer Error : " . json_encode($response));

        return false;
    }
}
