<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Member::create([
            'master_id' => null,
            'upline_type' => null,
            'upline_id' => null,
            "rank_id" => 1,
            'username' => "supertest1",
            'password' => bcrypt(1),
            'full_name' => "supertestone",
            'email' => "supertest1@gmail.com",
            'phone' => "1",
            'balance' => 1000
        ]);
    }
}
