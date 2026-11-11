@extends('layouts.admin-app')

@section('css')
@vite('resources/css/admin/admin-attendance-list.css')
@endsection

@section('content')
<div class="attendance-list__content">
    <div class="content__header">
        <h1 class="content__header--item">{{ $date->format('Y年m月d日') }}の勤怠</h1>
    </div>
    <div class="content__menu">
        <a class="previous-day" href="?date={{ $previousDay }}">前日</a>
        <p class="current-day">{{ $date->format('Y/m/d') }}</p>
        <a class="next-day" href="?date={{ $nextDay }}">翌日</a>
    </div>
    <table class="table">
        <tr class="table__row">
            <th class="table__header">
                <p class="table__header--item">名前</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">出勤</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">退勤</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">休憩</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">合計</p>
            </th>
            <th class="table__header">
                <p class="table__header--item">詳細</p>
            </th>
        </tr>
    @foreach ($attendanceRecords as $attendance)
            <tr class="table__row">
                <td class="table__description">
                    <p class="table__description--item">{{ $attendance['name'] }}</p>
                </td>
                <td class="table__description">
                    <p class="table__description--item">{{ $attendance['clock_in'] }}</p>
                </td>
                <td class="table__description">
                    <p class="table__description--item">{{ $attendance['clock_out'] }}</p>
                </td>
                <td class="table__description">
                    <p class="table__description--item">
                        {{ $attendance['total_break_time'] }}
                    </p>
                </td>
                <td class="table__description">
                    <p class="table__description--item">{{ $attendance['total_time'] }}</p>
                </td>
                <td class="table__description">
                    <a class="table__item--detail-link"
                        href="{{ route('admin.attendance.show', ['id' => $attendance['id']]) }}">
                        詳細
                    </a>
                </td>
            </tr>
        @endforeach
    </table>
</div>
@endsection
