# Sakura レンタルサーバーへのデプロイメントガイド

## 📋 前提条件

- Sakura レンタルサーバー（スタンダードプラン以上）のアカウント
- SSH アクセス権限
- MySQL 管理画面（phpMyAdmin）へのアクセス権限
- LINE Developers アカウント＋Channel ID と Secret
- git コマンドがサーバーにインストール済み

---

## 🔧 デプロイメント手順

### **Phase 1: データベース作成（Sakura パネル）**

#### 1-1. phpMyAdmin で新規データベースを作成

1. **Sakura パネル** → **データベース** → **phpMyAdmin にログイン**
2. **左メニュー** → **新規** → **データベースを作成**
3. **データベース名**: `mogucorp_timecard`（または自社の命名ルール）
4. **照合順序**: `utf8mb4_unicode_ci`
5. **作成** ボタンをクリック

#### 1-2. スキーマ SQL を実行

1. **phpMyAdmin** → 新しく作成したデータベースを選択
2. **SQL** タブをクリック
3. 下記の SQL をコピー＆ペーストして実行

```sql
-- timecards テーブル（打刻記録）
CREATE TABLE IF NOT EXISTS timecards (
  id INT PRIMARY KEY AUTO_INCREMENT,
  staff_id INT NOT NULL,
  date DATE NOT NULL,
  clock_in_time TIME,
  break_start_time TIME,
  break_end_time TIME,
  clock_out_time TIME,
  latitude DECIMAL(10, 8),
  longitude DECIMAL(11, 8),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (staff_id) REFERENCES staff(id),
  UNIQUE KEY unique_daily_record (staff_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- payroll_settings テーブル（給与設定）
CREATE TABLE IF NOT EXISTS payroll_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  closing_day INT DEFAULT 30,
  break_duration_minutes INT DEFAULT 30,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- payroll_records テーブル（給与記録）
CREATE TABLE IF NOT EXISTS payroll_records (
  id INT PRIMARY KEY AUTO_INCREMENT,
  staff_id INT NOT NULL,
  year_month VARCHAR(7),
  working_hours DECIMAL(10, 2),
  transport_fee INT,
  gross_salary INT,
  status ENUM('pending', 'confirmed', 'sent') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (staff_id) REFERENCES staff(id),
  UNIQUE KEY unique_monthly_record (staff_id, year_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- staff テーブルに LINE 関連カラムを追加（既存テーブルがある場合）
-- ※既に存在する場合は何もしない
ALTER TABLE staff ADD COLUMN IF NOT EXISTS line_user_id VARCHAR(255) UNIQUE COMMENT 'LINE ユーザーID';
ALTER TABLE staff ADD COLUMN IF NOT EXISTS hourly_rate INT DEFAULT 1200 COMMENT '時給（円）';
ALTER TABLE staff ADD COLUMN IF NOT EXISTS transport_fee_per_day INT DEFAULT 200 COMMENT '交通費（1日分、円）';
```

✅ **実行完了メッセージ** が表示されれば成功です。

---

### **Phase 2: 環境変数設定**

#### 2-1. Sakura サーバーに SSH でログイン

```bash
ssh -u mogucorp mogucorp.sakura.ne.jp
```

#### 2-2. .env ファイルを作成（本番環境変数）

```bash
cd /home/mogucorp/www/mogucorp/line-timecard-liff

cat > .env << 'EOF'
# Database Configuration
DB_HOST=localhost
DB_USER=mogucorp_db
DB_PASS=your_secure_password_here
DB_NAME=mogucorp_timecard

# LINE Configuration
LINE_CHANNEL_ID=1234567890
LINE_CHANNEL_SECRET=your_channel_secret_here
LIFF_ID=1234567890-abcdef

# Application Configuration
APP_URL=https://mogucorp.com/line-timecard-liff/
TIMEZONE=Asia/Tokyo
EOF
```

