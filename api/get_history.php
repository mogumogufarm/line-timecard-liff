<?php
/**
 * 出勤履歴取得 API
 * 過去 5 日間の打刻記録を返す
 */

require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 認証チェック
    $staff_id = Auth::requireLogin();

    $db = Database::getInstance();
    $today = DateUtils::today();

    // 過去 5 日間のレコード取得
    $sql = "SELECT DATE_FORMAT(date, '%Y-%m-%d') as date, clock_in_time, clock_out_time,
            break_start_time, break_end_time
            FROM timecards
            WHERE staff_id = ? AND date >= DATE_SUB(?, INTERVAL 4 DAY)
            ORDER BY date DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $staff_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $record = [
            'date' => $row['date'],
            'clock_in' => $row['clock_in_time'],
            'clock_out' => $row['clock_out_time'],
            'working_hours' => 0
        ];

        if ($row['clock_in_time'] && $row['clock_out_time']) {
            $working_hours = DateUtils::calculateWorkingHours(
                $row['clock_in_time'],
                $row['clock_out_time'],
                $row['break_start_time'],
                $row['break_end_time']
            );
            $record['working_hours'] = $working_hours;
        }

        $history[] = $record;
    }

    ApiResponse::success('', [
        'history' => $history,
        'count' => count($history)
    ]);

} catch (Exception $e) {
    ApiResponse::error('エラーが発生しました: ' . $e->getMessage(), 500);
}
?>
