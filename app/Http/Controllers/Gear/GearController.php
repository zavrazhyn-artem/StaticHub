<?php

namespace App\Http\Controllers\Gear;

use App\Http\Controllers\Controller;
use App\Models\StaticGroup;
use Illuminate\View\View;

class GearController extends Controller
{
    public function index(StaticGroup $static): View
    {
        return view('gear.index', [
            'static' => $static,
        ]);
    }
}
