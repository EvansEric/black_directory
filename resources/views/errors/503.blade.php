<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="60">
    <title>We’ll Be Right Back | Noir Directory</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="icon" href="{{ asset('images/favicon.ico') }}" sizes="32x32">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full overflow-x-hidden bg-brand-50 font-sans text-slate-800">
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-12 sm:px-6">
        <div class="pointer-events-none absolute -left-28 -top-28 h-80 w-80 rounded-full bg-amber-200/50 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-28 h-96 w-96 rounded-full bg-emerald-100/70 blur-3xl"></div>

        <section class="relative w-full max-w-2xl overflow-hidden rounded-3xl border border-amber-900/10 bg-white p-7 text-center shadow-2xl sm:p-12">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-500 font-serif text-4xl font-black text-slate-950 shadow-lg shadow-amber-500/20">
                N
            </div>

            <p class="mt-6 text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Noir Directory</p>
            <h1 class="mt-3 font-serif text-4xl font-bold text-slate-900 sm:text-5xl">We’ll be right back.</h1>
            <p class="mx-auto mt-5 max-w-xl text-sm leading-relaxed text-slate-600 sm:text-base">
                We’re making a few improvements to help you discover and support Black-owned businesses.
                The directory will be available again shortly.
            </p>

            <div class="mx-auto mt-8 flex max-w-md items-center justify-center gap-3 rounded-2xl bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-900">
                <i class="fa-solid fa-screwdriver-wrench text-amber-600" aria-hidden="true"></i>
                <span>Scheduled maintenance is in progress</span>
            </div>

            <button
                type="button"
                onclick="window.location.reload()"
                class="mt-8 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-slate-800"
            >
                <i class="fa-solid fa-rotate-right text-xs" aria-hidden="true"></i>
                Try again
            </button>

            <p class="mt-8 text-xs leading-relaxed text-slate-400">
                Thank you for supporting local Black-owned excellence.
            </p>
        </section>
    </main>
</body>
</html>