**注意**: 以下の値は Sakura パネルで確認して入力してください：
- `DB_PASS`: Sakura パネル → データベース → パスワード
- `LINE_CHANNEL_ID`, `LINE_CHANNEL_SECRET`, `LIFF_ID`: LINE Developers Console

#### 2-3. .env のパーミッション設定

```bash
chmod 600 .env
```

#### 2-4. config.php を .env から読み込むように更新

```bash
cat > config.php << 'EOF'
<?php
// .env ファイルから設定を読み込む
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

// データベース設定
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'line_timecard');

// LINE LIFF 設定
define('LINE_CHANNEL_ID', getenv('LINE_CHANNEL_ID') ?: '');
define('LINE_CHANNEL_SECRET', getenv('LINE_CHANNEL_SECRET') ?: '');
define('LIFF_ID', getenv('LIFF_ID') ?: '');

// アプリケーション設定
define('APP_URL', getenv('APP_URL') ?: 'https://mogucorp.com/line-timecard-liff/');
define('SESSION_TIMEOUT', 3600);
define('TIMEZONE', getenv('TIMEZONE') ?: 'Asia/Tokyo');

date_default_timezone_set(TIMEZONE);
session_start();
?>
EOF
```

---

### **Phase 3: Git にプッシュ**

#### 3-1. ローカルで .gitignore に .env を追加

```bash
cd /path/to/local/line-timecard-liff

echo ".env" >> .gitignore
git add .gitignore
git commit -m "Add .env to gitignore for security"
```

#### 3-2. コミット＆プッシュ

```bash
git add -A
git commit -m "Complete LINE timecard system implementation - ready for production

- Database schema: timecards, payroll_records, payroll_settings
- 9 API endpoints with authentication
- Frontend with LINE LIFF integration
- CSS and JavaScript UI
- GPS location tracking
- Salary calculation logic
- Environment variable configuration"

git push origin main
```

---

### **Phase 4: Sakura サーバーで git pull**

#### 4-1. Sakura 上で git pull を実行

```bash
cd /home/mogucorp/www/mogucorp/line-timecard-liff

git pull origin main
```

#### 4-2. ファイルパーミッション設定

```bash
# PHP ファイルは 644 (読み取り)
chmod 644 *.php
chmod -R 644 api/*.php
chmod -R 644 css/*.css
chmod -R 644 js/*.js

# ディレクトリは 755
chmod 755 api css js

# .env は 600 (セキュリティ)
chmod 600 .env
```

#### 4-3. 最新コミットを確認

```bash
git log -1 --oneline
# 出力例: 56bbc33 Complete LINE timecard system implementation
```

---

### **Phase 5: 自動デプロイ設定（CRON）**

もし `auto_deploy.sh` が既に存在する場合、以下を確認：

```bash
cat /home/mogucorp/cron/auto_deploy.sh
```

存在しない場合は作成：

```bash
cat > /home/mogucorp/cron/auto_deploy.sh << 'EOF'
#!/bin/bash

# LINE 打刻システム自動デプロイ
cd /home/mogucorp/www/mogucorp/line-timecard-liff || exit 1

# git pull を実行
git pull origin main

# ファイルパーミッションを修正
chmod 644 *.php
chmod -R 644 api/*.php css/*.css js/*.js
chmod 755 api css js
chmod 600 .env 2>/dev/null

# ログに記録
echo "[$(date)] Deploy completed" >> /tmp/auto_deploy.log
EOF

chmod 755 /home/mogucorp/cron/auto_deploy.sh
```

Sakura パネルで **定期タスク（CRON）** を設定：
- 実行コマンド: `/home/mogucorp/cron/auto_deploy.sh`
- 実行頻度: **1分ごと**（または 5分ごと）

---

## ✅ デプロイメント検証

### **Step 1: ファイルが配置されているか確認**

