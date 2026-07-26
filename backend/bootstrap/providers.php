<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\QueueServiceProvider::class,
    App\Ems\EmsServiceProvider::class,
];

