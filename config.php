<?php
/**
 * LINE 打刻システム設定ファイル
 * 本番環境では .env または環境変数から読み込むこと
 */

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
define('APP_URL', getenv('APP_URL') ?: 'https://mogucorp.com/liff/');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('TIMEZONE', 'Asia/Tokyo');

// タイムゾーン設定
date_default_timezone_set(TIMEZONE);

// セッション開始
session_start();
?>
