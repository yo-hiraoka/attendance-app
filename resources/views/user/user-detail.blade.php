@extends('layouts.app')

@section('css')
    @vite('resources/css/user/user-detail.css')
@endsection

@section('content')
<div class="detail__content">
    <div class="detail__header">
        <h1 class="content__header--item">勤怠詳細</h1>
    </div>

    <div class="form">
        <div class="form__content">
            <div class="form__group">
                <label class="form__header">名前</label>
                <div class="form__input-group">
                    <input class="form__input form__input--name readonly"
                            type="text" value="{{ $user->name }}" readonly>
                </div>
            </div>

            <div class="form__group">
                <label class="form__header">日付</label>
                <div class="form__input-group">
                    <input class="form__input form__input--date readonly"
                            type="text" value="{{ $data['year'] }}" readonly>
                    <input class="form__input form__input--date readonly"
                            type="text" value="{{ $data['date'] }}" readonly>
                </div>
            </div>

            <div class="form__group">
                <label class="form__header">出勤・退勤</label>
                <div class="form__input-group">
                    <input class="form__input readonly"
                            type="text" value="{{ $data['clock_in'] }}" readonly>
                    <p>〜</p>
                    <input class="form__input readonly"
                            type="text" value="{{ $data['clock_out'] }}" readonly>
                </div>
            </div>

            @foreach($data['breaks'] as $index => $break)
                <div class="form__group">
                    <label class="form__header">
                        {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
                    </label>
                    <div class="form__input-group">
                        <input class="form__input readonly"
                                type="text" value="{{ $break['break_in'] }}" readonly>
                        <p>〜</p>
                        <input class="form__input readonly"
                                type="text" value="{{ $break['break_out'] }}" readonly>
                    </div>
                </div>
            @endforeach

            <div class="form__group">
                <label class="form__header">備考</label>
                <div class="form__input-group">
                    <input class="form__textarea readonly"
                            type="text" value="" readonly>
                </div>
            </div>
        </div>

        <div class="form__button">
            <p class="readonly-message">修正申請機能は準備中です</p>
        </div>
    </div>
</div>
@endsection
