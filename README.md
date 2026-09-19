# LINE 打刻システム

スタッフ向けの LINE LIFF アプリケーション。出勤・退勤・休憩時間の打刻と給与情報の閲覧が可能です。

## 機能

- 📱 **LINE LIFF 統合**: LINE でログイン
- ⏰ **打刻機能**: 出勤・退勤・休憩時間の記録
- 📍 **GPS 位置情報**: 出勤・退勤時に緯度経度を記録
- 📊 **給与表示**: 本日の給与見込み・月別給与情報・過去の勤務履歴
- 🔐 **セッション認証**: 安全なセッションベース認証

## ディレクトリ構成

```
line-timecard-liff/
├── index.liff.php           ← メイン LIFF 画面
├── config.php               ← 設定ファイル
├── db.php                   ← DB接続・ユーティリティクラス
├── schema.sql               ← DB スキーマ
├── api/
│   ├── clock_in.php         ← 出勤 API
│   ├── clock_out.php        ← 退勤 API
│   ├── break_start.php      ← 休憩開始 API
│   ├── break_end.php        ← 休憩終了 API
│   ├── get_today_status.php ← 本日の状態取得 API
│   ├── get_history.php      ← 出勤履歴取得 API
│   ├── get_payroll.php      ← 給与情報取得 API
│   ├── line_login.php       ← LINE ログイン API
│   └── logout.php           ← ログアウト API
├── css/
│   └── style.css            ← LIFF 画面スタイル
├── js/
│   └── app.js               ← メインアプリケーションロジック
├── common.js                ← 共通スクリプト
└── common.css               ← 共通スタイル
```

## インストール・セットアップ

### 1. リポジトリをクローン

```bash
git clone https://github.com/mogumogufarm/line-timecard-liff.git
cd line-timecard-liff
```

### 2. データベース作成

MySQL にログインして、新しいデータベースを作成：

```sql
CREATE DATABASE line_timecard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. スキーマを実行

```bash
mysql -u root -p line_timecard < schema.sql
```

**注意**: 本システムはスタッフテーブルが mogumogu-farm-system DB に存在することを前提としています。
cross-DB JOIN が必要な場合は、schema.sql の `ALTER TABLE staff` をスキップしてください。

### 4. 設定ファイルを作成

`config.php` の環境変数を設定：

```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'password');
define('DB_NAME', getenv('DB_NAME') ?: 'line_timecard');

