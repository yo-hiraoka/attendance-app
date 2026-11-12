
# 勤怠管理アプリケーション

Laravel 10を使用した勤怠管理アプリケーションです。

一般ユーザーの勤怠登録・修正申請、管理者による勤怠管理・修正承認などを実装しています。

## 使用技術

- PHP 8.2
- Laravel 10
- Laravel Fortify
- MySQL 8.4
- Laravel Sail / Docker
- Blade / CSS / Vite
- PHPUnit
- Laravel Pint

## 主な機能

### 一般ユーザー

- 会員登録・ログイン・ログアウト
- メール認証
- 出勤・退勤打刻
- 休憩開始・終了（複数回対応）
- 月別勤怠一覧
- 勤怠詳細表示
- 勤怠修正申請
- 修正申請一覧（承認待ち・承認済み）
- マイ勤怠レポート（過去6か月の労働時間・残業時間・月次推移・異常検知）

### 管理者

- 管理者ログイン
- 日別勤怠一覧
- 勤怠詳細・直接修正
- スタッフ一覧
- スタッフ別月間勤怠一覧
- 修正申請一覧・詳細
- 勤怠修正申請の承認
- スタッフ別月間勤怠一覧のCSV出力

### CSV出力機能（応用機能 FN045）

管理者はスタッフ別の月間勤怠一覧から、CSVファイルをダウンロードできます。

操作手順：

1. 管理者としてログインする
2. 「スタッフ一覧」を開く
3. 対象スタッフの勤怠一覧を表示する
4. 必要に応じて前月・翌月へ移動する
5. 「CSV出力」をクリックする

出力項目：

- 日付
- 出勤時刻
- 退勤時刻
- 休憩時間
- 合計勤務時間

ファイル名：`attendance_staff_{スタッフID}_{YYYY-MM}.csv`

文字コード：UTF-8（BOM付き）

CSVには選択したスタッフ・対象月の勤怠情報のみ出力されます。
勤怠データがない場合は、ヘッダー行のみのCSVを出力します。

CSV出力は管理者専用機能です。

### マイ勤怠レポート（応用機能）

一般ユーザーは `/attendance/report` で、自分の勤怠統計を確認できます。

表示内容：

- 過去6か月（当月を含む）の総労働時間
- 1日8時間を超えた総残業時間
- 勤務完了日あたりの平均労働時間
- 過去6か月の月次推移
- 当月の遅刻・早退・長時間労働の回数

労働時間は休憩時間を差し引いて計算します。

異常検知の判定基準：
- 遅刻：日本時間9:00を超える出勤
- 早退：日本時間18:00より前の退勤
- 長時間労働：実働時間10時間超

未完了の勤怠は労働時間・残業時間の集計対象外です。
月次推移では、記録のない月は0として表示します。

一般ユーザー本人の勤怠情報のみを対象とし、
未ログインユーザーや管理者はアクセスできません。

## 環境構築

### 必要な環境

- Docker Desktop
- Docker Compose
- Git
- Composer
- Node.js / npm

Windowsの場合はWSL2上での実行を想定しています。

### Composerについて

このREADMEでは、WSL2またはホスト環境にPHPとComposerが
インストールされていることを前提としています。

`composer install` はLaravel Sail起動前に実行してください。

初回セットアップでは、PHPとComposerの対応バージョン、
必要なPHP拡張機能がインストールされていることを確認してください。

Laravel Sailの起動後は、Artisanやnpmなどのコマンドを
`./vendor/bin/sail` 経由で実行します。

### 1. リポジトリを取得

```bash
git clone git@github.com:yo-hiraoka/attendance-app.git
cd attendance-app
```

HTTPSで取得する場合は、次のURLも利用できます。

```text
https://github.com/yo-hiraoka/attendance-app.git
```

### 2. PHP依存パッケージをインストール

```bash
composer install
```

### 3. 環境変数を設定

```bash
cp .env.example .env
```

