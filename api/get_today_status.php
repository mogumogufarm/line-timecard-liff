<?php
/**
 * 本日の勤務状態取得 API
 * 本日の出勤状態・実働時間・給与見込みを返す
 */

require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 認証チェック
    $staff_id = Auth::requireLogin();

    $db = Database::getInstance();
    $today = DateUtils::today();

    // 本日のタイムカード取得
    $sql = "SELECT id, clock_in_time, clock_out_time, break_start_time, break_end_time
            FROM timecards
            WHERE staff_id = ? AND date = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $staff_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $timecard = $result->fetch_assoc();

    // スタッフ情報取得（時給と交通費）
    $sql = "SELECT hourly_rate, transport_fee_per_day FROM staff WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staffInfo = $result->fetch_assoc();

    $status_data = [
        'is_clocked_in' => false,
        'is_on_break' => false,
        'clock_in_time' => null,
        'clock_out_time' => null,
        'break_start_time' => null,
        'break_end_time' => null,
        'working_hours' => 0,
        'expected_salary' => 0,
        'hourly_rate' => $staffInfo['hourly_rate'] ?? 1200,
        'transport_fee' => $staffInfo['transport_fee_per_day'] ?? 200
    ];

    if ($timecard) {
        if ($timecard['clock_in_time']) {
            $status_data['is_clocked_in'] = true;
            $status_data['clock_in_time'] = $timecard['clock_in_time'];
        }

        if ($timecard['clock_out_time']) {
            $status_data['clock_out_time'] = $timecard['clock_out_time'];
        }

        if ($timecard['break_start_time']) {
            $status_data['break_start_time'] = $timecard['break_start_time'];
        }

        if ($timecard['break_end_time']) {
            $status_data['break_end_time'] = $timecard['break_end_time'];
        }

        // 実働時間を計算
        if ($timecard['clock_in_time'] && $timecard['clock_out_time']) {
            $working_hours = DateUtils::calculateWorkingHours(
                $timecard['clock_in_time'],
                $timecard['clock_out_time'],
                $timecard['break_start_time'],
                $timecard['break_end_time']
            );
            $status_data['working_hours'] = $working_hours;

            // 給与見込みを計算
            $expected_salary = floor($working_hours * $status_data['hourly_rate']) + $status_data['transport_fee'];
            $status_data['expected_salary'] = $expected_salary;
        } elseif ($timecard['clock_in_time']) {
            // 出勤済みだが退勤していない場合、現在までの推定時間を計算
            $current_time = DateUtils::getCurrentTime();
            $working_hours = DateUtils::calculateWorkingHours(
                $timecard['clock_in_time'],
                $current_time,
                $timecard['break_start_time'],
                $timecard['break_end_time']
            );
            $status_data['working_hours'] = round($working_hours, 2);

            // 給与見込みを計算（推定）
            $expected_salary = floor($working_hours * $status_data['hourly_rate']) + $status_data['transport_fee'];
            $status_data['expected_salary'] = $expected_salary;

            $status_data['is_on_break'] = ($timecard['break_start_time'] && !$timecard['break_end_time']);
        }
    }

    ApiResponse::success('', $status_data);

} catch (Exception $e) {
    ApiResponse::error('エラーが発生しました: ' . $e->getMessage(), 500);
}
?>
