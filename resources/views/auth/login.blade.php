<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — CryptoBot</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gradient-to-br from-slate-50 to-blue-50 flex items-center justify-center p-4">

<div class="w-full max-w-sm">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <div class="w-14 h-14 bg-blue-600 rounded-2xl flex items-center justify-center text-2xl font-bold text-white mx-auto mb-4 shadow-lg shadow-blue-200">₿</div>
        <h1 class="text-2xl font-bold text-slate-900">CryptoBot</h1>
        <p class="text-sm text-slate-500 mt-1">Admin Login</p>
    </div>

    {{-- Card --}}
    <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/60 border border-slate-100 p-7">

        @if($errors->any())
            <div class="mb-5 flex items-start gap-2.5 p-3.5 rounded-lg bg-red-50 border border-red-100 text-red-600 text-sm">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-900 bg-slate-50
                              placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                       placeholder="admin@cryptobot.com">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                <input id="password" type="password" name="password" required
                       class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-900 bg-slate-50
                              placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                       placeholder="••••••••">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="remember" name="remember"
                       class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <label for="remember" class="text-sm text-slate-600 select-none cursor-pointer">Ingat saya</label>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold
                           py-2.5 rounded-xl text-sm transition shadow-sm shadow-blue-200 mt-2">
                Masuk
            </button>
        </form>
    </div>

    <p class="text-center text-xs text-slate-400 mt-6">
        Hanya admin yang dapat mengakses sistem ini.
    </p>
</div>

</body>
</html>
