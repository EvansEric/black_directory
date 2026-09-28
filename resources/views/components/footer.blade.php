<footer class="mt-auto border-t border-amber-900/10 bg-brand-100 py-10 text-slate-600 sm:py-12">
    <div class="mx-auto mb-8 grid max-w-7xl gap-8 px-4 sm:px-6 md:grid-cols-4 lg:px-8">
        <div class="md:col-span-2">
            <div class="mb-4 flex items-center space-x-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 font-serif text-lg font-bold text-white">N</div>
                <span class="font-serif text-xl font-bold tracking-tight text-slate-900">Noir Directory</span>
            </div>
            <p class="max-w-sm text-xs leading-relaxed text-slate-400">
                Connecting communities with local Black-owned businesses. Empowering economic growth, preserving culture, and promoting sustainable local support.
            </p>
        </div>
        <div>
            <h4 class="mb-3 text-xs font-bold uppercase tracking-wider text-amber-400">Community</h4>
            <ul class="space-y-2 text-xs">
                <li>
                    <button
                        x-data="{ isAuthed: @js(auth()->check()) }"
                        @click="isAuthed ? $dispatch('open-add-modal') : $dispatch('open-auth-modal')"
                        class="transition hover:text-slate-950"
                    >
                        Add a Business
                    </button>
                </li>
                <li>
                    <a href="{{ route('pricing') }}" class="transition hover:text-slate-950">
                        Pricing & Listing Plans
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <div class="mx-auto border-t border-slate-800 px-4 pt-6 text-center text-xs sm:px-6 md:text-left lg:px-8">
        <p class="mx-auto max-w-7xl">&copy; {{ date('Y') }} Noir Business Directory.</p>
    </div>
</footer>
