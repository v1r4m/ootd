@extends('layouts.app')

@section('title', '설정')

@section('content')
    <div class="entry">
        <h1>설정</h1>

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf

            <label for="handle">핸들 (주소에 쓰여요: /@핸들)</label>
            <input id="handle" type="text" name="handle" value="{{ old('handle', $profile->handle) }}" required maxlength="30">
            @error('handle') <p class="field-error">{{ $message }}</p> @enderror

            <label for="display_name">이름</label>
            <input id="display_name" type="text" name="display_name" value="{{ old('display_name', $profile->display_name) }}" required maxlength="50">
            @error('display_name') <p class="field-error">{{ $message }}</p> @enderror

            <label for="base_look">내 아바타 기본 외형 (머리 모양/색, 눈 색 등 — 매일 그대로 유지돼요)</label>
            <textarea id="base_look" name="base_look" rows="3" maxlength="500"
                placeholder="예: 검은색 짧은 단발머리, 갈색 눈">{{ old('base_look', $profile->base_look) }}</textarea>
            @error('base_look') <p class="field-error">{{ $message }}</p> @enderror

            <div class="entry-actions">
                <button type="submit" class="btn btn-primary">저장</button>
                <a class="btn" href="{{ route('calendar', ['handle' => $profile->handle]) }}">달력으로</a>
            </div>
        </form>
    </div>
@endsection
