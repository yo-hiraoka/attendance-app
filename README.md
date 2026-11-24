
# 勤怠管理アプリケーション

Laravel 10を使用した勤怠管理アプリケーションです。

一般ユーザーの勤怠登録・修正申請、管理者による勤怠管理・修正承認などを実装しています。

## 使用技術（実行環境）

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

### 公開勤怠API v1（応用機能 FN057〜FN068）

勤怠情報をJSON形式で取得・登録・更新・削除するAPIです。

| HTTP | URL | 認証 |
|---|---|---|
| GET | `/api/v1/attendance-records` | 不要 |
| GET | `/api/v1/attendance-records/{attendanceRecord}` | 不要 |
| POST | `/api/v1/attendance-records` | Sanctum |
| PUT | `/api/v1/attendance-records/{attendanceRecord}` | Sanctum |
| PATCH | `/api/v1/attendance-records/{attendanceRecord}` | Sanctum |
| DELETE | `/api/v1/attendance-records/{attendanceRecord}` | Sanctum |

一覧取得では次の絞り込みに対応しています。

- `user_id`：ユーザーID
- `date`：勤務日（YYYY-MM-DD）
- `month`：対象月（YYYY-MM）
- `page`：ページ番号
- `per_page`：1ページあたりの件数（既定20、最大100）

書き込みAPIでは認証が必要です。
更新・削除は本人または管理者のみ実行できます。

主なHTTPステータスコード：

- 200：取得・更新成功
- 201：新規登録成功
- 204：削除成功
- 401：未認証
- 403：操作権限なし
- 404：対象データなし
- 422：入力検証エラー

日時はUTCで保存し、日本時間で入出力します。

#### 既知の制限事項

- 夜勤の翌日退勤時刻をAPI入力から新たに指定する機能は未対応です。
- 勤務日変更では関連する修正申請の日時も移動します。承認済み申請を含む業務上の取り扱いには追加確認が必要です。
- GET APIは仕様に従って認証不要です。個人の勤怠情報を返すため、実データを含む環境を外部公開しないでください。

## 認証・入力検証の実装対応

配布仕様のFN001/FN002、FN006/FN008、FN014/FN015に対応する入力検証・認証処理は以下の構成です。

| 要件 | 対象 | 実装 |
|---|---|---|
| FN001・FN002 | 会員登録 | Fortifyの登録処理と`RegistrationRequest`（FormRequest）。`CreateNewUser`でも同じ入力規則を使用 |
| FN006・FN008 | 一般ログイン | Fortifyの`LoginRequest`と認証処理 |
| FN014・FN015 | 管理者ログイン | `AdminLoginRequest`（Fortify `LoginRequest`継承）とFortifyの認証パイプライン |
| FN028 | 一般勤怠修正 | `AttendanceCorrectionRequest`が入力形式を検証し、Controllerで業務上の時刻整合性を確認 |

管理者ログインは`/admin/login`、一般ログインは`/login`を使用します。認証時にルートに応じた役割を確認し、管理者が一般ログインから入ること、および一般ユーザーが管理者ログインから入ることを禁止しています。

## 提出前の画面・動作確認

以下は**手動確認のためのチェック項目**です。GitHub Actionsの成功だけでは確認済みとはなりません。

- [ ] `/register`：必須項目、メール形式、パスワード確認エラー、登録成功を画面で確認
- [ ] `/login`・`/admin/login`：通常ログイン、権限の異なるアカウントの拒否、ログアウトを確認
- [ ] Mailpit：メール認証の送信・認証・再送を確認
- [ ] `/attendance`：出勤、複数休憩、休憩戻、退勤、ステータス表示を確認
- [ ] `/attendance/list`・`/attendance/detail/{id}`：一覧、前月・翌月、詳細と打刻時刻を確認
- [ ] 勤怠修正申請：申請・承認待ち表示・管理者承認・承認済み反映を確認
- [ ] 管理者画面：日別一覧、前日・翌日、直接修正、スタッフ別一覧とCSV出力を確認
- [ ] 応用機能：本人のレポート、およびAPIの取得・登録・更新・削除を仕様の範囲内で確認
- [ ] 提出用の基本設計書・テーブル仕様書・ER図と実装を最終照合