```bash
cd /home/mogucorp/www/mogucorp/line-timecard-liff

ls -la
# 出力すべき内容:
# index.liff.php
# config.php
# db.php
# .env
# api/
# css/
# js/
```

### **Step 2: DB接続テスト**

PHP で DB 接続をテスト：

```bash
php -r "
require 'db.php';
try {
    \$result = \$db->query('SELECT 1');
    echo 'DB接続: OK' . PHP_EOL;
} catch (Exception \$e) {
    echo 'DB接続エラー: ' . \$e->getMessage() . PHP_EOL;
}
"
```

### **Step 3: ブラウザでアクセステスト**

```
https://mogucorp.com/line-timecard-liff/index.liff.php
```

**期待される表示:**
- LINE ログイン画面
- または（ログイン済みの場合）打刻画面

### **Step 4: LINE LIFF アプリでテスト**

1. **LINE Developers Console** → LIFF → エンドポイント URL を確認
2. **LINE アプリ** → 設定したボタンから LIFF を開く
3. **LINE ログイン** → スタッフ情報が表示されることを確認

---

## 🐛 トラブルシューティング

### **1. DB接続エラー**

**症状**: `Connection failed: ...`

**解決:**
```bash
# config.php の DB 情報を確認
cat .env | grep DB_

# Sakura パネルでユーザー作成を確認
# phpMyAdmin → ユーザー → mogucorp_db が存在するか確認
```

### **2. LINE ログイン失敗**

**症状**: `Invalid token` または 401 Unauthorized

**解決:**
```bash
# LIFF_ID が正しいか確認
cat .env | grep LIFF_ID

# LINE Developers Console で LIFF の設定を再確認
# - エンドポイント URL が正しいか
# - スコープが正しいか（openid profile）
```

### **3. GPS が取得できない**

**症状**: GPS 座標が NULL で保存される

**解決:**
- HTTPS 環境か確認（GPS には HTTPS 必須）
- ブラウザの位置情報許可を確認
- GPS が無い環境でも NULL で処理は継続

### **4. CRON が動作していない**

**症状**: `git pull` が手動でも反映されない

**解決:**
```bash
# CRON ログを確認
tail -f /tmp/auto_deploy.log

# 手動で実行テスト
bash /home/mogucorp/cron/auto_deploy.sh

# git pull が成功しているか確認
cd /home/mogucorp/www/mogucorp/line-timecard-liff
git status
```

---

## 📊 デプロイメント完了チェックリスト

- [ ] DB を Sakura サーバーに作成
- [ ] テーブルが作成されたことを phpMyAdmin で確認
- [ ] .env ファイルを Sakura サーバーに配置
- [ ] config.php を .env 対応に更新
- [ ] git add / commit / push を実行
- [ ] Sakura サーバーで git pull を実行
- [ ] ファイルパーミッションを設定
- [ ] DB 接続をテスト
- [ ] https://mogucorp.com/line-timecard-liff/ にアクセス
- [ ] LINE ログイン画面が表示される
- [ ] CRON 自動デプロイを設定
- [ ] git log で最新コミットが反映されている

---

## 🔐 セキュリティチェックリスト

- [ ] .env ファイルのパーミッションを 600 に設定
- [ ] .env を .gitignore に追加（本番パスワード漏洩防止）
- [ ] HTTPS を有効化（GPS 位置情報に必須）
- [ ] SQL インジェクション対策: Prepared Statements を使用（実装済み）
- [ ] CSRF 対策: セッションベース認証（実装済み）
- [ ] LINE ID Token の署名検証（本番環境で実装推奨）

---

## 📞 サポート

デプロイ時にエラーが発生した場合：

1. **Sakura サポート**: https://server.sakura.ad.jp/
2. **GitHub Issues**: このリポジトリで報告
3. **LINE Developers**: https://developers.line.biz/

---

**作成日**: 2026-09-19  
**更新日**: 2026-09-19  
**バージョン**: v1.0
