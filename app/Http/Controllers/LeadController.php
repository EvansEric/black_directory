<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    /**
     * Store a lead for a business listing.
     */
    public function store(Request $request, Business $business): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $lead = $business->leads()->create($validated);

        return response()->json([
            'message' => 'Thank you! Your message has been sent to the business owner.',
            'lead' => $lead,
        ], 201);
    }
}
