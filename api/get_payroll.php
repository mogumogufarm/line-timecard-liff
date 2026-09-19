<?php
/**
 * 給与情報取得 API
 * 指定月の給与情報と日別勤務記録を返す
 */

require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 認証チェック
    $staff_id = Auth::requireLogin();

    // リクエスト処理
    $data = json_decode(file_get_contents('php://input'), true);
    $year_month = $data['year_month'] ?? date('Y-m'); // デフォルトは当月

    // フォーマット確認 (YYYY-MM)
    if (!preg_match('/^\d{4}-\d{2}$/', $year_month)) {
        ApiResponse::error('年月フォーマットが無効です (YYYY-MM)', 400);
    }

    $db = Database::getInstance();

    // 給与記録を取得
    $sql = "SELECT working_hours, transport_fee, gross_salary, status, created_at
            FROM payroll_records
            WHERE staff_id = ? AND year_month = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $staff_id, $year_month);
    $stmt->execute();
    $result = $stmt->get_result();
    $payrollRow = $result->fetch_assoc();

    $working_hours = 0;
    $transport_fee = 0;
    $gross_salary = 0;

    if ($payrollRow) {
        $working_hours = $payrollRow['working_hours'];
        $transport_fee = $payrollRow['transport_fee'];
        $gross_salary = $payrollRow['gross_salary'];
    }

    // 日別の勤務記録を取得
    $sql = "SELECT DATE_FORMAT(date, '%Y-%m-%d') as date, clock_in_time, clock_out_time,
            break_start_time, break_end_time
            FROM timecards
            WHERE staff_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
            ORDER BY date ASC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $staff_id, $year_month);
    $stmt->execute();
    $result = $stmt->get_result();

    $daily_records = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['clock_in_time'] && $row['clock_out_time']) {
            $workingHours = DateUtils::calculateWorkingHours(
                $row['clock_in_time'],
                $row['clock_out_time'],
                $row['break_start_time'],
                $row['break_end_time']
            );

            $daily_records[] = [
                'date' => $row['date'],
                'clock_in' => $row['clock_in_time'],
                'clock_out' => $row['clock_out_time'],
                'working_hours' => $workingHours
            ];
        }
    }

    ApiResponse::success('', [
        'year_month' => $year_month,
        'working_hours' => $working_hours,
        'transport_fee' => $transport_fee,
        'gross_salary' => $gross_salary,
        'daily_records' => $daily_records
    ]);

} catch (Exception $e) {
    ApiResponse::error('エラーが発生しました: ' . $e->getMessage(), 500);
}
?>
