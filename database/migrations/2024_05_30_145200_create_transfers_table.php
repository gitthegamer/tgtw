<?php

use App\Models\Transfer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('unique_id')->unique();
            $table->string('uuid')->nullable();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('member_id')->constrained();
            $table->tinyInteger('type');
            $table->decimal('before_balance')->default(0);
            $table->decimal('amount');
            $table->tinyInteger('status')->default(1);
            $table->string('message')->nullable();
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
        Schema::dropIfExists('transfers');
    }
}
