<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\QueueServiceProvider::class,
    App\Cms\CmsServiceProvider::class,
    App\Dams\DamsServiceProvider::class,
    App\Ems\EmsServiceProvider::class,
];

