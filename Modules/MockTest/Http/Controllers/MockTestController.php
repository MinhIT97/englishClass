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
}
