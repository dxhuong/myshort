<?php
// ===== CONFIG =====
// Phải khớp với BASE_URL và DB_PATH trong index.php
define('BASE_URL', 'http://localhost/rutgonlink');
define('DB_PATH', __DIR__ . '/db.sqlite');

// ===== DATABASE =====
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!file_exists(DB_PATH)) {
            // DB chưa khởi tạo — yêu cầu truy cập index.php trước
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA foreign_keys = ON");
    }
    return $pdo;
}

// ===== HELPERS =====
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function getClientIP(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $hdr) {
        if (!empty($_SERVER[$hdr])) {
            $ip = trim(explode(',', $_SERVER[$hdr])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

function renderPublicLayout(string $title, string $head, string $body): string {
    $titleH = h($title);
    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$titleH}</title>
  {$head}
  <script src="https://cdn.tailwindcss.com"></script>
  <style>body{background:#0d0d18}</style>
</head>
<body class="text-gray-100 min-h-screen">
  {$body}
</body>
</html>
HTML;
}

// ===== VIEWS =====

function render404(): string {
    $base = BASE_URL;
    $head = '';
    $body = <<<HTML
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="text-center">
    <p class="text-8xl font-black text-gray-800/80 mb-2 leading-none select-none">404</p>
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-red-600/15 border border-red-500/20 mb-6">
      <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <h1 class="text-2xl font-bold text-white mb-2">Link không tồn tại</h1>
    <p class="text-gray-500 text-sm mb-8">Link rút gọn này không tồn tại hoặc đã bị xóa.</p>
    <a href="{$base}/"
       class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-medium transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      Về trang chủ
    </a>
  </div>
</div>
HTML;
    return renderPublicLayout('Link không tồn tại — MyShort', $head, $body);
}

function renderExpired(array $link): string {
    $base    = BASE_URL;
    $isOff   = !$link['is_active'];
    $title   = $isOff ? 'Link đã bị tắt' : 'Link đã hết hạn';
    $msg     = $isOff
        ? 'Link này đã bị tắt bởi người quản trị.'
        : 'Link này đã hết hạn vào ' . ($link['expires_at'] ? date('d/m/Y H:i', strtotime($link['expires_at'])) : 'không xác định') . '.';
    $titleH  = h($title);
    $msgH    = h($msg);
    $head    = '';
    $body    = <<<HTML
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="text-center max-w-md">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-amber-600/15 border border-amber-500/20 mb-6">
      <svg class="w-10 h-10 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <h1 class="text-2xl font-bold text-white mb-2">{$titleH}</h1>
    <p class="text-gray-500 text-sm mb-8">{$msgH}</p>
    <a href="{$base}/"
       class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-medium transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      Về trang chủ
    </a>
  </div>
</div>
HTML;
    return renderPublicLayout($title . ' — MyShort', $head, $body);
}

function renderPasswordPage(array $link, string $error = ''): string {
    $base   = BASE_URL;
    $code   = h($link['short_code']);
    $errHtml = $error
        ? '<div class="flex items-center gap-2 bg-red-950/60 border border-red-800/60 text-red-300 px-4 py-3 rounded-xl text-sm mb-4">
             <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
             <span>' . h($error) . '</span></div>'
        : '';
    $head = '';
    $body = <<<HTML
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 mb-5">
        <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-white">Link được bảo vệ</h1>
      <p class="text-gray-500 text-sm mt-2">Nhập mật khẩu để tiếp tục đến <span class="text-indigo-400 font-mono">/{$code}</span></p>
    </div>
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-8 shadow-2xl">
      {$errHtml}
      <form method="POST" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Mật khẩu</label>
          <div class="relative">
            <input type="password" name="password" id="passInput" required autofocus
                   class="w-full px-4 py-3 pr-12 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition"
                   placeholder="••••••••">
            <button type="button" onclick="tp()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition p-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>
        <button type="submit"
                class="w-full py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg shadow-indigo-900/30">
          Tiếp tục
        </button>
      </form>
    </div>
    <p class="text-center text-gray-700 text-xs mt-6">
      Được rút gọn bởi <a href="{$base}/" class="text-gray-600 hover:text-gray-400">MyShort</a>
    </p>
  </div>
</div>
<script>function tp(){const p=document.getElementById('passInput');p.type=p.type==='password'?'text':'password';}</script>
HTML;
    return renderPublicLayout('Link được bảo vệ — MyShort', $head, $body);
}

function renderRedirect(array $link): string {
    $dest    = $link['original_url'];
    $destH   = h($dest);
    $destJs  = json_encode($dest);
    $title   = h($link['og_title'] ?: 'Đang chuyển hướng...');
    $desc    = h($link['og_description'] ?: 'Bạn sẽ được chuyển hướng ngay.');
    $image   = h($link['og_image_url'] ?: '');
    $pageUrl = h(BASE_URL . '/' . $link['short_code']);
    $ogImage = $image ? "<meta property=\"og:image\" content=\"{$image}\">\n  <meta name=\"twitter:image\" content=\"{$image}\">" : '';
    // Không dùng heredoc vì có biến phức tạp trong PHP thẻ HTML — dùng chuỗi concat
    return '<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . $title . '</title>

  <!-- Open Graph — để Facebook/Zalo crawler đọc trước khi redirect -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="' . $title . '">
  <meta property="og:description" content="' . $desc . '">
  <meta property="og:url" content="' . $pageUrl . '">
  ' . $ogImage . '
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="' . $title . '">
  <meta name="twitter:description" content="' . $desc . '">

  <!-- HTTP fallback redirect cho các trình thu thập không chạy JS -->
  <noscript><meta http-equiv="refresh" content="0;url=' . $destH . '"></noscript>

  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{background:#0d0d18;color:#e5e7eb;font-family:system-ui,sans-serif;
         min-height:100vh;display:flex;align-items:center;justify-content:center}
    .card{text-align:center;padding:2rem;max-width:400px}
    .spinner{width:40px;height:40px;border:3px solid #1f2937;border-top-color:#6366f1;
             border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 1.5rem}
    @keyframes spin{to{transform:rotate(360deg)}}
    p{color:#6b7280;font-size:.875rem;margin-top:.5rem}
    .url{color:#818cf8;font-size:.75rem;margin-top:.75rem;word-break:break-all;opacity:.7}
  </style>
</head>
<body>
  <div class="card">
    <div class="spinner"></div>
    <h1 style="font-size:1.1rem;font-weight:600;color:#f9fafb">Đang chuyển hướng...</h1>
    <p>Bạn sẽ được chuyển đến trang đích ngay bây giờ.</p>
    <p class="url">' . $destH . '</p>
  </div>
  <script>
    // Dùng replace() để không lưu trang trung gian vào lịch sử trình duyệt
    window.location.replace(' . $destJs . ');
  </script>
</body>
</html>';
}

// ===== MAIN LOGIC =====

$code = trim($_GET['code'] ?? '');

// Code rỗng → về trang chủ
if ($code === '') {
    header('Location: ' . BASE_URL . '/');
    exit;
}

// Tìm link trong database
$db   = getDB();
$stmt = $db->prepare("SELECT * FROM links WHERE short_code = ?");
$stmt->execute([$code]);
$link = $stmt->fetch();

// Link không tồn tại → 404
if (!$link) {
    http_response_code(404);
    echo render404();
    exit;
}

// Kiểm tra trạng thái active và hết hạn
$isExpired = $link['expires_at'] && strtotime($link['expires_at']) < time();
if (!$link['is_active'] || $isExpired) {
    echo renderExpired($link);
    exit;
}

// Kiểm tra mật khẩu
if (!empty($link['password'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submitted = $_POST['password'] ?? '';
        if (!password_verify($submitted, $link['password'])) {
            echo renderPasswordPage($link, 'Mật khẩu không đúng. Vui lòng thử lại.');
            exit;
        }
        // Mật khẩu đúng → tiếp tục bên dưới
    } else {
        echo renderPasswordPage($link);
        exit;
    }
}

// ===== GHI LOG CLICK =====
$ip        = getClientIP();
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
$referer   = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500);

// Kiểm tra unique click TRƯỚC khi ghi log
$uniqStmt = $db->prepare("
    SELECT COUNT(*) FROM clicks
    WHERE link_id = ? AND ip_address = ? AND clicked_at > datetime('now', '-24 hours')
");
$uniqStmt->execute([$link['id'], $ip]);
$isUnique = (int)$uniqStmt->fetchColumn() === 0;

// Ghi click vào bảng clicks
$db->prepare("INSERT INTO clicks (link_id, ip_address, user_agent, referer) VALUES (?, ?, ?, ?)")
   ->execute([$link['id'], $ip, $userAgent, $referer]);

// Cập nhật tổng click
$db->prepare("UPDATE links SET click_count = click_count + 1 WHERE id = ?")
   ->execute([$link['id']]);

// Cập nhật unique click nếu IP chưa click trong 24h
if ($isUnique) {
    $db->prepare("UPDATE links SET unique_clicks = unique_clicks + 1 WHERE id = ?")
       ->execute([$link['id']]);
}

// ===== RENDER REDIRECT PAGE =====
// Render HTML với OG meta tags và JS redirect
// OG crawler (Facebook/Zalo) đọc meta tags trước khi redirect xảy ra
echo renderRedirect($link);
