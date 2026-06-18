<?php

namespace App\Http\Controllers;

use App\Services\ProgressAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(protected ProgressAnalyticsService $analytics)
    {
    }

    public function show(Request $request): View
    {
        $data = $this->analytics->build($request->user());

        return view('analytics.show', $data);
    }
}