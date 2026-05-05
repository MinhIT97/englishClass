<?php

namespace Modules\IeltsSet\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class IeltsSetServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'IeltsSet';

    protected string $nameLower = 'ieltset';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