### 手動確認の結果（2026年11月24日）

次の内容は開発者によるブラウザ確認の報告です。GitHub Actionsによる自動テストとは区別しています。

| 対象 | 確認方法 | 結果 |
|---|---|---|
| 基本テストID1〜15 | 会員登録・ログイン・打刻・一覧・詳細・修正申請・管理者承認のブラウザ操作 | 報告された対象項目は成功 |
| 応用ID16 | Mailpitの認証メール送信・画面遷移・認証・再送 | 成功 |
| 応用ID17 | API GETの一覧・詳細・存在しないIDのレスポンス | 成功 |
| 応用ID18・19 | 書き込みAPI・SanctumのGitHub Actions自動テスト | 自動テスト成功（手動実行は未実施） |
| 応用ID20 | マイ勤怠レポート、統計・空データ・未認証アクセス | 成功 |
| CSV | スタッフ別CSVのダウンロード、UTF-8表示、列と一部勤務時間 | 画像で確認（全行照合は未実施） |
| 提出前の視覚確認 | 勤怠詳細の氏名欄・時刻形式の日本語エラー・スタッフ一覧見出し | ブラウザ画像・操作で成功報告 |

※ 個別テストケースの全条件や採点結果を保証するものではありません。提出用の基本設計書・テーブル仕様書・ER図は、別途作成したExcelで現行実装との照合を行っています。\n\n※ 仕様書同士に差異があるFN029の文言と、応用APIの物理テーブル名は配布元へ確認中のため、このチェックでは判断・変更していません。

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
## ER図

![勤怠管理アプリのER図](docs/er-diagram.png)

## URL

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

### COACHTECH提出・採点用ダミーデータ

