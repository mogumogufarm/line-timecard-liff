<?php
/**
 * ログアウト API
 */

require_once '../db.php';

Auth::logout();

header('Content-Type: application/json; charset=utf-8');
ApiResponse::success('ログアウトしました');
?>
