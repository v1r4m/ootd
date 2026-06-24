@extends('layouts.app')

@section('title', $first->format('Y년 n월').' · @'.$profile->handle)

@section('content')
    @php $canEdit = auth()->check() && auth()->id() === $profile->user_id; @endphp
    <div class="calendar-head">
        <a class="month-nav" href="{{ route('calendar', ['handle' => $profile->handle, 'year' => $prev->year, 'month' => $prev->month]) }}">◀</a>
        <div class="month-title">
            <h1>{{ $first->format('Y년 n월') }}</h1>
            <p class="handle">{{ '@'.$profile->handle }} — {{ $profile->display_name }}의 옷장</p>
        </div>
        <a class="month-nav" href="{{ route('calendar', ['handle' => $profile->handle, 'year' => $next->year, 'month' => $next->month]) }}">▶</a>
    </div>

    <div class="calendar">
        @foreach (['일', '월', '화', '수', '목', '금', '토'] as $i => $w)
            <div class="weekday {{ $i === 0 ? 'sun' : ($i === 6 ? 'sat' : '') }}">{{ $w }}</div>
        @endforeach

        @foreach ($days as $day)
            @if (is_null($day))
                <div class="day empty"></div>
            @else
                @php $outfit = $outfits->get($day->toDateString()); @endphp
                @php
                    $dayClass = 'day '.($day->isSameDay($today) ? 'today ' : '').($outfit ? 'filled' : '');
                    $dayTitle = $outfit?->description ?? $day->format('n월 j일');
                @endphp
                @if ($canEdit)
                    <a class="{{ $dayClass }}"
                       href="{{ route('outfits.edit', ['date' => $day->toDateString()]) }}"
                       title="{{ $dayTitle }}">
                        <span class="day-num">{{ $day->day }}</span>
                        @if ($outfit?->avatar_path)
                            <img class="avatar" src="{{ asset('storage/'.$outfit->avatar_path) }}" alt="{{ $outfit->description }}">
                        @elseif ($outfit)
                            <span class="pending">…</span>
                        @else
                            <span class="plus">+</span>
                        @endif
                    </a>
                @else
                    <div class="{{ $dayClass }}" title="{{ $dayTitle }}">
                        <span class="day-num">{{ $day->day }}</span>
                        @if ($outfit?->avatar_path)
                            <img class="avatar" src="{{ asset('storage/'.$outfit->avatar_path) }}" alt="{{ $outfit->description }}">
                        @elseif ($outfit)
                            <span class="pending">…</span>
                        @endif
                    </div>
                @endif
            @endif
        @endforeach
    </div>
@endsection
