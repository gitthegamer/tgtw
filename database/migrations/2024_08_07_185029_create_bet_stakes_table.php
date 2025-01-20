<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBetStakesTable extends Migration
{
    public function up()
    {
        Schema::create('bet_stakes', function (Blueprint $table) {
            $table->id();
            $table->string('bet_id')->unique();
            $table->decimal('stake', 15, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bet_stakes');
    }
}
