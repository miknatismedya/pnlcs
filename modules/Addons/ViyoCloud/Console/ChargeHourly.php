<?php

namespace Modules\Addons\ViyoCloud\Console;

use Illuminate\Console\Command;
use Modules\Addons\ViyoCloud\Support\HourlyBilling;

class ChargeHourly extends Command
{
    protected $signature = 'viyo:hourly-charge';

    protected $description = 'Charge hourly cloud servers from client balances; stop, resume or end them as the balance allows';

    public function handle(HourlyBilling $billing): int
    {
        $totals = $billing->chargeAll();
        $this->info(sprintf('charged %d hour(s), suspended %d, resumed %d, terminated %d', ...array_values($totals)));

        return self::SUCCESS;
    }
}
