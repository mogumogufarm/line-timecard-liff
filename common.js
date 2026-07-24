// 全ページ共通の通信・処理中ポップアップ関数
// 呼び出し元のページで、先に GAS_URL / currentIdToken を定義しておくこと

function callServer(payload) {
  payload.idToken = currentIdToken;
  return fetch(GAS_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'text/plain;charset=utf-8' },
    body: JSON.stringify(payload)
  }).then(function (res) { return res.json(); });
}

function withTimeout(promise, ms, label) {
  return Promise.race([
    promise,
    new Promise(function (_, reject) { setTimeout(function () { reject(new Error((label || 'TIMEOUT') + ':' + ms + 'ms')); }, ms); })
  ]);
}

function showProcessing(text) {
  document.getElementById('processingText').textContent = text;
  document.getElementById('processingOverlay').style.display = 'flex';
}
function hideProcessing() {
  document.getElementById('processingOverlay').style.display = 'none';
}
