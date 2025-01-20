<?php

use App\Models\Currency;
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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->boolean('can_rebate')->default(false);
            $table->boolean('can_commission')->default(false);
            $table->boolean('require_verify')->default(false);
            $table->string('key')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Currency::create([
            'code' => "MYR",
            'name' => "Malaysia Ringgit",
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('currencies');
    }
};
