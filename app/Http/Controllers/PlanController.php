<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    /**
     * Public pricing page (HU-017).
     */
    public function index(Request $request): View
    {
        return view('plans.index', [
            'plans' => Plan::available()->get(),
            'currentSubscription' => $request->user()?->currentSubscription(),
        ]);
    }
}
