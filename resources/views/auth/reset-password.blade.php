<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">
    <div class="flex min-h-screen items-center justify-center p-6">
        <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-8 shadow-xl">
            <h1 class="text-2xl font-bold text-slate-900">Reset your password</h1>
            <p class="mt-2 text-sm text-slate-600">Choose a new password for your account.</p>

            @if ($errors->any())
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-600">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">New Password</label>
                    <input type="password" name="password" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-700">Confirm Password</label>
                    <input type="password" name="password_confirmation" required class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>

                <button type="submit" class="w-full rounded-xl bg-amber-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-amber-500">
                    Update Password
                </button>
            </form>
        </div>
    </div>
</body>
</html>
