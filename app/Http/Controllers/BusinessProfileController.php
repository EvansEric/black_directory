<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\View\View;

class BusinessProfileController extends Controller
{
    public function __invoke(Business $business): View
    {
        abort_unless($business->is_verified, 404);

        $business->load('user')->loadCount('reviews')->loadAvg('reviews', 'rating');

        return view('businesses.show', ['business' => $business]);
    }
}
