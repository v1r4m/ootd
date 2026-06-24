@extends('layouts.app')

@section('title', 'OOTD — 매일의 옷차림을 도트 아바타로')

@section('content')
    <div class="entry">
        <h1>OOTD<span class="logo-dot">▦</span></h1>
        <p>오늘 뭐 입었는지 적으면, 도트 아바타로 옷차림을 기록해주는 옷장 달력이에요.</p>
        <p class="hint">계정을 만들면 나만의 <code>/@핸들</code> 달력이 생기고, 내 옷장은 나만 수정할 수 있어요.</p>

        <div class="entry-actions">
            <a class="btn btn-primary" href="{{ route('register') }}">시작하기</a>
            <a class="btn" href="{{ route('login') }}">로그인</a>
        </div>
    </div>
@endsection
