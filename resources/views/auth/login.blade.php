@extends('layouts.app')

@section('title', '로그인')

@section('content')
    <div class="entry">
        <h1>로그인</h1>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label for="email">이메일</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror

            <label for="password">비밀번호</label>
            <input id="password" type="password" name="password" required>

            <label class="remember">
                <input type="checkbox" name="remember"> 로그인 유지
            </label>

            <div class="entry-actions">
                <button type="submit" class="btn btn-primary">로그인</button>
                <a class="btn" href="{{ route('register') }}">계정 만들기</a>
            </div>
        </form>
    </div>
@endsection
