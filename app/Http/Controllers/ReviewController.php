<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Business $business): View
    {
        abort_unless($business->is_verified, 404);

        return view('reviews.index', [
            'business' => $business,
            'reviews' => $business->reviews()->with('user')->latest()->paginate(10),
        ]);
    }
}
