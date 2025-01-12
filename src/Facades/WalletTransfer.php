<?php

namespace TheGamerGroup\WalletTransfer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \TheGamerGroup\WalletTransfer\WalletTransfer
 */
class WalletTransfer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \TheGamerGroup\WalletTransfer\WalletTransfer::class;
    }
}
