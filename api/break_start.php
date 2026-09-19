<?php
/**
 * 休憩開始 API
 */

require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 認証チェック
    $staff_id = Auth::requireLogin();

    $db = Database::getInstance();
    $today = DateUtils::today();
    $break_start_time = DateUtils::getCurrentTime();

    // 本日のレコードを確認
    $sql = "SELECT id FROM timecards WHERE staff_id = ? AND date = ? AND clock_in_time IS NOT NULL";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $staff_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        ApiResponse::error('出勤記録が見つかりません', 400);
    }

    // 休憩開始時刻を記録
    $sql = "UPDATE timecards SET break_start_time = ? WHERE staff_id = ? AND date = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('sis', $break_start_time, $staff_id, $today);
    $stmt->execute();

    ApiResponse::success('休憩を開始しました', [
        'break_start_time' => $break_start_time
    ]);

} catch (Exception $e) {
    ApiResponse::error('エラーが発生しました: ' . $e->getMessage(), 500);
}
?>
