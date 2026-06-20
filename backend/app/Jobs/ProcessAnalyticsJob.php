<?php

namespace App\Jobs;

use App\Jobs\Analytics\ProcessAnalyticsEventJob;

class ProcessAnalyticsJob extends ProcessAnalyticsEventJob
{
    // Inherit from ProcessAnalyticsEventJob for legacy calls compatibility
}
