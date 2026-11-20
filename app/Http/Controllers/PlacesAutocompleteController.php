<?php

namespace App\Http\Controllers;

use App\Services\GooglePlaces;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlacesAutocompleteController extends Controller
{
    public function index(Request $request, GooglePlaces $places): JsonResponse
    {
        $validated = $request->validate([
            'input' => ['required', 'string', 'min:2', 'max:200'],
            'session_token' => ['nullable', 'uuid'],
        ]);

        return response()->json([
            'suggestions' => $places->autocomplete($validated['input'], $validated['session_token'] ?? null),
        ]);
    }

    public function show(string $place, Request $request, GooglePlaces $places): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => ['nullable', 'uuid'],
        ]);

        abort_unless(preg_match('/^[A-Za-z0-9_-]{3,200}$/', $place) === 1, 404);

        return response()->json($places->details($place, $validated['session_token'] ?? null));
    }
}
