<?php

use App\Models\Product;
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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->integer('sequence')->nullable();
            $table->string('code');
            $table->string('name');
            $table->tinyInteger('category');
            $table->string('class');
            $table->string('image');
            $table->boolean('status')->default(Product::STATUS_ACTIVE);
            $table->json('disclaimer');
            $table->string('web_download')->nullable();
            $table->string('ios_download')->nullable();
            $table->string('apk_download')->nullable();
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
        Schema::dropIfExists('products');
    }
};
