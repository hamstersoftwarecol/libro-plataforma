<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class PlanController extends Controller
{
    /**
     * Plans were replaced by single-book lifetime purchases; old links go to the payment form.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('checkout.create', status: 301);
    }
}
