@extends('layouts.admin-app')

@section('css')
@vite('resources/css/admin/admin-application-list.css')
@endsection

@section('content')
<div class="application-list__content">
    <div class="content__header">
        <h1 class="content__header--item">申請一覧</h1>
    </div>

    <div class="application__tab">
        <input class="application__tab--input"
                id="tab1" type="radio" name="tab_item" checked>
        <label class="application__tab--label" for="tab1">
            承認待ち
        </label>

        <input class="application__tab--input"
                id="tab2" type="radio" name="tab_item">
        <label class="application__tab--label" for="tab2">
            承認済み
        </label>

        @foreach (['pending' => 'content1', 'approved' => 'content2'] as $status => $contentId)
        <div class="tab__content" id="{{ $contentId }}">
            <table class="table">
                <tr class="table__row">
                    <th class="table__header">状態</th>
                    <th class="table__header">名前</th>
                    <th class="table__header">対象日時</th>
                    <th class="table__header">申請理由</th>
                    <th class="table__header">申請日時</th>
                    <th class="table__header">詳細</th>
                </tr>

                @foreach ($applications as $application)
                    @if ($application['status'] === $status)
                    <tr class="table__row">
                        <td class="table__description">
                            {{ $application['status_label'] }}
                        </td>
                        <td class="table__description">
                            {{ $application['name'] }}
                        </td>
                        <td class="table__description">
                            {{ $application['date'] }}
                        </td>
                        <td class="table__description">
                            {{ $application['reason'] }}
                        </td>
                        <td class="table__description">
                            {{ $application['created_at'] }}
                        </td>
                        <td class="table__description">
                            <a class="table__item--detail-link"
                                href="{{ route('admin.correction.show', ['id' => $application['id']]) }}">
                                詳細
                            </a>
                        </td>
                    </tr>
                    @endif
                @endforeach
            </table>
        </div>
        @endforeach
    </div>
</div>
@endsection
