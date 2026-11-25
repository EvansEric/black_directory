<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ReviewVerification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function create(Request $request, Business $business): View
    {
        $this->authorizeCustomer($request, $business);

        return view('reviews.create', [
            'business' => $business,
            'verifications' => $business->reviewVerifications()
                ->where('user_id', $request->user()->id)
                ->select(['id', 'business_id', 'user_id', 'status', 'created_at'])
                ->with('review')
                ->latest()->orderByDesc('id')->paginate(10),
        ]);
    }

    public function requestVerification(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeCustomer($request, $business);
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verify your email address before submitting evidence.');

        $data = $request->validate([
            'transaction_reference' => ['required', 'string', 'max:120'],
            'evidence' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:5120'],
        ]);

        $reference = trim($data['transaction_reference']);
        if ($reference === '') {
            throw ValidationException::withMessages(['transaction_reference' => 'Enter a receipt or booking reference.']);
        }

        $hash = hash_hmac('sha256', mb_strtolower($reference), config('app.key'));
        $file = $request->file('evidence');

        try {
            $business->reviewVerifications()->create([
                'user_id' => $request->user()->id,
                'transaction_hash' => $hash,
                'transaction_reference' => $reference,
                'evidence' => base64_encode($file->getContent()),
                'evidence_type' => $file->getMimeType(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            if (! $business->reviewVerifications()->where('transaction_hash', $hash)->exists()) {
                throw $exception;
            }

            throw ValidationException::withMessages(['transaction_reference' => 'This reference has already been submitted for this business.']);
        }

        return redirect()->route('reviews.create', $business)
            ->with('status', 'Evidence submitted for admin review. Return here to check its status.');
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $this->authorizeCustomer($request, $business);
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verify your email address before submitting a review.');

        $data = $request->validate([
            'verification_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $business, $data): void {
            $verification = ReviewVerification::query()->lockForUpdate()
                ->whereKey($data['verification_id'])
                ->where('business_id', $business->id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            if ($verification->status !== 'approved' || $verification->review()->exists()) {
                throw ValidationException::withMessages(['verification_id' => 'This purchase is not available for a review.']);
            }

            $business->reviews()->create([
                'user_id' => $request->user()->id,
                'review_verification_id' => $verification->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);
            $verification->update(['status' => 'consumed']);
        });

        return redirect()->route('reviews.create', $business)
            ->with('status', 'Your verified review has been published.');
    }

    private function authorizeCustomer(Request $request, Business $business): void
    {
        abort_unless($business->is_verified, 404);
        abort_if($business->user_id === $request->user()->id, 403, 'You cannot review your own business.');
    }
}
