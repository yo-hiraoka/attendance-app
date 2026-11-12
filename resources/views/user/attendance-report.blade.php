@extends('layouts.app')

@section('css')
@vite('resources/css/user/attendance-report.css')
@endsection

@section('content')
@php
    $formatDuration = static function (string $duration): string {
        [$hours, $minutes] = explode(':', $duration);

        return (int) $hours . 'h ' . (int) $minutes . 'm';
    };
@endphp

<div class="report">
    <div class="report__inner">
        <header class="report__header">
            <h1>マイ勤怠レポート</h1>
            <p>過去６ヶ月の勤怠データから集計しています。</p>
        </header>

        <section class="report__section">
            <h2>基本サマリー</h2>

            <div class="report__summary">
                <div class="report__card">
                    <p class="report__label">総労働時間</p>
                    <p class="report__value">
                        {{ $formatDuration($summary['work_duration']) }}
                    </p>
                </div>

                <div class="report__card">
                    <p class="report__label">総残業時間</p>
                    <p class="report__value">
                        {{ $formatDuration($summary['overtime_duration']) }}
                    </p>
                </div>

                <div class="report__card">
                    <p class="report__label">平均労働時間／日</p>
                    <p class="report__value">
                        {{ $formatDuration($summary['average_duration']) }}
                    </p>
                </div>
            </div>
        </section>

        <section class="report__section">
            <h2>月次推移（過去６ヶ月）</h2>

            <div class="report__table-wrapper">
                <table class="report__table">
                    <thead>
                        <tr>
                            <th scope="col">月</th>
                            <th scope="col">労働時間</th>
                            <th scope="col">残業時間</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach (array_reverse($monthly_trend) as $month)
                            <tr>
                                <td>{{ $month['month'] }}</td>
                                <td>
                                    {{ $formatDuration($month['work_duration']) }}
                                </td>
                                <td>
                                    {{ $formatDuration($month['overtime_duration']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report__section">
            <h2>今月の異常検知</h2>

            <p class="report__criteria">
                基準：始業 09:00 ／ 終業 18:00 ／
                長時間労働は１日10時間超
            </p>

            <div class="report__summary">
                <div class="report__card">
                    <p class="report__label">遅刻回数</p>
                    <p class="report__value">
                        {{ $anomalies['late'] }}
                        <span class="report__count-unit">回</span>
                    </p>
                </div>

                <div class="report__card">
                    <p class="report__label">早退回数</p>
                    <p class="report__value">
                        {{ $anomalies['early'] }}
                        <span class="report__count-unit">回</span>
                    </p>
                </div>

                <div class="report__card">
                    <p class="report__label">長時間労働日数</p>
                    <p class="report__value">
                        {{ $anomalies['long_hours'] }}
                        <span class="report__count-unit">日</span>
                    </p>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
