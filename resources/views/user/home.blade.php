@extends('layouts.app')

@section('content')
<div style="max-width: 800px; margin: 80px auto; text-align: center;">
    <h1>勤怠管理システム</h1>

    <p>{{ Auth::user()->name }} さん、ログイン中です。</p>

    <p>勤怠管理画面は現在開発中です。</p>
</div>
@endsection
