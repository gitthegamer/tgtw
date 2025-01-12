<?php

namespace TheGamerGroup\WalletTransfer;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use TheGamerGroup\WalletTransfer\Commands\WalletTransferCommand;

class WalletTransferServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('wallet-transfer')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_wallet_transfer_table')
            ->hasCommand(WalletTransferCommand::class);
    }
}