`.env` のMySQL接続情報が以下になっていることを確認してください。

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=attendance_app
DB_USERNAME=sail
DB_PASSWORD=password
```

※ `.env` はGitHubにコミットしないでください。

### 4. Dockerコンテナを起動

```bash
./vendor/bin/sail up -d
```

### 5. アプリケーションキーを生成

```bash
./vendor/bin/sail artisan key:generate
```

### 6. データベースを作成

```bash
./vendor/bin/sail artisan migrate
```

### 7. フロントエンドの準備

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

開発中にCSS等の変更を自動反映する場合：

```bash
./vendor/bin/sail npm run dev
```

### 8. ブラウザでアクセス

| サービス | URL |
|---|---|
| アプリケーション | http://localhost |
| 一般ユーザーログイン | http://localhost/login |
| 会員登録 | http://localhost/register |
| 管理者ログイン | http://localhost/admin/login |
| Mailpit | http://localhost:8025 |
| phpMyAdmin | http://localhost:8080 |

※ 使用ポートは `.env` の設定により変更できます。

## デモデータ（任意）

動作確認用のデータを投入する場合：

```bash
./vendor/bin/sail artisan db:seed --class=AttendanceDemoSeeder
```

### デモアカウント

`AttendanceDemoSeeder` を実行すると、以下のアカウントが作成されます。

| 種類 | メールアドレス | パスワード |
|---|---|---|
| 一般ユーザー1 | demo-user1@example.com | password |
| 一般ユーザー2 | demo-user2@example.com | password |
| 管理者 | demo-admin@example.com | password |

一般ユーザーは `/login`、管理者は `/admin/login` からログインしてください。

デモアカウントはメール認証済みで作成されます。

**注意：**
- これらのアカウントは開発・動作確認専用です。
- 本番環境では使用しないでください。
- 同じメールアドレスのデモユーザーが存在する場合、Seederはデータ投入を中止します。
- デモデータは自動投入されません。必要な場合のみSeederを実行してください。

デモユーザー・管理者のアカウント、勤怠記録、修正申請などが作成されます。

※ デモデータ投入は必須ではありません。既存データのある環境では実行前に内容を確認してください。

## テスト

### PHPUnit

```bash
./vendor/bin/sail artisan test
```

### Laravel Pint

```bash
./vendor/bin/sail pint --test
```

### コード差分チェック

```bash
git diff --check
```

開発時の確認実績：

- PHPUnit：124件PASS（471 assertions）
- Laravel Pint：100ファイルPASS

※ 上記はIssue #17完了時の結果です。

## データベース設計

主な業務テーブルは以下の5テーブルです。

1. `users`
2. `attendances`
3. `break_times`
4. `stamp_correction_requests`
5. `correction_request_breaks`

Laravel標準の管理用テーブルも使用しています。

詳しい設計方針は [データベース設計方針](docs/database-design.md) を参照してください。

## 補足事項

- 日時は原則UTCで保存し、表示時に日本時間へ変換します。
- 勤怠修正申請は管理者の承認後に勤怠記録へ反映されます。
- 管理者アカウントは一般ユーザーの会員登録画面から作成できません。
- CSV出力などの一部応用機能は未実装です。
- 本番環境に公開する場合は、依存パッケージのセキュリティ確認と環境設定の見直しが必要です。

## セキュリティ・運用上の注意

本アプリケーションはCOACHTECHの学習課題として、
Laravel 10を使用して開発しています。

Laravel 10は公式セキュリティサポートが終了しています。

2026年11月11日時点の監査結果：

- Composer：laravel/framework に関するセキュリティ勧告4件
- npm audit --omit=dev：脆弱性0件

本プロジェクトでは基本機能の実装・テストを優先し、
Laravelのメジャーバージョンアップは実施していません。

本番環境へ公開する場合は、サポート対象のLaravelへ
アップグレードし、依存パッケージの監査、全テスト、
セキュリティ設定の再確認を行う必要があります。

本番環境では APP_ENV=production、
APP_DEBUG=false を設定してください。

また、実際の認証情報、APP_KEY、メール設定等の
秘密情報をGitHubへ公開しないでください。
