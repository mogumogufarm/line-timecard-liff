/**
 * LINE 打刻システム - メインアプリケーションロジック
 */

// グローバル変数
let liff;
let idToken = null;
let refreshInterval = null;

// 初期化
document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);

    if (isLoggedIn) {
        // ログイン済みの場合、データを読み込む
        refreshTodayStatus();
        refreshPayrollInfo();
        refreshHistory();

        // 1分ごとにステータスを更新
        refreshInterval = setInterval(() => {
            refreshTodayStatus();
        }, 60000);
    } else {
        // LIFF を初期化
        initLiff();
    }

    // メニュー閉じる
    document.addEventListener('click', function(e) {
        const menu = document.getElementById('headerMenu');
        const menuBtn = document.querySelector('.header-menu');
        if (menu && !menu.contains(e.target) && !menuBtn.contains(e.target)) {
            menu.classList.remove('active');
        }
    });
});

/**
 * LIFF 初期化
 */
function initLiff() {
    liff = window.liff;

    liff.init({
        liffId: 'YOUR_LIFF_ID' // 本番環境では環境変数から設定
    }).then(() => {
        if (!liff.isLoggedIn()) {
            liff.login();
        }
    }).catch(err => {
        console.error('LIFF init failed:', err);
        showError('LIFF の初期化に失敗しました');
    });
}

/**
 * 日時を更新
 */
function updateDateTime() {
    const now = new Date();
    const dateStr = now.toLocaleDateString('ja-JP', {
        month: '2-digit',
        day: '2-digit'
    });
    const timeStr = now.toLocaleTimeString('ja-JP', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });

    const dateEl = document.getElementById('currentDate');
    const timeEl = document.getElementById('currentTime');

    if (dateEl) dateEl.textContent = dateStr;
    if (timeEl) timeEl.textContent = timeStr;
}

/**
 * 出勤ボタン
 */
async function handleClockIn() {
    if (!confirm('出勤しますか？')) return;

    try {
        const { latitude, longitude } = await getGPS();

        const response = await fetch('api/clock_in.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ latitude, longitude })
        });

        const data = await response.json();
        if (data.success) {
            showSuccess(`出勤しました: ${data.data.clock_in_time}`);
            refreshTodayStatus();
        } else {
            showError(data.message);
        }
    } catch (err) {
        console.error('Clock in error:', err);
        showError('出勤処理でエラーが発生しました');
    }
}

/**
 * 休憩ボタン
 */
async function handleBreak() {
    try {
        // 現在の状態を取得
        const response = await fetch('api/get_today_status.php');
        const data = await response.json();

        if (!data.success) {
            showError(data.message);
            return;
        }

        const status = data.data;

        if (status.is_on_break) {
            // 休憩終了
            if (!confirm('休憩を終了しますか？')) return;

            const response = await fetch('api/break_end.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            });

            const data = await response.json();
            if (data.success) {
                showSuccess(`休憩を終了しました: ${data.data.break_end_time}`);
            } else {
                showError(data.message);
            }
        } else if (status.is_clocked_in && !status.clock_out_time) {
            // 休憩開始
            if (!confirm('休憩を開始しますか？')) return;

            const response = await fetch('api/break_start.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({})
            });

            const data = await response.json();
            if (data.success) {
                showSuccess(`休憩を開始しました: ${data.data.break_start_time}`);
            } else {
                showError(data.message);
            }
        } else {
            showError('出勤状態を確認してください');
        }

        refreshTodayStatus();
    } catch (err) {
        console.error('Break handling error:', err);
        showError('休憩処理でエラーが発生しました');
    }
}

/**
 * 退勤ボタン
 */
async function handleClockOut() {
    if (!confirm('退勤しますか？')) return;

    try {
        const { latitude, longitude } = await getGPS();

        const response = await fetch('api/clock_out.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ latitude, longitude })
        });

        const data = await response.json();
        if (data.success) {
            showSuccess(`退勤しました: ${data.data.clock_out_time}`);
            refreshTodayStatus();
            refreshPayrollInfo();
        } else {
            showError(data.message);
        }
    } catch (err) {
        console.error('Clock out error:', err);
        showError('退勤処理でエラーが発生しました');
    }
}

/**
 * GPS位置情報を取得
 */
async function getGPS() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('GPS がサポートされていません'));
            return;
        }

        navigator.geolocation.getCurrentPosition(
            position => {
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                });
            },
            error => {
                console.warn('GPS 取得に失敗: ' + error.message);
                // GPS が取得できない場合は、null で継続
                resolve({ latitude: null, longitude: null });
            }
        );
    });
}

/**
 * 本日のステータスを更新
 */
async function refreshTodayStatus() {
    try {
        const response = await fetch('api/get_today_status.php');
        const data = await response.json();

        if (!data.success) {
            showError(data.message);
            return;
        }

        const status = data.data;
        const statusEl = document.getElementById('today-status');

        let html = '<div class="status-panel">';

        if (status.is_clocked_in) {
            html += `
                <div class="status-item">
                    <span class="status-label">出勤時刻</span>
                    <span class="status-value">${status.clock_in_time}</span>
                </div>
            `;

            if (status.clock_out_time) {
                html += `
                    <div class="status-item">
                        <span class="status-label">退勤時刻</span>
                        <span class="status-value">${status.clock_out_time}</span>
                    </div>
                `;
            }

            if (status.break_start_time) {
                html += `
                    <div class="status-item">
                        <span class="status-label">休憩時間</span>
                        <span class="status-value">${status.break_start_time}〜${status.break_end_time || '中'}</span>
                    </div>
                `;
            }

            html += `
                <div class="status-item">
                    <span class="status-label">実働時間</span>
                    <span class="status-value highlight">${status.working_hours.toFixed(2)} 時間</span>
                </div>
            `;
        } else {
            html += '<p style="text-align: center; color: #7A7256;">未出勤</p>';
        }

        html += '</div>';
        statusEl.innerHTML = html;

        // ボタン状態を更新
        updateButtonStates(status);
    } catch (err) {
        console.error('Refresh status error:', err);
    }
}

