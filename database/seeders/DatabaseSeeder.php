<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Setting;
use App\Models\Statistic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        ini_set('memory_limit', '4096M');
        Artisan::call("cache:clear");
        $this->call(ProductSeeder::class);
        $this->call(MemberSeeder::class);
    }
}
