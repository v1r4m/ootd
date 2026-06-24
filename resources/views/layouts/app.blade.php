<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'OOTD')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/galmuri@latest/dist/galmuri.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .nav-logout { display: inline; margin: 0; }
        .link-button {
            background: none; border: none; padding: 0; margin: 0;
            font: inherit; color: inherit; cursor: pointer; text-decoration: underline;
        }
        .remember { display: flex; align-items: center; gap: .4em; font-size: .9em; }
        .remember input { width: auto; }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="logo" href="{{ url('/') }}">OOTD<span class="logo-dot">▦</span></a>
        <nav>
            @auth
                <a href="{{ route('outfits.edit', ['date' => now()->toDateString()]) }}">오늘 기록하기</a>
                <a href="{{ route('profile.edit') }}">설정</a>
                <form method="POST" action="{{ route('logout') }}" class="nav-logout">
                    @csrf
                    <button type="submit" class="link-button">로그아웃</button>
                </form>
            @else
                <a href="{{ route('login') }}">로그인</a>
                <a href="{{ route('register') }}">회원가입</a>
            @endauth
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
