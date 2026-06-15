<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'OOTD')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/galmuri@latest/dist/galmuri.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site-header">
        <a class="logo" href="{{ url('/') }}">OOTD<span class="logo-dot">▦</span></a>
        <nav>
            <a href="{{ route('outfits.edit', ['date' => now()->toDateString()]) }}">오늘 기록하기</a>
            <a href="{{ route('profile.edit') }}">설정</a>
        </nav>
    </header>

    <main>
        @if (session('status'))
            <div class="flash flash-ok">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="flash flash-error">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="site-footer">매일의 옷차림을 도트 아바타로 ✦</footer>
</body>
</html>
