<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bet_logs', function (Blueprint $table) {
            $table->id();
            $table->string('bet_id')->unique();
            $table->string('product')->index();
            $table->string('game')->nullable();
            $table->string('category')->index();
            $table->string('username')->references('code')->on('members');
            $table->decimal('stake');
            $table->decimal('valid_stake');
            $table->decimal('payout');
            $table->decimal('winlose');
            $table->decimal('jackpot_win');
            $table->decimal('progressive_share');
            $table->string('payout_status');
            $table->string('bet_status');
            $table->date('account_date')->index();
            $table->date('round_date')->index();
            $table->date('modified_date')->index();
            $table->timestamp('round_at')->nullable()->index();
            $table->timestamp('modified_at')->nullable()->index();
            $table->json('bet_detail');
            $table->boolean('is_settle')->index()->default(false);
            $table->string('key')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bet_logs');
    }
};
