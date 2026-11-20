<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Listing and Subscription Disclosure | Noir Directory')]
class extends Component {};
?>

<div class="flex min-h-screen flex-col bg-white">
    <livewire:navigation />

    <main class="grow">
        <section class="border-b border-amber-900/10 bg-brand-50 px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <p class="mb-3 text-xs font-bold uppercase tracking-wider text-amber-700">Noir Directory</p>
                <h1 class="font-serif text-3xl font-bold text-slate-900 sm:text-4xl">Listing and subscription disclosure</h1>
                <p class="mt-4 text-sm leading-relaxed text-slate-600 sm:text-base">Learn how business listings, verification, and paid plans work.</p>
            </div>
        </section>

        <section class="px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            <div class="mx-auto max-w-3xl space-y-8 text-sm leading-relaxed text-slate-600 sm:text-base">
                <section>
                    <h2 class="mb-2 text-lg font-bold text-slate-900">Our mission</h2>
                    <p>Noir Directory's mission is to help people discover and support Black-owned businesses. Customers and supporters are welcome to use the directory.</p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-bold text-slate-900">Listing review and verification</h2>
                    <p>Business submissions are reviewed before they appear publicly. A listing must be verified before its owner can start a paid plan.</p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-bold text-slate-900">Paid plans and renewal</h2>
                    <p>Paid subscriptions renew on the selected monthly or annual billing cycle until canceled. Plan prices and features are shown on the <a href="{{ route('pricing') }}" class="font-semibold text-amber-700 underline hover:text-amber-600">pricing page</a> and at checkout.</p>
                </section>

                <section>
                    <h2 class="mb-2 text-lg font-bold text-slate-900">Cancellation and verification changes</h2>
                    <p>You can manage or cancel a subscription by selecting the Free plan. When a paid subscription is canceled, the paid plan generally continues through the end of the current billing period.</p>
                    <p class="mt-3">Removing a listing's verification hides the listing but does not automatically cancel an existing subscription.</p>
                </section>
            </div>
        </section>
    </main>

    <x-footer />
    <livewire:auth-modal />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
</div>
