<?php

namespace App\Jobs;

use App\Helpers\_918kaya;
use App\Helpers\_918kiss;
use App\Helpers\_AWC;
use App\Helpers\_Evo888;
use App\Helpers\_Playboy;
use App\Helpers\Mega888;
use App\Models\BetLog;
use App\Models\MemberAccount;
use App\Models\Product;
use App\Models\Setting;
use App\Modules\_PlayboyController;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessAWCBetLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $argument;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($argument)
    {
        $this->argument = $argument;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $now = $this->argument ? Carbon::parse($this->argument) : now();
        $date = $now->copy()->subMinutes(30)->format('Y-m-d\TH:i:sP');

        foreach (_AWC::PLATFORM as $platform) {
            $cacheKey = "awc_processing_" . $platform;

            if (Cache::has($cacheKey)) {
                continue;
            }

            $settingKey = $platform . "_last_bet_time";
            $cachedDate = Setting::get($settingKey);
            if ($cachedDate) {
                $cachedDateCarbon = Carbon::parse($cachedDate);
                if (now()->gte($cachedDateCarbon->addDay())) {
                    Setting::updateOrCreate(
                        ['name' => $settingKey],
                        ['value' => null]
                    );
                    $cachedDate = null;
                }
            }

            $date = $cachedDate ? Carbon::parse($cachedDate)->format('Y-m-d\TH:i:sP') : $date;

            ProcessAWCBetLogDetails::dispatch($platform, $date)->delay(20);

            Cache::put($cacheKey, true, now()->addMinutes(5));
        }
    }
}
