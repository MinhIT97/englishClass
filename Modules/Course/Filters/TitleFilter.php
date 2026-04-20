<?php

namespace Modules\Course\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class TitleFilter
{
    public function handle(Builder $query, Closure $next)
    {
        if (request()->has('title') && request('title')) {
            $query->where('title', 'like', '%' . request('title') . '%');
        }

        return $next($query);
    }
}
