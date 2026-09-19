<?php
/**
 * 出勤 API
 * 出勤時刻と GPS 座標を記録
 */

require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 認証チェック
    $staff_id = Auth::requireLogin();

    // リクエスト処理
    $data = json_decode(file_get_contents('php://input'), true);
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // 今日のレコードを確認
    $today = DateUtils::today();
    $sql = "SELECT id, clock_in_time FROM timecards WHERE staff_id = ? AND date = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $staff_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        // 既に出勤済み
        ApiResponse::error('既に出勤しています', 400);
    }

    // 出勤時刻を記録
    $clock_in_time = DateUtils::getCurrentTime();
    $sql = "INSERT INTO timecards (staff_id, date, clock_in_time, latitude, longitude)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('issdd', $staff_id, $today, $clock_in_time, $latitude, $longitude);
    $stmt->execute();

    ApiResponse::success('出勤しました', [
        'clock_in_time' => $clock_in_time,
        'message' => '出勤時刻を記録しました'
    ]);

} catch (Exception $e) {
    ApiResponse::error('エラーが発生しました: ' . $e->getMessage(), 500);
}
?>
