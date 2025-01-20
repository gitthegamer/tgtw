<?php

use App\Models\Member;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('master_id')->nullable();
            $table->bigInteger('agent_id')->nullable();
            $table->bigInteger('upline_id')->nullable();
            $table->string('upline_type')->nullable();
            $table->bigInteger('linkage_id')->nullable();
            $table->string('code');
            $table->string('currency', 3)->default("SGD");
            $table->foreignId('rank_id')->default(1);
            $table->decimal('point', 16, 2)->default(0);
            $table->integer('reward_point')->default(0);
            $table->tinyInteger('wallet_type')->default(0);
            $table->foreignId('product_id')->nullable()->references('id')->on('products');
            $table->string('username')->unique();
            $table->string('password')->nullable();
            $table->string('token')->nullable();
            $table->decimal('balance')->default(0);
            $table->string('profile_image')->nullable();
            $table->string('nickname')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('phone_verify')->default(false);
            $table->date('birthday')->nullable();
            $table->text('remark')->nullable();
            $table->tinyInteger('register_from')->default(0);
            $table->string('language')->nullable();
            $table->timestamp("last_login_at")->nullable();
            $table->index(['created_at']);
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
        Schema::dropIfExists('members');
    }
}
