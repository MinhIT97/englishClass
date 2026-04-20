<?php

namespace Modules\Question\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class TopicFilter
{
    public function handle(Builder $query, Closure $next)
    {
        if (request()->has('topic') && request('topic')) {
            $query->where('topic', request('topic'));
        }

        return $next($query);
    }
}