/**
 * ボタンの状態を更新
 */
function updateButtonStates(status) {
    const clockInBtn = document.getElementById('clockInBtn');
    const breakBtn = document.getElementById('breakBtn');
    const clockOutBtn = document.getElementById('clockOutBtn');

    if (status.clock_out_time) {
        // 退勤済み
        clockInBtn.disabled = true;
        breakBtn.disabled = true;
        clockOutBtn.disabled = true;
    } else if (status.is_clocked_in) {
        // 出勤済み
        clockInBtn.disabled = true;
        breakBtn.disabled = false;
        clockOutBtn.disabled = false;
    } else {
        // 未出勤
        clockInBtn.disabled = false;
        breakBtn.disabled = true;
        clockOutBtn.disabled = true;
    }
}

/**
 * 給与情報を更新
 */
async function refreshPayrollInfo() {
    try {
        const response = await fetch('api/get_today_status.php');
        const data = await response.json();

        if (!data.success) return;

        const status = data.data;
        const salaryEl = document.getElementById('today-salary');

        let html = `
            <div class="salary-panel">
                <div class="salary-display">
                    <p style="margin: 0; font-size: 12px; color: #7A7256;">本日の給与見込み</p>
                    <div class="salary-amount">¥${status.expected_salary.toLocaleString()}</div>
                    <div class="salary-details">
                        <div class="salary-detail-item">
                            <div class="salary-detail-label">実働時間</div>
                            <div class="salary-detail-value">${status.working_hours.toFixed(2)}h</div>
                        </div>
                        <div class="salary-detail-item">
                            <div class="salary-detail-label">時給</div>
                            <div class="salary-detail-value">¥${status.hourly_rate}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        salaryEl.innerHTML = html;
    } catch (err) {
        console.error('Refresh payroll error:', err);
    }
}

/**
 * 出勤履歴を更新
 */
async function refreshHistory() {
    try {
        const response = await fetch('api/get_history.php');
        const data = await response.json();

        if (!data.success) return;

        const history = data.data.history;
        const historyEl = document.getElementById('history-table');

        if (history.length === 0) {
            historyEl.innerHTML = '<p style="text-align: center; color: #7A7256;">記録がありません</p>';
            return;
        }

        let html = `
            <table class="history-table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>出勤</th>
                        <th>退勤</th>
                        <th>時間</th>
                    </tr>
                </thead>
                <tbody>
        `;

        history.forEach(record => {
            html += `
                <tr>
                    <td>${record.date}</td>
                    <td>${record.clock_in || '-'}</td>
                    <td>${record.clock_out || '-'}</td>
                    <td>${record.working_hours ? record.working_hours.toFixed(2) + 'h' : '-'}</td>
                </tr>
            `;
        });

        html += `
                </tbody>
            </table>
        `;

        historyEl.innerHTML = html;
    } catch (err) {
        console.error('Refresh history error:', err);
    }
}

/**
 * LINE ログイン処理
 */
async function handleLineLogin() {
    try {
        if (!liff) {
            initLiff();
            return;
        }

        if (!liff.isLoggedIn()) {
            liff.login();
            return;
        }

        const idToken = liff.getIDToken();

        const response = await fetch('api/line_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_token: idToken })
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('ログインしました');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showError(data.message);
        }
    } catch (err) {
        console.error('Login error:', err);
        showError('ログイン処理でエラーが発生しました');
    }
}

/**
 * ログアウト処理
 */
async function handleLogout() {
    if (!confirm('ログアウトしますか？')) return;

    try {
        // セッションをクリア（PHPで処理）
        const response = await fetch('api/logout.php', {
            method: 'POST'
        });

        if (response.ok) {
            window.location.href = 'index.liff.php';
        }
    } catch (err) {
        console.error('Logout error:', err);
        window.location.href = 'index.liff.php';
    }
}

/**
 * メニュー表示切り替え
 */
function toggleMenu() {
    const menu = document.getElementById('headerMenu');
    if (menu) {
        menu.classList.toggle('active');
    }
}

/**
 * 成功メッセージ表示
 */
function showSuccess(message) {
    showAlert(message, 'success');
}

/**
 * エラーメッセージ表示
 */
function showError(message) {
    showAlert(message, 'error');
}

/**
 * アラート表示
 */
function showAlert(message, type = 'info') {
    // シンプルな実装：ブラウザの alert を使用
    // 本番環境では、より洗練されたUI コンポーネントを使用することを推奨
    if (type === 'error') {
        alert('❌ ' + message);
    } else if (type === 'success') {
        alert('✅ ' + message);
    } else {
        alert(message);
    }
}

// ページアンロード時にインターバルをクリア
window.addEventListener('beforeunload', function() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