採点用のアカウントと勤怠記録を作成する場合は、**初期セットアップ時に以下のコマンドを実行してください。**

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```

標準の `db:seed` から `SubmissionSampleSeeder` が呼び出され、採点用データが作成されます。

個別に実行する場合は、次のコマンドも利用できます。

```bash
./vendor/bin/sail artisan db:seed --class=SubmissionSampleSeeder
```

**注意：** 採点用データには共通パスワードを使用します。学習・採点用環境でのみ実行してください。既存データがあるDBや本番環境では実行しないでください。

| 種類 | メールアドレス | パスワード |
|---|---|---|
| 一般ユーザー1 | user1@example.com | password |
| 一般ユーザー2 | user2@example.com | password |
| 管理者 | user3@example.com | password |

一般ユーザーは `/login`、管理者は `/admin/login` からログインしてください。

- 3アカウントはメール認証済みで作成されます。
- 一般ユーザー1には、過去5か月の勤怠と、当月の最初の平日17日分の完了済み勤怠を作成します。
- 実行日によっては、一般ユーザー1に当日・未来日の勤怠が存在し、当日の出勤打刻ができない場合があります。
- 当日の出勤・休憩・退勤操作の確認には、当月の勤怠を投入していない一般ユーザー2（`user2@example.com`）を利用してください。
- データの日付はSeeder実行時の月を基準とします。
- 指定されたユーザーが既に存在する場合、既存データを上書きせず投入を中止します。
- 採点用の共通パスワードを使用するため、本番環境では実行しないでください。
- Migrationだけでは採点用データは投入されません。学習・採点用の初期環境で上記Seederコマンドを実行してください。

## テスト

### PHPUnit

テストはDBを初期化します。まず、Sailの `mysql:3306` の `testing` が削除してよい専用DBであり、通常DB・本番DBに接続しないことを確認してください。DB名やユーザー名だけでは専用性を保証できません。

MySQL管理者が、対象サーバーと既存ユーザーを確認したうえで、対話的なMySQLクライアントから次のSQLを実行します。すでに同名アカウントがある場合は作成せず、そのアカウントと権限を管理者に確認してください。

```sql
CREATE USER 'attendance_test'@'%' IDENTIFIED BY RANDOM PASSWORD;
GRANT ALL PRIVILEGES ON `testing`.* TO 'attendance_test'@'%';
SHOW GRANTS FOR 'attendance_test'@'%';
```

生成されたパスワードは安全に保管し、共有しないでください。`SHOW GRANTS` と付与されたロールを確認し、`testing` 以外のDBやグローバル権限がないことを確認します。実際にテストユーザーで接続した場合は、次のSQLでMySQLが選んだアカウントと有効なロールも確認できます。

```sql
SELECT CURRENT_USER(), CURRENT_ROLE(), DATABASE();
SHOW GRANTS FOR CURRENT_USER;
```

リポジトリのルートにGit管理外の `.env.testing` を作り、テスト専用接続情報と生成されたパスワードを設定してください。少なくとも `DB_CONNECTION=mysql`、`DB_HOST=mysql`、`DB_PORT=3306`、`DB_DATABASE=testing`、`DB_USERNAME=attendance_test`、`DB_PASSWORD` が必要です。アプリキーなどテストに必要な他の環境設定も用意してください。秘密情報をコミット・共有しないでください。

PHPUnitは `APP_ENV=testing` を強制し、Laravelは `.env.testing` を読み込みます。`DB_DATABASE` と `DB_USERNAME` はPHPUnitの既定値で、既存のプロセス環境変数が優先されます。異なる値はガードが拒否します。`DB_PASSWORD` は `.env.testing` の値を確実に使うため、実行プロセスに既に定義されている場合（空値を含む）は拒否します。接続URL、設定キャッシュ、Unixソケット、読み書き別接続、追加接続オプションも拒否します。

次のコマンドはテスト起動時の設定を確認します。DBへの接続やMigrationは行いません。成功は設定値を示すものであり、DBサーバーの正体や権限を保証しません。

```bash
./vendor/bin/sail phpunit --filter test_effective_test_database_configuration_is_allowed
```

専用DBと専用ユーザーの権限を確認した場合に限り、テストを実行します。

```bash
./vendor/bin/sail artisan test
```

エラー時は `.env.testing` の存在、テストプロセスに継承された環境変数（`DB_PASSWORD`、`DB_USERNAME`、`DB_DATABASE`、`DATABASE_URL`、`DB_URL` を含む）、設定キャッシュを確認してください。`DB_PASSWORD` がコンテナ環境に定義されている場合は、例えば `docker compose exec laravel.test env -u DB_PASSWORD php vendor/bin/phpunit --filter test_effective_test_database_configuration_is_allowed` のようにテストプロセスから除外できます。他のコマンドにも同じ注意が必要です。並列テストは専用DBの安全性を確認できる仕組みが整うまで非対応です。

### Laravel Pint

```bash
./vendor/bin/sail pint --test
```

### コード差分チェック

```bash
git diff --check
```

GitHub Actionsの最新確認実績（2026年11月24日・`main`、コミット `d895c2974ebe`）：

- PHPUnit：233件PASS（919 assertions）
- Laravel Pint：125ファイルPASS
- 実行結果：https://github.com/yo-hiraoka/attendance-app/actions/runs/45315352707

※ 上記はGitHub Actionsでの自動テスト結果です。提出時のブラウザ操作確認・デザインの目視確認を完了したことは意味しません。

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
- 本番環境に公開する場合は、依存パッケージのセキュリティ確認と環境設定の見直しが必要です。

## セキュリティ・運用上の注意

2026年11月14日時点のローカル監査結果：

- Laravel Framework：10.50.4
- Composer：laravel/framework に関するセキュリティ勧告5件
  - Low：2件
  - Medium：1件
  - High：1件
  - 深刻度未記載：1件
- npm audit --omit=dev：脆弱性0件

Composerの5件には、同一のGitHub Advisoryを参照する
重複した勧告が含まれています。

Laravel 10は公式セキュリティサポートが終了しています。

本プロジェクトは学習課題としてLaravel 10を使用しており、
今回の作業では依存パッケージを更新していません。

実データを扱う本番環境への公開前には、以下の対応が必要です。

- サポート対象のLaravelへのアップグレード
- Composerとnpmのセキュリティ監査
- 全機能の回帰テスト
- APIの認証・公開範囲の見直し
- 本番環境の設定と秘密情報の保護
