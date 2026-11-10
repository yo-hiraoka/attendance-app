@extends('layouts.app')

@section('css')
    @vite('resources/css/user/user-detail.css')
@endsection

@section('content')
<div class="detail__content">
    <div class="detail__header">
        <h1 class="content__header--item">勤怠詳細</h1>
    </div>

    <form class="form"
            action="{{ route('attendance.correction.store', ['id' => $data['id']]) }}"
            method="post">
        @csrf

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
                    <input class="form__input"
                            type="text"
                            name="new_clock_in"
                            value="{{ old('new_clock_in', $data['clock_in']) }}"
                            @readonly($data['application'] !== null)>
                    <p>〜</p>
                    <input class="form__input"
                            type="text"
                            name="new_clock_out"
                            value="{{ old('new_clock_out', $data['clock_out']) }}"
                            @readonly($data['application'] !== null)>
                </div>
            </div>

            <div class="error-message">
                <div></div>
                <div class="error-message__item">
                    @error('new_clock_in') <p>{{ $message }}</p> @enderror
                    @error('new_clock_out') <p>{{ $message }}</p> @enderror
                </div>
            </div>

            @foreach($data['breaks'] as $index => $break)
                <div class="form__group">
                    <label class="form__header">
                        {{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}
                    </label>
                    <div class="form__input-group">
                        <input class="form__input"
                                type="text"
                                name="new_break_in[{{ $index }}]"
                                value="{{ old('new_break_in.' . $index, $break['break_in']) }}"
                                @readonly($data['application'] !== null)>
                        <p>〜</p>
                        <input class="form__input"
                                type="text"
                                name="new_break_out[{{ $index }}]"
                                value="{{ old('new_break_out.' . $index, $break['break_out']) }}"
                                @readonly($data['application'] !== null)>
                    </div>
                </div>

                <div class="error-message">
                    <div></div>
                    <div class="error-message__item">
                        @error('new_break_in.' . $index)
                            <p>{{ $message }}</p>
                        @enderror
                        @error('new_break_out.' . $index)
                            <p>{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endforeach

            @if($data['application'] === null)
                @php $newBreakIndex = count($data['breaks']); @endphp

                <div class="form__group">
                    <label class="form__header">
                        {{ $newBreakIndex === 0 ? '休憩' : '休憩' . ($newBreakIndex + 1) }}
                    </label>
                    <div class="form__input-group">
                        <input class="form__input"
                                type="text"
                                name="new_break_in[{{ $newBreakIndex }}]"
                                value="{{ old('new_break_in.' . $newBreakIndex) }}">
                        <p>〜</p>
                        <input class="form__input"
                                type="text"
                                name="new_break_out[{{ $newBreakIndex }}]"
                                value="{{ old('new_break_out.' . $newBreakIndex) }}">
                    </div>
                </div>

                <div class="error-message">
                    <div></div>
                    <div class="error-message__item">
                        @error('new_break_in.' . $newBreakIndex)
                            <p>{{ $message }}</p>
                        @enderror
                        @error('new_break_out.' . $newBreakIndex)
                            <p>{{ $message }}</p>
                        @enderror
                        @error('new_break_in')
                            <p>{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            <div class="form__group">
                <label class="form__header">備考</label>
                <div class="form__input-group">
                    <input class="form__textarea"
                            type="text"
                            name="comment"
                            value="{{ old('comment', $data['comment']) }}"
                            @readonly($data['application'] !== null)>
                </div>
            </div>

            <div class="error-message">
                <div></div>
                <div class="error-message__item">
                    @error('comment') <p>{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="form__button">
            @if($data['application'] === null)
                <button class="form__button--submit" type="submit">
                    修正
                </button>
            @else
                <p class="readonly-message">
                    承認待ちのため修正できません
                </p>
            @endif
        </div>
    </form>
</div>
@endsection
