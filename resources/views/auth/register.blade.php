@extends('layouts.app')

@section('title', '회원가입')

@section('content')
    <div class="entry">
        <h1>회원가입</h1>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <label for="name">이름</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="50" autofocus>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror

            <label for="email">이메일</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror

            <label for="handle">핸들 (주소에 쓰여요: /@핸들)</label>
            <input id="handle" type="text" name="handle" value="{{ old('handle') }}" required maxlength="30"
                placeholder="예: viram">
            @error('handle') <p class="field-error">{{ $message }}</p> @enderror

            <label for="password">비밀번호 (8자 이상)</label>
            <input id="password" type="password" name="password" required>
            @error('password') <p class="field-error">{{ $message }}</p> @enderror

            <label for="password_confirmation">비밀번호 확인</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>

            <div class="entry-actions">
                <button type="submit" class="btn btn-primary">가입하기</button>
                <a class="btn" href="{{ route('login') }}">이미 계정이 있어요</a>
            </div>
        </form>
    </div>
@endsection
