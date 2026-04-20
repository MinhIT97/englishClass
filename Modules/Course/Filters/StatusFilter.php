<?php

namespace Modules\Course\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class StatusFilter
{
    public function handle(Builder $query, Closure $next)
    {
        if (request()->has('status') && request('status')) {
            $query->where('status', request('status'));
        }

        return $next($query);
    }
}
