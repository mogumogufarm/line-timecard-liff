<?php
/**
 * LINE ログイン API
 * LIFF から ID Token を受け取り、スタッフ情報と照合してセッション開始
 */

require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // リクエスト処理
    $data = json_decode(file_get_contents('php://input'), true);
    $id_token = $data['id_token'] ?? null;

    if (!$id_token) {
        ApiResponse::error('ID token is required', 400);
    }

    // ID Token を検証 (簡易版)
    // 実装環境では LINE API で検証を行うことを推奨
    // https://developers.line.biz/en/reference/line-login/

    // トークンをデコード（署名検証は省略、本番環境では必須）
    $parts = explode('.', $id_token);
    if (count($parts) !== 3) {
        ApiResponse::error('Invalid token format', 400);
    }

    $payload = json_decode(base64_decode($parts[1]), true);
    if (!$payload) {
        ApiResponse::error('Invalid token payload', 400);
    }

    $line_user_id = $payload['sub'] ?? null;
    if (!$line_user_id) {
        ApiResponse::error('Invalid token: sub not found', 400);
    }

    // スタッフ照合
    $db = Database::getInstance();
    $sql = "SELECT id, name FROM staff WHERE line_user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $line_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staffRow = $result->fetch_assoc();

    if (!$staffRow) {
        ApiResponse::error('Staff not found', 404);
    }

    // セッション開始
    Auth::login($staffRow['id'], $line_user_id);

    ApiResponse::success('ログインしました', [
        'staff_id' => $staffRow['id'],
        'name' => $staffRow['name']
    ]);

} catch (Exception $e) {
    ApiResponse::error('エラーが発生しました: ' . $e->getMessage(), 500);
}
?>
