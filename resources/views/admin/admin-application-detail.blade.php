@extends('layouts.admin-app')

@section('css')
@vite('resources/css/admin/admin-application-detail.css')
@endsection

@section('content')
<div class="detail__content">
    <div class="detail__header">
        <h1 class="content__header--item">勤怠詳細</h1>
    </div>

    <form class="applied-form"
          action="{{ route('admin.correction.approve', ['id' => $application['id']]) }}"
          method="post">
        @csrf

        <div class="applied-form__content">
            <div class="applied-form__group">
                <label class="applied-form__header">名前</label>
                <div class="applied-form__input-group">
                    <input class="applied-form__input"
                            type="text" value="{{ $user->name }}" readonly>
                </div>
            </div>

            <div class="applied-form__group">
                <label class="applied-form__header">日付</label>
                <div class="applied-form__input-group">
                    <input class="applied-form__input" type="text"
                            value="{{ $application['year'] }}" readonly>
                    <input class="applied-form__input" type="text"
                            value="{{ $application['date'] }}" readonly>
                </div>
            </div>

            <div class="applied-form__group">
                <label class="applied-form__header">出勤・退勤</label>
                <div class="applied-form__input-group">
                    <input class="applied-form__input" type="text"
                            value="{{ $application['clock_in'] }}" readonly>
                    <p class="wavy-line">〜</p>
                    <input class="applied-form__input" type="text"
                            value="{{ $application['clock_out'] }}" readonly>
                </div>
            </div>

            @foreach ($application['breaks'] as $index => $break)
            <div class="applied-form__group">
                <label class="applied-form__header">
                    {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
                </label>
                <div class="applied-form__input-group">
                    <input class="applied-form__input readonly" type="text"
                            value="{{ $break['break_in'] }}" readonly>
                    <p>〜</p>
                    <input class="applied-form__input readonly" type="text"
                            value="{{ $break['break_out'] }}" readonly>
                </div>
            </div>
            @endforeach

            <div class="applied-form__group">
                <label class="applied-form__header">
                    {{ count($application['breaks']) === 0 ? '休憩' : '休憩' . (count($application['breaks']) + 1) }}
                </label>
                <div class="applied-form__input-group">
                    <input class="applied-form__input readonly"
                            type="text" value="" readonly>
                    <p>〜</p>
                    <input class="applied-form__input readonly"
                            type="text" value="" readonly>
                </div>
            </div>

            <div class="applied-form__group">
                <label class="applied-form__header">備考</label>
                <div class="applied-form__input-group">
                    <textarea class="applied-form__textarea"
                               readonly>{{ $application['reason'] }}</textarea>
                </div>
            </div>

            @error('approval')
            <p class="error-message__item">{{ $message }}</p>
            @enderror
        </div>

        <div class="applied-form__button">
            @if ($application['status'] === 'pending')
                <button class="applied-form__button--submit"
                        type="submit">承認</button>
            @else
                <p class="applied-form__item">承認済み</p>
            @endif
        </div>
    </form>
</div>
@endsection
