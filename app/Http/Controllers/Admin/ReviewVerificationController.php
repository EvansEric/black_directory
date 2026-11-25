<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReviewVerificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.review-verifications.index', [
            'verifications' => ReviewVerification::query()->where('status', 'pending')
                ->where('user_id', '!=', $request->user()->id)
                ->whereHas('business', fn ($query) => $query->where('user_id', '!=', $request->user()->id))
                ->select(['id', 'business_id', 'user_id', 'status', 'transaction_reference', 'created_at'])
                ->with(['business', 'user'])->oldest()->orderBy('id')->paginate(15),
        ]);
    }

    public function evidence(Request $request, ReviewVerification $verification): Response
    {
        $this->authorizeDecision($request, $verification);
        abort_unless($verification->status === 'pending' && $verification->evidence !== null, 404);

        $evidence = base64_decode($verification->evidence, true);
        abort_if($evidence === false, 500, 'The evidence could not be decoded.');

        return response($evidence, 200, [
            'Content-Type' => $verification->evidence_type,
            'Content-Disposition' => 'attachment; filename="purchase-evidence"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function decide(Request $request, ReviewVerification $verification): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected']]);

        DB::transaction(function () use ($request, $verification, $data): void {
            $verification = ReviewVerification::query()->lockForUpdate()->findOrFail($verification->id);
            $this->authorizeDecision($request, $verification);
            abort_unless($verification->status === 'pending', 409, 'This request has already been decided.');

            $verification->update([
                'status' => $data['decision'],
                'transaction_hash' => $data['decision'] === 'rejected' ? null : $verification->transaction_hash,
                'transaction_reference' => null,
                'evidence' => null,
                'evidence_type' => null,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
        });

        return redirect()->route('admin.review-verifications.index')
            ->with('status', 'Verification decision saved and evidence deleted.');
    }

    private function authorizeDecision(Request $request, ReviewVerification $verification): void
    {
        abort_if(
            $verification->user_id === $request->user()->id || $verification->business->user_id === $request->user()->id,
            403,
            'Another admin must review this request.',
        );
    }
}
