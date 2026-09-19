<?php
/**
 * 休憩終了 API
 */

require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 認証チェック
    $staff_id = Auth::requireLogin();

    $db = Database::getInstance();
    $today = DateUtils::today();
    $break_end_time = DateUtils::getCurrentTime();

    // 本日のレコードを確認
    $sql = "SELECT id FROM timecards WHERE staff_id = ? AND date = ? AND break_start_time IS NOT NULL";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $staff_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        ApiResponse::error('休憩開始記録が見つかりません', 400);
    }

    // 休憩終了時刻を記録
    $sql = "UPDATE timecards SET break_end_time = ? WHERE staff_id = ? AND date = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('sis', $break_end_time, $staff_id, $today);
    $stmt->execute();

    ApiResponse::success('休憩を終了しました', [
        'break_end_time' => $break_end_time
    ]);

} catch (Exception $e) {
    ApiResponse::error('エラーが発生しました: ' . $e->getMessage(), 500);
}
?>
