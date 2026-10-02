<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('Our Mission | Noir Directory')]
class extends Component {};
?>

<div class="flex min-h-screen flex-col bg-white">
    <livewire:navigation />

    <main class="grow">
        <section class="bg-brand-50 px-4 py-10 sm:px-6 sm:py-16 lg:px-8 lg:py-20">
            <div class="mx-auto grid max-w-7xl items-center gap-10 lg:grid-cols-12 lg:gap-14">
                <div class="lg:col-span-7">
                    <span class="mb-5 inline-flex items-center gap-2 text-xs font-bold uppercase text-amber-700">
                        <span class="h-px w-8 bg-amber-600"></span>
                        Why we exist
                    </span>
                    <h1 class="max-w-3xl font-serif text-4xl font-bold leading-tight text-slate-950 sm:text-5xl lg:text-6xl">
                        When Black-owned businesses thrive, <span class="text-amber-700">communities grow stronger.</span>
                    </h1>
                    <p class="mt-6 max-w-2xl text-base leading-relaxed text-slate-600 sm:text-lg">
                        Noir Directory makes it easier to find, choose, and champion Black-owned businesses close to home. Every discovery can keep opportunity moving through the neighborhoods we share.
                    </p>
                    <a href="{{ route('home') }}" class="mt-8 inline-flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800">
                        Explore the directory <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>

                <figure class="relative min-h-72 overflow-hidden rounded-lg bg-amber-100 sm:min-h-96 lg:col-span-5 lg:min-h-[34rem]">
                    <img
                        src="https://images.unsplash.com/photo-1528605248644-14dd04022da1?auto=format&fit=crop&w=1200&q=85"
                        alt="Neighbors sharing a meal around a table"
                        class="absolute inset-0 h-full w-full object-cover"
                        loading="lazy"
                    >
                    <figcaption class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/80 to-transparent px-5 pb-5 pt-16 text-sm font-semibold text-white">
                        Strong communities are built together.
                    </figcaption>
                </figure>
            </div>
        </section>

        <section class="bg-slate-950 px-4 py-12 text-white sm:px-6 sm:py-16 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-8 md:grid-cols-12 md:gap-12">
                <h2 class="font-serif text-3xl font-bold leading-tight sm:text-4xl md:col-span-5">Our mission is bigger than a listing.</h2>
                <div class="md:col-span-7">
                    <p class="text-base leading-relaxed text-slate-300 sm:text-lg">
                        We connect people with the entrepreneurs, makers, and neighborhood institutions shaping everyday life. By making these businesses easier to discover and support, we help turn local spending into lasting relationships, opportunity, and shared prosperity.
                    </p>
                </div>
            </div>
        </section>

        <section class="px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <p class="mb-3 text-xs font-bold uppercase text-amber-700">What we believe</p>
                <div class="grid gap-8 border-t border-slate-200 pt-7 md:grid-cols-3 md:gap-10">
                    <article>
                        <span class="font-serif text-2xl font-bold text-amber-700">01</span>
                        <h3 class="mt-3 text-lg font-bold text-slate-900">Visibility creates possibility.</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Great businesses deserve to be found by the people looking for exactly what they offer.</p>
                    </article>
                    <article>
                        <span class="font-serif text-2xl font-bold text-amber-700">02</span>
                        <h3 class="mt-3 text-lg font-bold text-slate-900">Local support has a ripple effect.</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Choosing local helps strengthen livelihoods, neighborhoods, and the next generation of entrepreneurs.</p>
                    </article>
                    <article>
                        <span class="font-serif text-2xl font-bold text-amber-700">03</span>
                        <h3 class="mt-3 text-lg font-bold text-slate-900">Community is built by showing up.</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">We make it simpler to discover new favorites and keep coming back to the people behind them.</p>
                    </article>
                </div>

                <div class="mt-12 flex flex-col gap-5 border-t border-slate-200 pt-8 sm:flex-row sm:items-center sm:justify-between">
                    <p class="max-w-2xl font-serif text-2xl font-bold leading-snug text-slate-900">Make your next search a way to show support.</p>
                    <button
                        x-data
                        @click="$dispatch('open-auth-modal', { tab: 'register' })"
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-amber-500 px-5 py-3 text-sm font-bold text-slate-950 transition hover:bg-amber-400"
                    >
                        Join the directory <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </div>
        </section>
    </main>

    <x-footer />

    <livewire:auth-modal />
    <livewire:add-business-modal />
    <livewire:subscription-modal />
</div>