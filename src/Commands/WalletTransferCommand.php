<?php

namespace TheGamerGroup\WalletTransfer\Commands;

use Illuminate\Console\Command;

class WalletTransferCommand extends Command
{
    public $signature = 'wallet-transfer';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
