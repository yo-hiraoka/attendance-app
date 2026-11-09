<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ホーム | 勤怠管理システム</title>
    @vite(['resources/css/sanitize.css', 'resources/css/common.css'])
</head>
<body>
    <header class="header">
        <div class="header__inner">
            <a class="header__logo" href="{{ route('admin.home') }}">
                <img src="{{ asset('images/logo.svg') }}" alt="logo">
            </a>
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button type="submit">ログアウト</button>
            </form>
        </div>
    </header>

    <main style="max-width: 800px; margin: 80px auto; text-align: center;">
        <h1>管理者ホーム</h1>
        <p>{{ Auth::user()->name }} さん、管理者としてログイン中です。</p>
        <p>勤怠管理・承認機能は現在開発中です。</p>
    </main>
</body>
</html>
