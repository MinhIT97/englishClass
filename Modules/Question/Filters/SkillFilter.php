<?php

namespace Modules\Question\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class SkillFilter
{
    public function handle(Builder $query, Closure $next)
    {
        if (request()->has('skill') && request('skill')) {
            $query->where('skill', request('skill'));
        }

        return $next($query);
    }
}
