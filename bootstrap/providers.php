<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ProviderServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    ProviderServiceProvider::class,
    RepositoryServiceProvider::class,
];
