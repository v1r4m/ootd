@extends('layouts.app')

@section('title', $day->format('Y년 n월 j일').' 옷차림')

@section('content')
    <div class="entry">
        <h1>{{ $day->format('Y년 n월 j일') }} <span class="weekday-label">({{ ['일','월','화','수','목','금','토'][$day->dayOfWeek] }})</span></h1>

        @if ($outfit?->avatar_path)
            <div class="entry-avatar">
                <img class="avatar-big" src="{{ asset('storage/'.$outfit->avatar_path) }}" alt="{{ $outfit->description }}">
                @if ($outfit->engine === 'placeholder')
                    <p class="hint">임시 도트예요. <code>.env</code>에 <code>GEMINI_API_KEY</code>를 넣으면 진짜 아바타가 생성돼요!</p>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('outfits.update', ['date' => $day->toDateString()]) }}" id="outfit-form">
            @csrf
            <label for="description">오늘 뭐 입었어?</label>
            <textarea id="description" name="description" rows="4" required maxlength="1000"
                placeholder="예: 오늘은 회색 티셔츠에 하얀색 바지, 검은색 크록스를 신었어!">{{ old('description', $outfit?->description) }}</textarea>
            @error('description')
                <p class="field-error">{{ $message }}</p>
            @enderror

            <div class="entry-actions">
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    {{ $outfit ? '아바타 다시 만들기' : '아바타 만들기' }}
                </button>
                <a class="btn" href="{{ route('calendar', ['handle' => $profile->handle, 'year' => $day->year, 'month' => $day->month]) }}">달력으로</a>
            </div>
        </form>

        @if ($outfit)
            <form method="POST" action="{{ route('outfits.destroy', ['date' => $day->toDateString()]) }}"
                  onsubmit="return confirm('이 날의 기록을 지울까요?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">기록 지우기</button>
            </form>
        @endif
    </div>

    <script>
        document.getElementById('outfit-form').addEventListener('submit', () => {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.textContent = '도트 찍는 중... (10초쯤 걸려요)';
        });
    </script>
@endsection