define('LINE_CHANNEL_ID', getenv('LINE_CHANNEL_ID') ?: 'your-channel-id');
define('LIFF_ID', getenv('LIFF_ID') ?: 'your-liff-id');
```

### 5. LINE LIFF 設定

1. LINE Developers コンソールで新しい Channel を作成
2. LIFF (LINE Front-end Framework) を設定
3. `LIFF_ID` を `js/app.js` に設定：

```javascript
liff.init({
    liffId: 'YOUR_LIFF_ID'
});
```

## API 仕様

### 認証

すべての API は SESSION ベースの認証を使用します。
認証に失敗した場合は 401 Unauthorized を返します。

### 打刻 API

#### POST /api/clock_in.php
**出勤時刻を記録**

リクエスト:
```json
{
    "latitude": 35.1234567,
    "longitude": 138.2345678
}
```

レスポンス:
```json
{
    "success": true,
    "message": "出勤しました",
    "data": {
        "clock_in_time": "09:30:45"
    }
}
```

#### POST /api/break_start.php
**休憩を開始**

レスポンス:
```json
{
    "success": true,
    "message": "休憩を開始しました",
    "data": {
        "break_start_time": "12:00:00"
    }
}
```

#### POST /api/break_end.php
**休憩を終了**

レスポンス:
```json
{
    "success": true,
    "message": "休憩を終了しました",
    "data": {
        "break_end_time": "13:00:00"
    }
}
```

#### POST /api/clock_out.php
**退勤時刻を記録・給与を計算**

リクエスト:
```json
{
    "latitude": 35.1234567,
    "longitude": 138.2345678
}
```

レスポンス:
```json
{
    "success": true,
    "message": "退勤しました",
    "data": {
        "clock_out_time": "17:30:45",
        "working_hours": 7.92,
        "gross_salary": 9704
    }
}
```

### 情報取得 API

#### GET /api/get_today_status.php
**本日の勤務状態を取得**

レスポンス:
```json
{
    "success": true,
    "data": {
        "is_clocked_in": true,
        "is_on_break": false,
        "clock_in_time": "09:30:45",
        "clock_out_time": null,
        "working_hours": 7.92,
        "expected_salary": 9704,
        "hourly_rate": 1200,
        "transport_fee": 200
    }
}
```

#### GET /api/get_history.php
**過去 5 日間の出勤履歴を取得**

レスポンス:
```json
{
    "success": true,
    "data": {
        "history": [
            {
                "date": "2026-09-19",
                "clock_in": "09:30:45",
                "clock_out": "17:30:45",
                "working_hours": 7.92
            }
        ]
    }
}
```

#### POST /api/get_payroll.php
**月別の給与情報を取得**

リクエスト:
```json
{
    "year_month": "2026-09"
}
```

レスポンス:
```json
{
    "success": true,
    "data": {
        "year_month": "2026-09",
        "working_hours": 160.0,
        "transport_fee": 4000,
        "gross_salary": 196000,
        "daily_records": [
            {
                "date": "2026-09-01",
                "clock_in": "09:00:00",
                "clock_out": "17:30:00",
                "working_hours": 8.0
            }
        ]
    }
}
```

### 認証 API

#### POST /api/line_login.php
**LINE アカウントでログイン**

リクエスト:
```json
{
    "id_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

レスポンス:
```json
{
    "success": true,
    "message": "ログインしました",
    "data": {
        "staff_id": 1,
        "name": "田中太郎"
    }
}
```

## デザイン仕様

- **フォント**: Noto Sans JP
- **背景**: グラデーション (#EFE8D8 → #F7F3E9)
- **テキスト色**: #2E3B22（深緑）
- **プライマリカラー**: #3E5A1F
- **ボタンスタイル**: border-radius 14px, font-weight 700
- **カード**: border-radius 20px, box-shadow 0 4px 20px rgba(47, 93, 58, 0.08)
- **レスポンシブ**: モバイルファースト (max-width: 480px)

## 環境変数

`.env` ファイルで設定（本番環境）：

```
DB_HOST=db.example.com
DB_USER=line_timecard_user
DB_PASS=secure_password
DB_NAME=line_timecard

LINE_CHANNEL_ID=1234567890
LINE_CHANNEL_SECRET=your_channel_secret
LIFF_ID=your-liff-id

APP_URL=https://mogucorp.com/liff/
```

## 本番デプロイ

### Sakura レンタルサーバーへのデプロイ

1. **git push** でリモートにプッシュ
2. サーバーの CRON (auto_deploy.sh) が自動で反映
3. または SSH で手動デプロイ：

```bash
ssh user@mogucorp.com
cd /home/mogucorp/www/mogucorp
git pull origin main
```

## セキュリティ考慮事項

- ✅ セッションベース認証
- ✅ CSRF トークン（PHP セッションに含まれる）
- ⚠️ **TODO**: HTTPS 環境で使用してください（GPS 許可に必須）
- ⚠️ **TODO**: LINE ID Token の署名検証を実装
- ⚠️ **TODO**: SQL インジェクション対策（prepared statements を使用）
- ⚠️ **TODO**: 入力値のサニタイズ

## トラブルシューティング

### GPS 取得失敗
- HTTPS 環境で実行しているか確認
- ブラウザの位置情報許可を確認
- GPS が取得できない場合も処理は継続します

### LIFF 初期化失敗
- `LIFF_ID` が正しく設定されているか確認
- LINE Developers コンソールで LIFF を有効化
- ブラウザのコンソールを確認

### データベース接続エラー
- config.php の DB 設定を確認
- MySQL サーバーが起動しているか確認
- データベースとテーブルが作成されているか確認

## 今後の実装予定

- [ ] LINE Bot との連携（給与明細自動送信）
- [ ] 地図表示機能（打刻位置の確認）
- [ ] ジオフェンス機能（認可された場所のみ打刻可能）
- [ ] 管理画面（スタッフ管理、給与確認）
- [ ] PDF 給与明細生成
- [ ] メール通知機能

## ライセンス

Private (もぐもぐ農園)

## サポート

問題や質問がある場合は、GitHub Issues で報告してください。
