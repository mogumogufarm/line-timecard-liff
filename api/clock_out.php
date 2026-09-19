<?php
/**
 * 退勤 API
 * 退勤時刻、GPS座標を記録し、給与を自動計算・登録
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
    $today = DateUtils::today();
    $clock_out_time = DateUtils::getCurrentTime();

    // 本日のレコードを確認
    $sql = "SELECT id, clock_in_time, break_start_time, break_end_time FROM timecards
            WHERE staff_id = ? AND date = ? AND clock_in_time IS NOT NULL";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $staff_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        ApiResponse::error('出勤記録が見つかりません', 400);
    }

    // 退勤時刻と GPS 座標を更新
    $sql = "UPDATE timecards SET clock_out_time = ?, latitude = COALESCE(?, latitude),
            longitude = COALESCE(?, longitude)
            WHERE staff_id = ? AND date = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('sddis', $clock_out_time, $latitude, $longitude, $staff_id, $today);
    $stmt->execute();

    // 給与を計算
    $clock_in_str = $today . ' ' . $row['clock_in_time'];
    $clock_out_str = $today . ' ' . $clock_out_time;
    $clockInSeconds = strtotime($clock_in_str);
    $clockOutSeconds = strtotime($clock_out_str);
    $totalSeconds = $clockOutSeconds - $clockInSeconds;

    // 休憩時間を差し引く
    $breakSeconds = 0;
    if ($row['break_start_time'] && $row['break_end_time']) {
        $breakStartStr = $today . ' ' . $row['break_start_time'];
        $breakEndStr = $today . ' ' . $row['break_end_time'];
        $breakStartSeconds = strtotime($breakStartStr);
        $breakEndSeconds = strtotime($breakEndStr);
        $breakSeconds = $breakEndSeconds - $breakStartSeconds;
    }

    $workingSeconds = $totalSeconds - $breakSeconds;
    $workingHours = floor($workingSeconds / 3600 * 100) / 100; // 四捨五入なし、切り捨て

    // スタッフの時給と交通費を取得
    $sql = "SELECT hourly_rate, transport_fee_per_day FROM staff WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staffRow = $result->fetch_assoc();

    if (!$staffRow) {
        ApiResponse::error('スタッフ情報が見つかりません', 400);
    }

    $hourly_rate = $staffRow['hourly_rate'] ?? 1200;
    $transport_fee = $staffRow['transport_fee_per_day'] ?? 200;
    $gross_salary = floor($workingHours * $hourly_rate) + $transport_fee;

    // 給与記録を登録または更新
    $year_month = date('Y-m', strtotime($today));
    $sql = "INSERT INTO payroll_records (staff_id, year_month, working_hours, transport_fee, gross_salary)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            working_hours = working_hours + ?,
            gross_salary = (SELECT SUM(gross_salary) FROM payroll_records WHERE staff_id = ? AND year_month = ?)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('isidiii', $staff_id, $year_month, $workingHours, $transport_fee, $gross_salary, $staff_id, $year_month);
    $stmt->execute();

    ApiResponse::success('退勤しました', [
        'clock_out_time' => $clock_out_time,
        'working_hours' => $workingHours,
        'gross_salary' => $gross_salary,
        'message' => '退勤時刻を記録しました'
    ]);

} catch (Exception $e) {
    ApiResponse::error('エラーが発生しました: ' . $e->getMessage(), 500);
}
?>
