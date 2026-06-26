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
                @elseif ($outfit)
                    <div class="{{ $dayClass }} viewable" title="{{ $dayTitle }}"
                         role="button" tabindex="0"
                         data-date="{{ $day->format('Y년 n월 j일').' ('.['일','월','화','수','목','금','토'][$day->dayOfWeek].')' }}"
                         data-desc="{{ $outfit->description }}"
                         @if ($outfit->avatar_path) data-avatar="{{ asset('storage/'.$outfit->avatar_path) }}" @endif>
                        <span class="day-num">{{ $day->day }}</span>
                        @if ($outfit->avatar_path)
                            <img class="avatar" src="{{ asset('storage/'.$outfit->avatar_path) }}" alt="{{ $outfit->description }}">
                        @else
                            <span class="pending">…</span>
                        @endif
                    </div>
                @else
                    <div class="{{ $dayClass }}" title="{{ $dayTitle }}">
                        <span class="day-num">{{ $day->day }}</span>
                    </div>
                @endif
            @endif
        @endforeach
    </div>

    @unless ($canEdit)
        <div id="day-modal" class="modal-overlay" hidden>
            <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-date">
                <button type="button" class="modal-close" aria-label="닫기">✕</button>
                <h2 id="modal-date" class="modal-date"></h2>
                <img class="modal-avatar" alt="" hidden>
                <p class="modal-desc"></p>
            </div>
        </div>

        <script>
            (function () {
                const modal = document.getElementById('day-modal');
                if (!modal) return;
                const dateEl = modal.querySelector('.modal-date');
                const avatarEl = modal.querySelector('.modal-avatar');
                const descEl = modal.querySelector('.modal-desc');

                function open(cell) {
                    dateEl.textContent = cell.dataset.date || '';
                    if (cell.dataset.avatar) {
                        avatarEl.src = cell.dataset.avatar;
                        avatarEl.alt = cell.dataset.desc || '';
                        avatarEl.hidden = false;
                    } else {
                        avatarEl.removeAttribute('src');
                        avatarEl.hidden = true;
                    }
                    descEl.textContent = cell.dataset.desc || '아직 도트를 찍는 중이에요…';
                    modal.hidden = false;
                    document.body.style.overflow = 'hidden';
                }
                function close() {
                    modal.hidden = true;
                    document.body.style.overflow = '';
                }

                document.querySelectorAll('.day.viewable').forEach(function (cell) {
                    cell.addEventListener('click', function () { open(cell); });
                    cell.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(cell); }
                    });
                });
                modal.querySelector('.modal-close').addEventListener('click', close);
                modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && !modal.hidden) close();
                });
            })();
        </script>
    @endunless
@endsection
