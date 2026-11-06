# データベース設計方針

## 基本方針

本アプリでは、以下の5テーブル構成を採用する。

1. `users`：ユーザー情報・管理者区分
2. `attendances`：勤怠記録
3. `break_times`：休憩記録
4. `stamp_correction_requests`：勤怠修正申請
5. `correction_request_breaks`：修正申請に含まれる休憩記録

**DBカラム名は、COACHTECH提供Bladeの変数名・フォーム入力名に合わせて変更しない。**

DBとBladeの名前が異なる場合は、Controller・FormRequestなどのアプリケーション層で変換する。

## DBとBladeの対応関係

| テーブル | DBカラム | Blade側の名前 |
|---|---|---|
| attendances | work_date | date |
| attendances | clock_in_at | clock_in |
| attendances | clock_out_at | clock_out |
| break_times | started_at | break_in |
| break_times | ended_at | break_out |
| stamp_correction_requests | requested_clock_in_at | new_clock_in |
| stamp_correction_requests | requested_clock_out_at | new_clock_out |
| stamp_correction_requests | reason | comment |

Blade側の名前はフォーム入力名または表示用の変数名であり、DBカラムそのものではない。

休憩修正用の `new_break_in[]`・`new_break_out[]` も、アプリケーション層で変換する。

## データの保存・表示

- DBでは日時を適切な日時型で保存する。
- Bladeでは必要に応じて `H:i` などの表示形式へ変換する。
- フォームから送信された値は、バリデーション後にDBの形式へ変換する。
- 一般ユーザーの修正申請は承認前に元の勤怠記録を変更しない。
- 管理者が承認した場合に、元の勤怠記録へ反映する。

## 今後の開発ルール

- Bladeの変数名が異なることだけを理由にDBカラムを変更しない。
- DB設計の変更が必要な場合は、機能要件・テストケースを確認して判断する。
- Migration・Model・Controller・FormRequestの実装時には本資料を参照する。
- 詳細な型・制約・リレーションはIssue #3で最終確認する。

## 関連Issue

- Issue #2：提供Blade・CSS・Vite設定
- Issue #3：DB Migration・Model・リレーション

## タイムゾーン・日時管理方針

- Laravelのタイムゾーンは `Asia/Tokyo` とする。
- `work_date` は日本時間を基準とした勤務日を保存する。
- `clock_in_at`、`clock_out_at`、`started_at`、`ended_at` などの日時は、UTCで保存する方針とする。
- Bladeへの表示時には日本時間（JST）へ変換する。
- フォームから入力された時刻は、対象の勤務日と組み合わせ、UTCへ変換してから保存する。
- 日付をまたぐ勤務や休憩の扱いは、打刻機能実装時に要件と照合する。

**実装上の注意：** Laravelのタイムゾーン設定やEloquentの `datetime` キャストだけでは、UTC保存は保証されない。Controllerや日時変換処理を実装する際に、保存値と表示値の変換テストを行う。

本項目は設計方針であり、UTC保存・JST表示の実装は今後のIssueで行う。