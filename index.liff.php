<?php
/**
 * LINE 打刻システム - メイン LIFF 画面
 */

require_once 'db.php';

// ログイン状態の確認
$is_logged_in = Auth::isLoggedIn();
$staff_id = Auth::getStaffId();
$line_user_id = Auth::getLineUserId();

// ログイン済みの場合、スタッフ情報を取得
$staff_name = '';
if ($is_logged_in && $staff_id) {
    $db = Database::getInstance();
    $sql = "SELECT name FROM staff WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staffRow = $result->fetch_assoc();
    if ($staffRow) {
        $staff_name = $staffRow['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>勤怠打刻システム</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <link rel="stylesheet" href="common.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="common.js"></script>
</head>
<body>
    <div class="liff-screen">
        <?php if (!$is_logged_in): ?>
            <!-- ログイン画面 -->
            <div class="liff-login">
                <div class="liff-header">
                    <h1 class="liff-title">勤怠打刻システム</h1>
                    <p class="liff-subtitle">LINE でログイン</p>
                </div>
                <div class="liff-content">
                    <div class="card">
                        <p class="login-description">LINE アカウントでログインしてください</p>
                        <button class="btn btn-primary" onclick="handleLineLogin()">
                            LINE でログイン
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- メイン画面 -->
            <div class="liff-header">
                <div class="header-top">
                    <h2 class="header-name"><?php echo htmlspecialchars($staff_name); ?> さん</h2>
                    <button class="header-menu" onclick="toggleMenu()">⋮</button>
                </div>
                <p class="header-date" id="currentDate"></p>
                <p class="header-time" id="currentTime"></p>
            </div>

            <div class="liff-content">
                <!-- 打刻ボタングループ -->
                <section class="button-group">
                    <button class="btn btn-primary" id="clockInBtn" onclick="handleClockIn()">出勤</button>
                    <button class="btn btn-secondary" id="breakBtn" onclick="handleBreak()">休憩</button>
                    <button class="btn btn-danger" id="clockOutBtn" onclick="handleClockOut()">退勤</button>
                </section>

                <!-- 本日の勤務状態 -->
                <section class="section">
                    <div class="section-title">本日の勤務</div>
                    <div id="today-status" class="status-panel">
                        <p class="loading">読み込み中...</p>
                    </div>
                </section>

                <!-- 本日の給与見込み -->
                <section class="section">
                    <div class="section-title">本日の給与見込み</div>
                    <div id="today-salary" class="salary-panel">
                        <p class="loading">読み込み中...</p>
                    </div>
                </section>

                <!-- 出勤履歴 -->
                <section class="section">
                    <div class="section-title">出勤履歴（過去5日）</div>
                    <div id="history-table" class="history-panel">
                        <p class="loading">読み込み中...</p>
                    </div>
                </section>

                <!-- 給与情報 -->
                <section class="section">
                    <div class="section-title">給与情報</div>
                    <div id="payroll-info" class="payroll-panel">
                        <p class="loading">読み込み中...</p>
                    </div>
                </section>
            </div>

            <!-- メニュー -->
            <div id="headerMenu" class="header-menu-dropdown">
                <button onclick="handleLogout()" class="menu-item">ログアウト</button>
            </div>
        <?php endif; ?>
    </div>

    <script src="js/app.js"></script>
    <script>
        // グローバル変数
        const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        const staffId = <?php echo $staff_id ?: 'null'; ?>;
    </script>
</body>
</html>
