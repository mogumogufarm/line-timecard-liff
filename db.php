<?php
/**
 * データベース接続クラス
 */

require_once 'config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            die('Connection failed: ' . $this->conn->connect_error);
        }
        $this->conn->set_charset('utf8mb4');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql) {
        $result = $this->conn->query($sql);
        if (!$result) {
            throw new Exception('Query error: ' . $this->conn->error);
        }
        return $result;
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function escape_string($str) {
        return $this->conn->real_escape_string($str);
    }

    public function insert_id() {
        return $this->conn->insert_id;
    }

    public function affected_rows() {
        return $this->conn->affected_rows;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

/**
 * API 共通レスポンスクラス
 */
class ApiResponse {
    public static function json($success, $message = '', $data = null, $statusCode = 200) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);

        $response = [
            'success' => $success,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error($message, $statusCode = 400) {
        self::json(false, $message, null, $statusCode);
    }

    public static function success($message = '', $data = null) {
        self::json(true, $message, $data, 200);
    }
}

/**
 * 認証ユーティリティ
 */
class Auth {
    public static function requireLogin() {
        if (!isset($_SESSION['staff_id'])) {
            ApiResponse::error('Unauthorized', 401);
        }
        return $_SESSION['staff_id'];
    }

    public static function isLoggedIn() {
        return isset($_SESSION['staff_id']) && isset($_SESSION['line_user_id']);
    }

    public static function login($staff_id, $line_user_id) {
        $_SESSION['staff_id'] = $staff_id;
        $_SESSION['line_user_id'] = $line_user_id;
        $_SESSION['login_time'] = time();
    }

    public static function logout() {
        session_destroy();
    }

    public static function getStaffId() {
        return $_SESSION['staff_id'] ?? null;
    }

    public static function getLineUserId() {
        return $_SESSION['line_user_id'] ?? null;
    }
}

/**
 * 日時ユーティリティ
 */
class DateUtils {
    public static function now() {
        return date('Y-m-d H:i:s');
    }

    public static function today() {
        return date('Y-m-d');
    }

    public static function getCurrentTime() {
        return date('H:i:s');
    }

    public static function getTime($timestamp) {
        return date('H:i:s', strtotime($timestamp));
    }

    public static function calculateWorkingHours($clockInTime, $clockOutTime, $breakStartTime = null, $breakEndTime = null) {
        $clockInSeconds = strtotime($clockInTime);
        $clockOutSeconds = strtotime($clockOutTime);
        $totalSeconds = $clockOutSeconds - $clockInSeconds;

        if ($breakStartTime && $breakEndTime) {
            $breakStartSeconds = strtotime($breakStartTime);
            $breakEndSeconds = strtotime($breakEndTime);
            $breakSeconds = $breakEndSeconds - $breakStartSeconds;
            $totalSeconds -= $breakSeconds;
        }

        return round($totalSeconds / 3600, 2); // return hours
    }
}
?>
