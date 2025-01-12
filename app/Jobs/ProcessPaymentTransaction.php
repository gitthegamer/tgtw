<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPaymentTransaction implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $transaction;
    public $payment;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Transaction $transaction, Payment $payment)
    {
        $this->transaction = $transaction;
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        PaymentTransaction::firstOrCreate(
            ['remark' => "Transaction #" . $this->transaction->id],
            [
                'payment_id' => $this->payment->id,
                'date' => now()->format('Y-m-d'),
                'type' => PaymentTransaction::TYPE_WITHDRAWAL,
                'amount' => $this->transaction->amount * -1,
            ]
        );

        return 0;
    }

}
