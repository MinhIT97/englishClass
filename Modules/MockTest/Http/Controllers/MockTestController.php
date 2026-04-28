<?php

namespace Modules\MockTest\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MockTestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('mocktest::index');
    }

    /**
     * Start a full mock test simulation.
     */
    public function startFullTest()
    {
        // For now, redirect to the first skill (Listening) in practice mode
        // but we could extend this to track a "test session" in the future.
        return redirect()->route('student.practice.drill', ['skill' => 'listening'])
            ->with('info', 'Starting Full Mock Test Simulation. Complete all sections for a final band score.');
    }
}
