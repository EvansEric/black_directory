<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BusinessVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'verified', 'all'])],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $validated['status'] ?? 'pending';
        $search = trim($validated['search'] ?? '');

        $businesses = Business::query()
            ->with('user')
            ->when($status !== 'all', fn ($query) => $query->where('is_verified', $status === 'verified'))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%'));
            }))
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.businesses.index', compact('businesses', 'status', 'search'));
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'is_verified' => ['required', 'boolean'],
        ]);

        $verified = (bool) $validated['is_verified'];
        $business->is_verified = $verified;
        $business->save();

        return redirect()->route('admin.businesses.index', ['status' => $verified ? 'pending' : 'verified'])
            ->with('status', $verified ? 'Business verified and visible in the directory.' : 'Verification removed. The business is no longer publicly visible.');
    }
}
