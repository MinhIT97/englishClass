<?php

namespace Modules\Question\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class TypeFilter
{
    public function handle(Builder $query, Closure $next)
    {
        if (request()->has('type') && request('type')) {
            $query->where('type', request('type'));
        }

        return $next($query);
    }
}
