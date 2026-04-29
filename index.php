<?php
// ===== CONFIG =====
define('BASE_URL', 'http://localhost/rutgonlink'); // Thay bằng domain thực tế, không có dấu /
define('SCRIPT_PATH', '/rutgonlink');              // Thay bằng '' nếu chạy ở root domain
define('ADMIN_USER', 'admin');
// Mật khẩu mặc định: admin123 — Chạy lệnh sau để tạo hash mới:
// php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
define('ADMIN_PASS', '$2y$10$MSStaOd927it4LbJphbpJeXUV8vVcfCyCYVyuTIfy.ZKTjntBS8g2');
define('DB_PATH', __DIR__ . '/db.sqlite');
define('RATE_LIMIT', 10);
define('REMEMBER_DAYS', 30);        // Thời gian lưu đăng nhập (ngày)
define('REMEMBER_COOKIE', 'myshort_rm'); // Tên cookie

// ===== SESSION & INIT =====
session_start();

// ===== REMEMBER ME =====

function setRememberToken(): void {
    $token     = bin2hex(random_bytes(32)); // 64 ký tự hex, an toàn mật mã
    $tokenHash = hash('sha256', $token);
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);
    $ip        = getClientIP();
    $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    getDB()->prepare("
        INSERT INTO remember_tokens (token_hash, expires_at, ip_address, user_agent)
        VALUES (?, ?, ?, ?)
    ")->execute([$tokenHash, $expires, $ip, $ua]);

    $secure   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookieOpts = [
        'expires'  => time() + REMEMBER_DAYS * 86400,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie(REMEMBER_COOKIE, $token, $cookieOpts);
}

function clearRememberToken(): void {
    $token = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($token !== '') {
        $hash = hash('sha256', $token);
        try {
            getDB()->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")
                   ->execute([$hash]);
        } catch (\Throwable) {}
        // Xóa cookie bằng cách set thời gian hết hạn trong quá khứ
        setcookie(REMEMBER_COOKIE, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function checkRememberToken(): void {
    if (!empty($_SESSION['admin'])) return; // Đã đăng nhập rồi
    $token = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($token === '') return;

    $hash = hash('sha256', $token);
    try {
        $stmt = getDB()->prepare("
            SELECT id FROM remember_tokens
            WHERE token_hash = ? AND expires_at > datetime('now')
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if ($row) {
            // Token hợp lệ → tự đăng nhập, xoay token mới (token rotation)
            getDB()->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")
                   ->execute([$hash]);
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            setRememberToken(); // Cấp token mới
        } else {
            // Token không hợp lệ hoặc hết hạn → xóa cookie
            setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }
    } catch (\Throwable) {}
}

// Kiểm tra cookie remember ngay sau session_start
checkRememberToken();

// ===== LANGUAGE SYSTEM =====
function getLang(): string {
    return $_SESSION['lang'] ?? 'en';
}

function t(string $key, mixed ...$args): string {
    static $cache = null;
    if ($cache === null) $cache = getTranslations();
    $lang = getLang();
    $str  = $cache[$lang][$key] ?? $cache['en'][$key] ?? $key;
    return $args ? vsprintf($str, $args) : $str;
}

function getTranslations(): array {
    return [
        // ===== ENGLISH =====
        'en' => [
            'lang.name'                  => 'English',
            // Nav
            'nav.create'                 => 'Create Link',
            'nav.dashboard'              => 'Dashboard',
            'nav.analytics'              => 'Analytics',
            'nav.settings'               => 'Settings',
            'nav.about'                  => 'About',
            'nav.logout'                 => 'Logout',
            // Login
            'login.title'                => 'MyShort',
            'login.subtitle'             => 'Sign in to manage your short links',
            'login.username'             => 'Username',
            'login.password'             => 'Password',
            'login.submit'               => 'Sign In',
            'login.remember'             => 'Remember me for %s days',
            'login.error'                => 'Invalid username or password.',
            // Home
            'home.title'                 => 'Create Short Link',
            'home.subtitle'              => 'Shorten long URLs, add password protection, track statistics',
            'home.tab.basic'             => 'Basic',
            'home.tab.advanced'          => 'Advanced (OG / Expiry)',
            'home.original_url'          => 'Original URL',
            'home.original_url.ph'       => 'https://example.com/your-very-long-url-here',
            'home.custom_slug'           => 'Custom Slug',
            'home.custom_slug.opt'       => '(optional)',
            'home.custom_slug.ph'        => 'my-link',
            'home.custom_slug.hint'      => 'Leave blank to auto-generate 6 random characters',
            'home.password'              => 'Password Protection',
            'home.password.opt'          => '(optional)',
            'home.password.ph'           => 'Leave blank if not needed',
            'home.og_title'              => 'OG Title',
            'home.og_title.ph'           => 'Title shown when sharing on Facebook / Zalo',
            'home.og_desc'               => 'OG Description',
            'home.og_desc.ph'            => 'Short description shown when sharing',
            'home.og_image'              => 'OG Image URL',
            'home.og_image.ph'           => 'https://example.com/thumbnail.jpg',
            'home.expires_at'            => 'Expiry Date',
            'home.submit'                => 'Create Short Link',
            'home.creating'              => 'Creating...',
            'home.success'               => 'Link created successfully!',
            'home.short_link'            => 'Short Link',
            'home.download_qr'           => 'Download QR',
            'home.share'                 => 'Share',
            'home.create_new'            => '+ Create New Link',
            'home.placeholder'           => 'Your short link and QR code will appear here',
            'home.err.invalid_url'       => 'Invalid URL. Please enter a valid https://... address.',
            'home.err.slug_chars'        => 'Slug may only contain letters, numbers, - and _',
            'home.err.slug_exists'       => 'Slug "%s" already exists. Please choose another.',
            'home.err.rate_limit'        => 'Rate limit exceeded (10 links/hour). Please try again later.',
            'home.err.qr_fail'           => 'Failed to download QR code',
            'home.err.generic'           => 'An error occurred, please try again',
            // Dashboard
            'dash.title'                 => 'Dashboard',
            'dash.subtitle'              => 'Total %s links',
            'dash.create_new'            => 'Create New Link',
            'dash.search.ph'             => 'Search by slug or URL...',
            'dash.col.slug'              => 'Slug',
            'dash.col.url'               => 'Original URL',
            'dash.col.clicks'            => 'Clicks',
            'dash.col.unique'            => 'Unique',
            'dash.col.created'           => 'Created',
            'dash.col.status'            => 'Status',
            'dash.col.actions'           => 'Actions',
            'dash.status.active'         => 'Active',
            'dash.status.expired'        => 'Expired',
            'dash.status.off'            => 'Off',
            'dash.empty'                 => 'No links found',
            'dash.qr.title'              => 'QR Code',
            'dash.qr.download'           => 'Download',
            'dash.qr.close'              => 'Close',
            'dash.delete.confirm'        => 'Delete this link? This action cannot be undone.',
            // Stats
            'stats.title'                => 'Stats',
            'stats.total'                => 'Total Clicks',
            'stats.unique'               => 'Unique Clicks',
            'stats.today'                => 'Today',
            'stats.week'                 => 'Last 7 Days',
            'stats.chart.title'          => 'Click Chart — Last 7 Days',
            'stats.referers.title'       => 'Top 10 Referrers',
            'stats.recent.title'         => 'Last 20 Clicks',
            'stats.col.source'           => 'Source',
            'stats.col.clicks'           => 'Clicks',
            'stats.col.time'             => 'Time',
            'stats.col.ip'               => 'IP',
            'stats.col.referer'          => 'Referrer',
            'stats.no_data'              => 'No data yet',
            'stats.no_clicks'            => 'No clicks yet',
            'stats.copy'                 => 'Copy link',
            'stats.edit'                 => 'Edit',
            // Edit
            'edit.title'                 => 'Edit Link',
            'edit.original_url'          => 'Original URL',
            'edit.slug'                  => 'Slug',
            'edit.new_password'          => 'New Password',
            'edit.password.has'          => 'Has password — enter new one to change, leave blank to keep',
            'edit.password.none'         => 'Leave blank if not needed',
            'edit.og.section'            => 'Open Graph (Facebook / Zalo Preview)',
            'edit.og_title'              => 'OG Title',
            'edit.og_desc'               => 'OG Description',
            'edit.og_image'              => 'OG Image URL',
            'edit.expires_at'            => 'Expiry Date',
            'edit.status.label'          => 'Status',
            'edit.status.active'         => 'Active',
            'edit.save'                  => 'Save Changes',
            'edit.cancel'                => 'Cancel',
            'edit.err.invalid_url'       => 'Invalid URL.',
            'edit.err.slug_chars'        => 'Slug may only contain letters, numbers, - and _',
            'edit.err.slug_exists'       => 'Slug "%s" already exists.',
            // Settings
            'settings.title'             => 'Settings',
            'settings.subtitle'          => 'Manage your admin account',
            'settings.account.role'      => 'Administrator',
            'settings.account.status'    => 'Currently logged in',
            'settings.pw.title'          => 'Change Password',
            'settings.pw.current'        => 'Current Password',
            'settings.pw.new'            => 'New Password',
            'settings.pw.new.hint'       => 'Minimum 6 characters',
            'settings.pw.confirm'        => 'Confirm New Password',
            'settings.pw.confirm.ph'     => 'Re-enter your new password',
            'settings.pw.submit'         => 'Save New Password',
            'settings.pw.saving'         => 'Saving...',
            'settings.pw.match'          => '✓ Passwords match',
            'settings.pw.nomatch'        => '✗ Passwords do not match',
            'settings.pw.str.0'          => 'Very Weak',
            'settings.pw.str.1'          => 'Weak',
            'settings.pw.str.2'          => 'Medium',
            'settings.pw.str.3'          => 'Strong',
            'settings.note.body'         => 'The new password will be bcrypt-hashed and saved directly into <code>index.php</code>. Ensure the file is writable (chmod 644 or 666 on Linux).',
            'settings.revoke_title'      => 'Active Sessions',
            'settings.revoke_desc'       => 'You are currently logged in on <strong>%s device(s)</strong> via "Remember Me". You can revoke all tokens to sign out from all devices immediately.',
            'settings.revoke_btn'        => 'Sign out from all devices',
            'settings.revoke_success'    => 'All sessions have been revoked.',
            'settings.err.wrong_current' => 'Current password is incorrect.',
            'settings.err.too_short'     => 'New password must be at least 6 characters.',
            'settings.err.no_match'      => 'Passwords do not match.',
            'settings.err.write_fail'    => 'Cannot update config file. Please edit ADMIN_PASS manually.',
            'settings.err.permission'    => 'No write permission. Check index.php file permissions.',
            'settings.success'           => 'Password changed successfully! New hash saved to config.',
            // Analytics
            'analytics.title'            => 'Analytics',
            'analytics.subtitle'         => 'Aggregated click statistics across all links',
            'analytics.link_list'        => 'Link List',
            'analytics.card.links'       => 'Total Links',
            'analytics.card.active'      => '%s active',
            'analytics.card.clicks'      => 'Total Clicks',
            'analytics.card.unique_sub'  => '%s unique',
            'analytics.card.today'       => 'Today',
            'analytics.card.vs_yday'     => '%s%s vs yesterday',
            'analytics.card.month'       => 'Last 30 Days',
            'analytics.card.week_sub'    => '%s in last 7 days',
            'analytics.tab.hour'         => 'Per Hour',
            'analytics.tab.hour.sub'     => '(24h)',
            'analytics.tab.day'          => 'Per Day',
            'analytics.tab.day.sub'      => '(30 days)',
            'analytics.tab.week'         => 'Per Week',
            'analytics.tab.week.sub'     => '(12 weeks)',
            'analytics.tab.month'        => 'Per Month',
            'analytics.tab.month.sub'    => '(12 months)',
            'analytics.chart.hour'       => 'Clicks per hour — last 24 hours',
            'analytics.chart.day'        => 'Clicks per day — last 30 days',
            'analytics.chart.week'       => 'Clicks per week — last 12 weeks',
            'analytics.chart.month'      => 'Clicks per month — last 12 months',
            'analytics.top.title'        => 'Top 10 Most Clicked Links',
            'analytics.col.rank'         => '#',
            'analytics.col.slug'         => 'Slug',
            'analytics.col.url'          => 'Original URL',
            'analytics.col.clicks'       => 'Clicks',
            'analytics.col.unique'       => 'Unique',
            'analytics.col.detail'       => 'Details',
            'analytics.no_data'          => 'No click data yet',
            // 404
            '404.title'                  => 'Page Not Found',
            '404.message'                => "The link you're looking for doesn't exist or has been deleted.",
            '404.button'                 => 'Go Home',
            // About
            'about.title'                => 'About',
            'about.hero.title'           => 'MyShort — Free Self-Hosted URL Shortener',
            'about.hero.intro'           => 'MyShort is a lightweight, self-hosted URL shortener built with pure PHP and SQLite — no framework, no composer, just 3 files and you\'re live.',
            'about.hero.by'              => 'Developed and open-sourced by',
            'about.hero.studio'          => 'a studio that builds tiny digital products designed to automate repetitive work and just run.',
            'about.hero.tagline'         => 'MyShort is exactly that: minimal, reliable, and distraction-free. No dashboards you\'ll never use. No pricing tiers. No lock-in. Just a clean tool that shortens links, tracks clicks, and stays out of your way.',
            'about.feat.title'           => 'Features',
            'about.feat.1.name'          => 'Custom slug',
            'about.feat.1.desc'          => 'Vanity URLs — pick your own short slug.',
            'about.feat.2.name'          => 'Password-protected links',
            'about.feat.2.desc'          => 'Restrict access with a password.',
            'about.feat.3.name'          => 'Click analytics',
            'about.feat.3.desc'          => 'Unique visitor tracking per link.',
            'about.feat.4.name'          => 'OG thumbnail',
            'about.feat.4.desc'          => 'Rich previews on Facebook & Zalo.',
            'about.feat.5.name'          => 'QR code',
            'about.feat.5.desc'          => 'Generate & download instantly.',
            'about.feat.6.name'          => 'Admin dashboard',
            'about.feat.6.desc'          => 'Search, pagination, full control.',
            'about.why.title'            => 'Why MyShort?',
            'about.why.desc'             => 'Most URL shorteners require a database server, a full framework, or a paid plan. MyShort runs on any shared hosting with PHP 8.0+ — no setup headaches, no monthly fees, no noise.',
            'about.why.tagline'          => 'Built small. Runs calm. Does the job.',
            'about.backrun.title'        => 'About Backrun',
            'about.backrun.desc1'        => 'Backrun builds tiny digital products that automate repetitive work — fast, calm, and reliable.',
            'about.backrun.desc2'        => 'The mission is simple: build small, reliable products that remove repetitive work and just run. Every Backrun product is designed to be minimal, controlled, and trustworthy — built to run, not to distract.',
            'about.backrun.desc3'        => 'Tiny tools. Extensions. Flows. Background automation that just runs.',
            'about.backrun.link'         => 'Visit backrun.co',
            // Common
            'common.back'                => 'Back',
            'common.copied'              => 'Copied!',
            'common.required'            => 'required',
        ],

        // ===== TIẾNG VIỆT =====
        'vi' => [
            'lang.name'                  => 'Tiếng Việt',
            // Nav
            'nav.create'                 => 'Tạo Link',
            'nav.dashboard'              => 'Bảng điều khiển',
            'nav.analytics'              => 'Thống kê',
            'nav.settings'               => 'Cài đặt',
            'nav.about'                  => 'Giới thiệu',
            'nav.logout'                 => 'Đăng xuất',
            // Login
            'login.title'                => 'MyShort',
            'login.subtitle'             => 'Đăng nhập để quản lý link rút gọn',
            'login.username'             => 'Tên đăng nhập',
            'login.password'             => 'Mật khẩu',
            'login.submit'               => 'Đăng nhập',
            'login.remember'             => 'Ghi nhớ đăng nhập trong %s ngày',
            'login.error'                => 'Tên đăng nhập hoặc mật khẩu không đúng.',
            // Home
            'home.title'                 => 'Tạo link rút gọn',
            'home.subtitle'              => 'Rút gọn URL dài, thêm mật khẩu bảo vệ, theo dõi thống kê',
            'home.tab.basic'             => 'Cơ bản',
            'home.tab.advanced'          => 'Nâng cao (OG / Hết hạn)',
            'home.original_url'          => 'URL gốc',
            'home.original_url.ph'       => 'https://example.com/duong-dan-rat-dai-cua-ban',
            'home.custom_slug'           => 'Slug tuỳ chỉnh',
            'home.custom_slug.opt'       => '(tùy chọn)',
            'home.custom_slug.ph'        => 'my-link',
            'home.custom_slug.hint'      => 'Để trống → tự sinh 6 ký tự ngẫu nhiên',
            'home.password'              => 'Mật khẩu bảo vệ',
            'home.password.opt'          => '(tùy chọn)',
            'home.password.ph'           => 'Để trống nếu không cần mật khẩu',
            'home.og_title'              => 'OG Title',
            'home.og_title.ph'           => 'Tiêu đề hiển thị khi chia sẻ Facebook/Zalo',
            'home.og_desc'               => 'OG Description',
            'home.og_desc.ph'            => 'Mô tả ngắn hiển thị khi chia sẻ',
            'home.og_image'              => 'OG Image URL',
            'home.og_image.ph'           => 'https://example.com/thumbnail.jpg',
            'home.expires_at'            => 'Ngày hết hạn',
            'home.submit'                => 'Tạo link rút gọn',
            'home.creating'              => 'Đang tạo...',
            'home.success'               => 'Link đã được tạo thành công!',
            'home.short_link'            => 'Link rút gọn',
            'home.download_qr'           => 'Tải QR',
            'home.share'                 => 'Chia sẻ',
            'home.create_new'            => '+ Tạo link mới',
            'home.placeholder'           => 'Link rút gọn và QR code sẽ xuất hiện tại đây',
            'home.err.invalid_url'       => 'URL không hợp lệ. Vui lòng nhập đúng định dạng https://...',
            'home.err.slug_chars'        => 'Slug chỉ được chứa chữ cái, số, dấu - và _',
            'home.err.slug_exists'       => 'Slug "%s" đã tồn tại. Vui lòng chọn slug khác.',
            'home.err.rate_limit'        => 'Vượt quá giới hạn tạo link (10 link/giờ). Vui lòng thử lại sau.',
            'home.err.qr_fail'           => 'Không thể tải QR code',
            'home.err.generic'           => 'Có lỗi xảy ra, vui lòng thử lại',
            // Dashboard
            'dash.title'                 => 'Bảng điều khiển',
            'dash.subtitle'              => 'Tổng cộng %s link',
            'dash.create_new'            => 'Tạo link mới',
            'dash.search.ph'             => 'Tìm theo slug hoặc URL...',
            'dash.col.slug'              => 'Slug',
            'dash.col.url'               => 'URL gốc',
            'dash.col.clicks'            => 'Clicks',
            'dash.col.unique'            => 'Unique',
            'dash.col.created'           => 'Ngày tạo',
            'dash.col.status'            => 'Trạng thái',
            'dash.col.actions'           => 'Hành động',
            'dash.status.active'         => 'Hoạt động',
            'dash.status.expired'        => 'Hết hạn',
            'dash.status.off'            => 'Tắt',
            'dash.empty'                 => 'Không tìm thấy link nào',
            'dash.qr.title'              => 'QR Code',
            'dash.qr.download'           => 'Tải xuống',
            'dash.qr.close'              => 'Đóng',
            'dash.delete.confirm'        => 'Xóa link này? Hành động không thể hoàn tác.',
            // Stats
            'stats.title'                => 'Thống kê',
            'stats.total'                => 'Tổng clicks',
            'stats.unique'               => 'Unique clicks',
            'stats.today'                => 'Hôm nay',
            'stats.week'                 => '7 ngày qua',
            'stats.chart.title'          => 'Biểu đồ click 7 ngày qua',
            'stats.referers.title'       => 'Top 10 nguồn truy cập',
            'stats.recent.title'         => '20 click gần nhất',
            'stats.col.source'           => 'Nguồn',
            'stats.col.clicks'           => 'Clicks',
            'stats.col.time'             => 'Thời gian',
            'stats.col.ip'               => 'IP',
            'stats.col.referer'          => 'Referer',
            'stats.no_data'              => 'Chưa có dữ liệu',
            'stats.no_clicks'            => 'Chưa có click nào',
            'stats.copy'                 => 'Copy link',
            'stats.edit'                 => 'Chỉnh sửa',
            // Edit
            'edit.title'                 => 'Chỉnh sửa link',
            'edit.original_url'          => 'URL gốc',
            'edit.slug'                  => 'Slug',
            'edit.new_password'          => 'Mật khẩu mới',
            'edit.password.has'          => 'Có mật khẩu — nhập để đổi, để trống để giữ nguyên',
            'edit.password.none'         => 'Để trống nếu không cần mật khẩu',
            'edit.og.section'            => 'Open Graph (Facebook / Zalo Preview)',
            'edit.og_title'              => 'OG Title',
            'edit.og_desc'               => 'OG Description',
            'edit.og_image'              => 'OG Image URL',
            'edit.expires_at'            => 'Ngày hết hạn',
            'edit.status.label'          => 'Trạng thái',
            'edit.status.active'         => 'Đang hoạt động',
            'edit.save'                  => 'Lưu thay đổi',
            'edit.cancel'                => 'Hủy',
            'edit.err.invalid_url'       => 'URL không hợp lệ.',
            'edit.err.slug_chars'        => 'Slug chỉ được chứa chữ cái, số, dấu - và _',
            'edit.err.slug_exists'       => 'Slug "%s" đã tồn tại.',
            // Settings
            'settings.title'             => 'Cài đặt',
            'settings.subtitle'          => 'Quản lý tài khoản quản trị',
            'settings.account.role'      => 'Quản trị viên',
            'settings.account.status'    => 'Đang đăng nhập',
            'settings.pw.title'          => 'Đổi mật khẩu',
            'settings.pw.current'        => 'Mật khẩu hiện tại',
            'settings.pw.new'            => 'Mật khẩu mới',
            'settings.pw.new.hint'       => 'Tối thiểu 6 ký tự',
            'settings.pw.confirm'        => 'Xác nhận mật khẩu mới',
            'settings.pw.confirm.ph'     => 'Nhập lại mật khẩu mới',
            'settings.pw.submit'         => 'Lưu mật khẩu mới',
            'settings.pw.saving'         => 'Đang lưu...',
            'settings.pw.match'          => '✓ Mật khẩu khớp',
            'settings.pw.nomatch'        => '✗ Mật khẩu chưa khớp',
            'settings.pw.str.0'          => 'Rất yếu',
            'settings.pw.str.1'          => 'Yếu',
            'settings.pw.str.2'          => 'Trung bình',
            'settings.pw.str.3'          => 'Mạnh',
            'settings.note.body'         => 'Mật khẩu mới sẽ được mã hóa bcrypt và lưu trực tiếp vào <code>index.php</code>. Đảm bảo file có quyền ghi (chmod 644 hoặc 666 trên Linux).',
            'settings.revoke_title'      => 'Phiên đăng nhập đang hoạt động',
            'settings.revoke_desc'       => 'Bạn đang đăng nhập trên <strong>%s thiết bị</strong> qua "Ghi nhớ đăng nhập". Có thể thu hồi tất cả để đăng xuất khỏi mọi thiết bị ngay lập tức.',
            'settings.revoke_btn'        => 'Đăng xuất tất cả thiết bị',
            'settings.revoke_success'    => 'Đã thu hồi tất cả phiên đăng nhập.',
            'settings.err.wrong_current' => 'Mật khẩu hiện tại không đúng.',
            'settings.err.too_short'     => 'Mật khẩu mới phải có ít nhất 6 ký tự.',
            'settings.err.no_match'      => 'Xác nhận mật khẩu không khớp.',
            'settings.err.write_fail'    => 'Không thể cập nhật file cấu hình. Hãy sửa ADMIN_PASS thủ công.',
            'settings.err.permission'    => 'Không có quyền ghi file. Kiểm tra permission của index.php.',
            'settings.success'           => 'Đổi mật khẩu thành công! Hash mới đã được lưu vào file cấu hình.',
            // Analytics
            'analytics.title'            => 'Thống kê',
            'analytics.subtitle'         => 'Thống kê lượt truy cập tổng hợp tất cả link',
            'analytics.link_list'        => 'Danh sách link',
            'analytics.card.links'       => 'Tổng link',
            'analytics.card.active'      => '%s đang hoạt động',
            'analytics.card.clicks'      => 'Tổng clicks',
            'analytics.card.unique_sub'  => '%s unique',
            'analytics.card.today'       => 'Hôm nay',
            'analytics.card.vs_yday'     => '%s%s so với hôm qua',
            'analytics.card.month'       => '30 ngày qua',
            'analytics.card.week_sub'    => '%s trong 7 ngày',
            'analytics.tab.hour'         => 'Theo giờ',
            'analytics.tab.hour.sub'     => '(24h)',
            'analytics.tab.day'          => 'Theo ngày',
            'analytics.tab.day.sub'      => '(30 ngày)',
            'analytics.tab.week'         => 'Theo tuần',
            'analytics.tab.week.sub'     => '(12 tuần)',
            'analytics.tab.month'        => 'Theo tháng',
            'analytics.tab.month.sub'    => '(12 tháng)',
            'analytics.chart.hour'       => 'Lượt click theo giờ — 24 giờ qua',
            'analytics.chart.day'        => 'Lượt click theo ngày — 30 ngày qua',
            'analytics.chart.week'       => 'Lượt click theo tuần — 12 tuần qua',
            'analytics.chart.month'      => 'Lượt click theo tháng — 12 tháng qua',
            'analytics.top.title'        => 'Top 10 link nhiều click nhất',
            'analytics.col.rank'         => '#',
            'analytics.col.slug'         => 'Slug',
            'analytics.col.url'          => 'URL gốc',
            'analytics.col.clicks'       => 'Clicks',
            'analytics.col.unique'       => 'Unique',
            'analytics.col.detail'       => 'Chi tiết',
            'analytics.no_data'          => 'Chưa có dữ liệu click',
            // 404
            '404.title'                  => 'Trang không tồn tại',
            '404.message'                => 'Đường dẫn bạn tìm không tồn tại hoặc đã bị xóa.',
            '404.button'                 => 'Về trang chủ',
            // About
            'about.title'                => 'Giới thiệu',
            'about.hero.title'           => 'MyShort — Phần Mềm Rút Gọn Link Tự Host Miễn Phí',
            'about.hero.intro'           => 'MyShort là phần mềm rút gọn URL tự host, xây dựng bằng PHP thuần và SQLite — không framework, không composer, chỉ 3 file là có thể chạy ngay.',
            'about.hero.by'              => 'Được phát triển và mở mã nguồn bởi',
            'about.hero.studio'          => 'xưởng phần mềm chuyên xây dựng các sản phẩm kỹ thuật số nhỏ gọn, tự động hóa công việc lặp lại và vận hành ổn định.',
            'about.hero.tagline'         => 'MyShort đúng là như vậy: tối giản, đáng tin cậy và không gây phân tâm. Không dashboard thừa. Không bậc giá. Không bị phụ thuộc. Chỉ là một công cụ rút gọn link, theo dõi lượt click và không làm phiền bạn.',
            'about.feat.title'           => 'Tính năng',
            'about.feat.1.name'          => 'Slug tuỳ chỉnh',
            'about.feat.1.desc'          => 'Chọn slug ngắn gọn, dễ nhớ theo ý muốn.',
            'about.feat.2.name'          => 'Bảo vệ bằng mật khẩu',
            'about.feat.2.desc'          => 'Giới hạn truy cập link bằng mật khẩu.',
            'about.feat.3.name'          => 'Thống kê lượt click',
            'about.feat.3.desc'          => 'Theo dõi unique visitor từng link.',
            'about.feat.4.name'          => 'OG thumbnail',
            'about.feat.4.desc'          => 'Preview đẹp khi chia sẻ trên Facebook & Zalo.',
            'about.feat.5.name'          => 'QR code',
            'about.feat.5.desc'          => 'Tạo và tải xuống ngay lập tức.',
            'about.feat.6.name'          => 'Bảng quản lý',
            'about.feat.6.desc'          => 'Tìm kiếm, phân trang, toàn quyền kiểm soát.',
            'about.why.title'            => 'Tại sao chọn MyShort?',
            'about.why.desc'             => 'Hầu hết các dịch vụ rút gọn URL yêu cầu máy chủ database, framework đầy đủ hoặc gói trả phí. MyShort chạy trên mọi hosting shared có PHP 8.0+ — không đau đầu cài đặt, không phí hàng tháng, không nhiễu loạn.',
            'about.why.tagline'          => 'Xây dựng nhỏ. Vận hành bình tĩnh. Làm đúng việc.',
            'about.backrun.title'        => 'Về Backrun',
            'about.backrun.desc1'        => 'Backrun xây dựng các sản phẩm kỹ thuật số nhỏ gọn, tự động hóa công việc lặp lại — nhanh, bình tĩnh và đáng tin cậy.',
            'about.backrun.desc2'        => 'Sứ mệnh đơn giản: xây dựng sản phẩm nhỏ, đáng tin cậy, loại bỏ công việc lặp lại và vận hành ổn định. Mỗi sản phẩm của Backrun được thiết kế tối giản, có kiểm soát và đáng tin — được xây để chạy, không để gây xao nhãng.',
            'about.backrun.desc3'        => 'Công cụ nhỏ. Tiện ích mở rộng. Luồng tự động. Nền tảng hoạt động ngầm và chỉ chạy.',
            'about.backrun.link'         => 'Truy cập backrun.co',
            // Common
            'common.back'                => 'Quay lại',
            'common.copied'              => 'Đã sao chép!',
            'common.required'            => 'bắt buộc',
        ],
    ];
}

// ===== DATABASE SETUP =====
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        initDB($pdo);
    }
    return $pdo;
}

function initDB(PDO $pdo): void {
    $pdo->exec("PRAGMA foreign_keys = ON");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            short_code TEXT UNIQUE NOT NULL,
            original_url TEXT NOT NULL,
            custom_slug TEXT,
            password TEXT,
            og_title TEXT,
            og_description TEXT,
            og_image_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME,
            click_count INTEGER DEFAULT 0,
            unique_clicks INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1,
            created_by_ip TEXT
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clicks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            link_id INTEGER NOT NULL,
            clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address TEXT,
            user_agent TEXT,
            referer TEXT,
            FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token_hash TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            ip_address TEXT,
            user_agent TEXT
        )
    ");
    // Dọn token hết hạn tự động mỗi khi DB được init
    $pdo->exec("DELETE FROM remember_tokens WHERE expires_at < datetime('now')");
}

// ===== HELPER FUNCTIONS =====
function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function generateSlug(int $length = 6): string {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $slug  = '';
    for ($i = 0; $i < $length; $i++) $slug .= $chars[random_int(0, strlen($chars) - 1)];
    return $slug;
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

function isSlugUnique(string $slug, int $excludeId = 0): bool {
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM links WHERE short_code = ? AND id != ?");
    $stmt->execute([$slug, $excludeId]);
    return $stmt->fetchColumn() == 0;
}

function checkRateLimit(string $ip): bool {
    $stmt = getDB()->prepare("
        SELECT COUNT(*) FROM links
        WHERE created_by_ip = ? AND created_at > datetime('now', '-1 hour')
    ");
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn() < RATE_LIMIT;
}

// ===== AUTH =====
function isLoggedIn(): bool {
    return !empty($_SESSION['admin']);
}

function requireAuth(): void {
    if (!isLoggedIn()) redirect('/login');
}

// ===== ROUTER =====
$uri        = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptPath = SCRIPT_PATH;
if ($scriptPath !== '' && str_starts_with($uri, $scriptPath)) {
    $uri = substr($uri, strlen($scriptPath));
}
$uri    = '/' . trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/login') {
    if ($method === 'POST') handleLoginPost();
    else handleLoginGet();
} elseif ($uri === '/logout') {
    handleLogout();
} elseif ($uri === '/lang') {
    handleLang();
} elseif ($uri === '/' || $uri === '') {
    requireAuth();
    handleHome();
} elseif ($uri === '/create' && $method === 'POST') {
    requireAuth();
    handleCreate();
} elseif ($uri === '/dashboard') {
    requireAuth();
    handleDashboard();
} elseif ($uri === '/analytics') {
    requireAuth();
    handleAnalytics();
} elseif ($uri === '/settings/revoke-all' && $method === 'POST') {
    requireAuth();
    handleRevokeAll();
} elseif ($uri === '/settings') {
    requireAuth();
    if ($method === 'POST') handleSettingsPost();
    else handleSettingsGet();
} elseif ($uri === '/about') {
    handleAbout();
} elseif (preg_match('#^/stats/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    requireAuth();
    handleStats($m[1]);
} elseif (preg_match('#^/edit/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    requireAuth();
    if ($method === 'POST') handleEditPost($m[1]);
    else handleEditGet($m[1]);
} elseif (preg_match('#^/delete/([a-zA-Z0-9_-]+)$#', $uri, $m)) {
    requireAuth();
    handleDelete($m[1]);
} else {
    http_response_code(404);
    echo renderNotFound();
}
exit;

// ===== HANDLERS =====

function handleLang(): void {
    $lang = $_GET['set'] ?? 'en';
    if (in_array($lang, ['en', 'vi'], true)) $_SESSION['lang'] = $lang;
    $back = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/';
    // Sanitize referer to only allow same-origin
    if (!str_starts_with($back, BASE_URL)) $back = BASE_URL . '/';
    header('Location: ' . $back);
    exit;
}

function handleLoginGet(string $error = ''): void {
    echo renderLogin($error);
}

function handleLoginPost(): void {
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';
    $rememberMe = !empty($_POST['remember_me']);

    if ($username === ADMIN_USER && password_verify($password, ADMIN_PASS)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        if ($rememberMe) setRememberToken();
        redirect('/dashboard');
    } else {
        handleLoginGet(t('login.error'));
    }
}

function handleLogout(): void {
    clearRememberToken();
    session_destroy();
    redirect('/login');
}

function handleHome(): void {
    echo renderHome();
}

function handleCreate(): void {
    header('Content-Type: application/json; charset=utf-8');
    $ip = getClientIP();
    if (!checkRateLimit($ip)) {
        echo json_encode(['error' => t('home.err.rate_limit')]);
        return;
    }
    $originalUrl = trim($_POST['original_url'] ?? '');
    $customSlug  = trim($_POST['custom_slug'] ?? '');
    $password    = $_POST['password'] ?? '';
    $ogTitle     = trim($_POST['og_title'] ?? '');
    $ogDesc      = trim($_POST['og_description'] ?? '');
    $ogImage     = trim($_POST['og_image_url'] ?? '');
    $expiresAt   = trim($_POST['expires_at'] ?? '');

    if (empty($originalUrl) || !filter_var($originalUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => t('home.err.invalid_url')]);
        return;
    }
    if (!empty($customSlug)) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $customSlug)) {
            echo json_encode(['error' => t('home.err.slug_chars')]);
            return;
        }
        if (!isSlugUnique($customSlug)) {
            echo json_encode(['error' => t('home.err.slug_exists', $customSlug)]);
            return;
        }
        $shortCode = $customSlug;
    } else {
        do { $shortCode = generateSlug(); } while (!isSlugUnique($shortCode));
    }
    $passwordHash = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
    $expiresAtVal = !empty($expiresAt) ? $expiresAt : null;

    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO links (short_code, original_url, custom_slug, password, og_title, og_description, og_image_url, expires_at, created_by_ip)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $shortCode, $originalUrl, $customSlug ?: null, $passwordHash,
        $ogTitle ?: null, $ogDesc ?: null, $ogImage ?: null, $expiresAtVal, $ip,
    ]);
    $shortUrl = BASE_URL . '/' . $shortCode;
    echo json_encode([
        'success'    => true,
        'short_url'  => $shortUrl,
        'short_code' => $shortCode,
        'qr_url'     => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($shortUrl),
    ]);
}

function handleDashboard(): void {
    $db      = getDB();
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset  = ($page - 1) * $perPage;
    $total   = (int)$db->query("SELECT COUNT(*) FROM links")->fetchColumn();
    $stmt    = $db->prepare("SELECT * FROM links ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$perPage, $offset]);
    echo renderDashboard($stmt->fetchAll(), $page, $total);
}

function handleStats(string $code): void {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM links WHERE short_code = ?");
    $stmt->execute([$code]);
    $link = $stmt->fetch();
    if (!$link) { http_response_code(404); echo renderNotFound(); return; }

    $q = static function (string $sql, array $p) use ($db) {
        $s = $db->prepare($sql); $s->execute($p); return $s;
    };

    $todayCount = (int)$q("SELECT COUNT(*) FROM clicks WHERE link_id=? AND clicked_at>=date('now')", [$link['id']])->fetchColumn();
    $weekCount  = (int)$q("SELECT COUNT(*) FROM clicks WHERE link_id=? AND clicked_at>=date('now','-7 days')", [$link['id']])->fetchColumn();

    $refStmt = $db->prepare("
        SELECT referer, COUNT(*) as count FROM clicks
        WHERE link_id=? AND referer!='' AND referer IS NOT NULL
        GROUP BY referer ORDER BY count DESC LIMIT 10
    ");
    $refStmt->execute([$link['id']]);

    $recentStmt = $db->prepare("SELECT * FROM clicks WHERE link_id=? ORDER BY clicked_at DESC LIMIT 20");
    $recentStmt->execute([$link['id']]);

    $chartStmt = $db->prepare("
        SELECT date(clicked_at) as day, COUNT(*) as count FROM clicks
        WHERE link_id=? AND clicked_at>=date('now','-6 days')
        GROUP BY day ORDER BY day
    ");
    $chartStmt->execute([$link['id']]);

    $chartData = [];
    for ($i = 6; $i >= 0; $i--) $chartData[date('Y-m-d', strtotime("-$i days"))] = 0;
    foreach ($chartStmt->fetchAll() as $r) $chartData[$r['day']] = (int)$r['count'];

    echo renderStats($link, [
        'today'    => $todayCount,
        'week'     => $weekCount,
        'referers' => $refStmt->fetchAll(),
        'recent'   => $recentStmt->fetchAll(),
        'chart'    => $chartData,
    ]);
}

function handleEditGet(string $code, string $error = ''): void {
    $stmt = getDB()->prepare("SELECT * FROM links WHERE short_code = ?");
    $stmt->execute([$code]);
    $link = $stmt->fetch();
    if (!$link) { http_response_code(404); echo renderNotFound(); return; }
    echo renderEdit($link, $error);
}

function handleEditPost(string $code): void {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM links WHERE short_code = ?");
    $stmt->execute([$code]);
    $link = $stmt->fetch();
    if (!$link) { http_response_code(404); echo renderNotFound(); return; }

    $originalUrl = trim($_POST['original_url'] ?? '');
    $customSlug  = trim($_POST['custom_slug'] ?? '');
    $password    = $_POST['password'] ?? '';
    $ogTitle     = trim($_POST['og_title'] ?? '');
    $ogDesc      = trim($_POST['og_description'] ?? '');
    $ogImage     = trim($_POST['og_image_url'] ?? '');
    $expiresAt   = trim($_POST['expires_at'] ?? '');
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if (empty($originalUrl) || !filter_var($originalUrl, FILTER_VALIDATE_URL)) {
        handleEditGet($code, t('edit.err.invalid_url')); return;
    }
    $newCode = $code;
    if (!empty($customSlug) && $customSlug !== $code) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $customSlug)) {
            handleEditGet($code, t('edit.err.slug_chars')); return;
        }
        if (!isSlugUnique($customSlug, $link['id'])) {
            handleEditGet($code, t('edit.err.slug_exists', $customSlug)); return;
        }
        $newCode = $customSlug;
    }
    $passwordHash = $link['password'];
    if (!empty($password)) $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $db->prepare("
        UPDATE links SET short_code=?, original_url=?, custom_slug=?,
            password=?, og_title=?, og_description=?, og_image_url=?,
            expires_at=?, is_active=?
        WHERE id=?
    ")->execute([
        $newCode, $originalUrl, $customSlug ?: null, $passwordHash,
        $ogTitle ?: null, $ogDesc ?: null, $ogImage ?: null,
        !empty($expiresAt) ? $expiresAt : null, $isActive, $link['id'],
    ]);
    redirect('/dashboard');
}

function handleDelete(string $code): void {
    getDB()->prepare("DELETE FROM links WHERE short_code=?")->execute([$code]);
    redirect('/dashboard');
}

function handleSettingsGet(string $success = '', string $error = ''): void {
    echo renderSettings($success, $error);
}

function handleSettingsPost(): void {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPass, ADMIN_PASS)) {
        handleSettingsGet('', t('settings.err.wrong_current')); return;
    }
    if (strlen($newPass) < 6) {
        handleSettingsGet('', t('settings.err.too_short')); return;
    }
    if ($newPass !== $confirmPass) {
        handleSettingsGet('', t('settings.err.no_match')); return;
    }
    $newHash    = password_hash($newPass, PASSWORD_DEFAULT);
    $filePath   = __FILE__;
    $content    = file_get_contents($filePath);
    $oldHash    = ADMIN_PASS;
    $newContent = str_replace(
        "define('ADMIN_PASS', '$oldHash')",
        "define('ADMIN_PASS', '$newHash')",
        $content, $count
    );
    if ($count === 0) { handleSettingsGet('', t('settings.err.write_fail')); return; }
    if (file_put_contents($filePath, $newContent) === false) {
        handleSettingsGet('', t('settings.err.permission')); return;
    }
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    handleSettingsGet(t('settings.success'));
}

function handleRevokeAll(): void {
    // Xóa tất cả token remember → đăng xuất khỏi mọi thiết bị
    getDB()->exec("DELETE FROM remember_tokens");
    clearRememberToken();
    handleSettingsGet(t('settings.revoke_success'));
}

function handleAnalytics(): void {
    $db = getDB();

    $totalLinks   = (int)$db->query("SELECT COUNT(*) FROM links")->fetchColumn();
    $totalClicks  = (int)$db->query("SELECT COALESCE(SUM(click_count),0) FROM links")->fetchColumn();
    $totalUnique  = (int)$db->query("SELECT COALESCE(SUM(unique_clicks),0) FROM links")->fetchColumn();
    $activeLinks  = (int)$db->query("SELECT COUNT(*) FROM links WHERE is_active=1 AND (expires_at IS NULL OR expires_at > datetime('now'))")->fetchColumn();
    $todayClicks  = (int)$db->query("SELECT COUNT(*) FROM clicks WHERE clicked_at >= date('now')")->fetchColumn();
    $yestClicks   = (int)$db->query("SELECT COUNT(*) FROM clicks WHERE clicked_at >= date('now','-1 day') AND clicked_at < date('now')")->fetchColumn();
    $weekClicks   = (int)$db->query("SELECT COUNT(*) FROM clicks WHERE clicked_at >= date('now','-7 days')")->fetchColumn();
    $monthClicks  = (int)$db->query("SELECT COUNT(*) FROM clicks WHERE clicked_at >= date('now','-30 days')")->fetchColumn();

    $hourRaw = $db->query("SELECT strftime('%H',clicked_at) as hr, COUNT(*) as cnt FROM clicks WHERE clicked_at >= datetime('now','-24 hours') GROUP BY hr ORDER BY hr")->fetchAll();
    $hourData = array_fill(0, 24, 0);
    foreach ($hourRaw as $r) $hourData[(int)$r['hr']] = (int)$r['cnt'];

    $dayRaw = $db->query("SELECT date(clicked_at) as d, COUNT(*) as cnt FROM clicks WHERE clicked_at >= date('now','-29 days') GROUP BY d ORDER BY d")->fetchAll();
    $dayData = [];
    for ($i = 29; $i >= 0; $i--) $dayData[date('Y-m-d', strtotime("-$i days"))] = 0;
    foreach ($dayRaw as $r) if (isset($dayData[$r['d']])) $dayData[$r['d']] = (int)$r['cnt'];

    $weekRaw = $db->query("SELECT strftime('%Y-W%W', clicked_at) as wk, COUNT(*) as cnt FROM clicks WHERE clicked_at >= date('now','-84 days') GROUP BY wk ORDER BY wk")->fetchAll();
    $weekLabels = [];
    for ($i = 11; $i >= 0; $i--) {
        $ts  = strtotime("-" . ($i * 7) . " days");
        $weekLabels[strftime('%Y-W%W', $ts)] = 'W' . date('W', $ts) . '/' . date('y', $ts);
    }
    $weekFinal = array_fill_keys(array_values($weekLabels), 0);
    foreach ($weekRaw as $r) if (isset($weekLabels[$r['wk']])) $weekFinal[$weekLabels[$r['wk']]] = (int)$r['cnt'];

    $monthRaw = $db->query("SELECT strftime('%Y-%m', clicked_at) as mo, COUNT(*) as cnt FROM clicks WHERE clicked_at >= date('now','-365 days') GROUP BY mo ORDER BY mo")->fetchAll();
    $monthMap = [];
    for ($i = 11; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("first day of -$i months"));
        $monthMap[$key] = date('m/Y', strtotime("first day of -$i months"));
    }
    $monthFinal = array_fill_keys(array_values($monthMap), 0);
    foreach ($monthRaw as $r) if (isset($monthMap[$r['mo']])) $monthFinal[$monthMap[$r['mo']]] = (int)$r['cnt'];

    $topStmt = $db->query("SELECT short_code, original_url, click_count, unique_clicks FROM links ORDER BY click_count DESC LIMIT 10");

    echo renderAnalytics([
        'total_links'   => $totalLinks,
        'total_clicks'  => $totalClicks,
        'total_unique'  => $totalUnique,
        'active_links'  => $activeLinks,
        'today_clicks'  => $todayClicks,
        'yest_clicks'   => $yestClicks,
        'week_clicks'   => $weekClicks,
        'month_clicks'  => $monthClicks,
        'hour_data'     => $hourData,
        'day_data'      => $dayData,
        'week_data'     => $weekFinal,
        'month_data'    => $monthFinal,
        'top_links'     => $topStmt->fetchAll(),
    ]);
}

function handleAbout(): void {
    echo renderAbout();
}

// ===== VIEWS =====

function getNavbar(): string {
    $base = BASE_URL;
    $lang = getLang();
    $otherLang  = $lang === 'en' ? 'vi' : 'en';
    $otherLabel = $lang === 'en' ? '🇻🇳 Tiếng Việt' : '🇬🇧 English';
    $langUrl    = $base . '/lang?set=' . $otherLang;
    $navCreate  = t('nav.create');
    $navDash    = t('nav.dashboard');
    $navAna     = t('nav.analytics');
    $navSet     = t('nav.settings');
    $navAbout   = t('nav.about');
    $navLogout  = t('nav.logout');
    $currentFlag = $lang === 'en' ? '🇬🇧 EN' : '🇻🇳 VI';

    return <<<HTML
<nav class="bg-gray-900/80 backdrop-blur-md border-b border-gray-800 sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <div class="flex items-center gap-5">
        <a href="{$base}/" class="flex items-center gap-2 font-bold text-lg shrink-0">
          <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600/30 border border-indigo-500/40">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
          </span>
          <span class="text-indigo-400">My</span><span class="text-white">Short</span>
        </a>
        <div class="hidden md:flex items-center gap-0.5">
          <a href="{$base}/" class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-colors">{$navCreate}</a>
          <a href="{$base}/dashboard" class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-colors">{$navDash}</a>
          <a href="{$base}/analytics" class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-colors">{$navAna}</a>
          <a href="{$base}/about" class="px-3 py-2 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800 transition-colors">{$navAbout}</a>
        </div>
      </div>
      <div class="flex items-center gap-1">
        <!-- Language switcher -->
        <a href="{$langUrl}"
           class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition-colors border border-gray-700/50">
          {$currentFlag}
          <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </a>
        <!-- Settings -->
        <a href="{$base}/settings" title="{$navSet}" class="p-2 rounded-lg text-gray-500 hover:text-white hover:bg-gray-800 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </a>
        <!-- Logout -->
        <a href="{$base}/logout" title="{$navLogout}" class="p-2 rounded-lg text-gray-500 hover:text-white hover:bg-gray-800 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </a>
      </div>
    </div>
  </div>
</nav>
HTML;
}

function renderLayout(string $title, string $content): string {
    $navbar  = isLoggedIn() ? getNavbar() : '';
    $titleH  = h($title);
    return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$titleH} — MyShort</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{gray:{950:'#0a0a12'}}}}}</script>
  <style>
    body{background:#0d0d18}
    .toast-enter{animation:toastIn .3s ease}
    @keyframes toastIn{from{opacity:0;transform:translateX(2rem)}to{opacity:1;transform:translateX(0)}}
    .toast-exit{animation:toastOut .3s ease forwards}
    @keyframes toastOut{to{opacity:0;transform:translateX(2rem)}}
    input[type="datetime-local"]::-webkit-calendar-picker-indicator{filter:invert(.7);cursor:pointer}
    ::-webkit-scrollbar{width:6px;height:6px}
    ::-webkit-scrollbar-track{background:#111827}
    ::-webkit-scrollbar-thumb{background:#374151;border-radius:3px}
  </style>
</head>
<body class="text-gray-100 min-h-screen">
  {$navbar}
  <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>
  {$content}
  <script>
  function showToast(msg,type='success'){
    const c=document.getElementById('toast-container');
    const t=document.createElement('div');
    const bg=type==='success'?'bg-emerald-600':type==='error'?'bg-red-600':'bg-indigo-600';
    t.className=`toast-enter pointer-events-auto px-4 py-3 rounded-xl \${bg} text-white text-sm font-medium shadow-2xl flex items-center gap-3 max-w-sm`;
    const icon=type==='success'
      ?'<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>'
      :'<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
    t.innerHTML=icon+`<span class="flex-1">\${msg}</span><button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100 text-lg leading-none ml-1">×</button>`;
    c.appendChild(t);
    setTimeout(()=>{t.classList.replace('toast-enter','toast-exit');setTimeout(()=>t.remove(),300)},3500);
  }
  function copyText(text,msg){
    msg=msg||'Copied!';
    if(navigator.clipboard){navigator.clipboard.writeText(text).then(()=>showToast(msg)).catch(()=>fbCopy(text,msg));}
    else fbCopy(text,msg);
  }
  function fbCopy(text,msg){
    const el=Object.assign(document.createElement('textarea'),{value:text,style:'position:fixed;left:-9999px'});
    document.body.appendChild(el);el.select();document.execCommand('copy');el.remove();showToast(msg);
  }
  </script>
</body>
</html>
HTML;
}

// ===== VIEW: LOGIN =====
function renderLogin(string $error = ''): string {
    $base      = BASE_URL;
    $tTitle    = t('login.title');
    $tSub      = t('login.subtitle');
    $tUser     = t('login.username');
    $tPass     = t('login.password');
    $tSubmit   = t('login.submit');
    $tRemember = t('login.remember', REMEMBER_DAYS);
    $errHtml   = $error
        ? '<div class="flex items-start gap-2 bg-red-950/60 border border-red-800/60 text-red-300 px-4 py-3 rounded-xl text-sm mb-5">
             <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
             <span>' . h($error) . '</span></div>'
        : '';
    $content = <<<HTML
<div class="min-h-screen flex items-center justify-center px-4 py-12">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 mb-5 shadow-lg shadow-indigo-900/30">
        <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
        </svg>
      </div>
      <h1 class="text-3xl font-bold text-white tracking-tight">{$tTitle}</h1>
      <p class="text-gray-500 mt-2 text-sm">{$tSub}</p>
    </div>
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-8 shadow-2xl">
      {$errHtml}
      <form method="POST" action="{$base}/login" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">{$tUser}</label>
          <input type="text" name="username" autocomplete="username" required autofocus
                 class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition"
                 placeholder="admin">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">{$tPass}</label>
          <div class="relative">
            <input type="password" name="password" id="lPass" autocomplete="current-password" required
                   class="w-full px-4 py-3 pr-12 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition"
                   placeholder="••••••••">
            <button type="button" onclick="toggleP('lPass')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 p-1 transition">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
        </div>
        <!-- Remember me -->
        <label class="flex items-center gap-3 cursor-pointer select-none group">
          <div class="relative">
            <input type="checkbox" name="remember_me" value="1" id="rememberMe" class="sr-only peer">
            <div class="w-10 h-5 bg-gray-700 peer-checked:bg-indigo-600 rounded-full transition-colors duration-200"></div>
            <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5"></div>
          </div>
          <span class="text-sm text-gray-400 group-hover:text-gray-300 transition-colors">{$tRemember}</span>
        </label>
        <button type="submit" class="w-full py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-semibold rounded-xl transition-all shadow-lg shadow-indigo-900/40">
          {$tSubmit}
        </button>
      </form>
    </div>
    <p class="text-center mt-6 text-gray-700 text-xs">
      <a href="{$base}/about" class="hover:text-gray-500 transition-colors">MyShort</a>
    </p>
  </div>
</div>
<script>function toggleP(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}</script>
HTML;
    return renderLayout($tTitle, $content);
}

// ===== VIEW: HOME =====
function renderHome(): string {
    $base       = BASE_URL;
    $tTitle     = t('home.title');
    $tSub       = t('home.subtitle');
    $tBasic     = t('home.tab.basic');
    $tAdv       = t('home.tab.advanced');
    $tOrigUrl   = t('home.original_url');
    $tOrigPh    = t('home.original_url.ph');
    $tSlug      = t('home.custom_slug');
    $tSlugOpt   = t('home.custom_slug.opt');
    $tSlugPh    = t('home.custom_slug.ph');
    $tSlugHint  = t('home.custom_slug.hint');
    $tPass      = t('home.password');
    $tPassOpt   = t('home.password.opt');
    $tPassPh    = t('home.password.ph');
    $tOgTitle   = t('home.og_title');
    $tOgTitlePh = t('home.og_title.ph');
    $tOgDesc    = t('home.og_desc');
    $tOgDescPh  = t('home.og_desc.ph');
    $tOgImg     = t('home.og_image');
    $tOgImgPh   = t('home.og_image.ph');
    $tExpires   = t('home.expires_at');
    $tSubmit    = t('home.submit');
    $tCreating  = t('home.creating');
    $tSuccess   = t('home.success');
    $tShortLnk  = t('home.short_link');
    $tDlQr      = t('home.download_qr');
    $tShare     = t('home.share');
    $tNewLink   = t('home.create_new');
    $tPlaceh    = t('home.placeholder');
    $tErrQr     = t('home.err.qr_fail');
    $tErrGen    = t('home.err.generic');

    $content = <<<HTML
<div class="max-w-5xl mx-auto px-4 py-10">
  <div class="mb-8">
    <h1 class="text-3xl font-bold text-white tracking-tight">{$tTitle}</h1>
    <p class="text-gray-500 mt-2 text-sm">{$tSub}</p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
    <!-- Form -->
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
      <div class="flex border-b border-gray-800">
        <button onclick="switchTab('basic')" id="tab-basic"
                class="flex-1 py-3.5 px-4 text-sm font-medium transition-colors border-b-2 border-indigo-500 text-indigo-400 bg-indigo-600/10">{$tBasic}</button>
        <button onclick="switchTab('adv')" id="tab-adv"
                class="flex-1 py-3.5 px-4 text-sm font-medium transition-colors border-b-2 border-transparent text-gray-500 hover:text-gray-300">{$tAdv}</button>
      </div>
      <form id="createForm" class="p-6 space-y-4">
        <div id="panel-basic" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">{$tOrigUrl} <span class="text-red-400">*</span></label>
            <input type="url" name="original_url" required autocomplete="off"
                   class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition"
                   placeholder="{$tOrigPh}">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">{$tSlug} <span class="text-gray-600 font-normal">{$tSlugOpt}</span></label>
            <div class="flex rounded-xl overflow-hidden border border-gray-700 focus-within:ring-2 focus-within:ring-indigo-500/70 focus-within:border-indigo-500 transition">
              <span class="inline-flex items-center px-3 bg-gray-800 text-gray-500 text-sm border-r border-gray-700 select-none">/</span>
              <input type="text" name="custom_slug" autocomplete="off" pattern="[a-zA-Z0-9_-]*"
                     class="flex-1 px-4 py-3 bg-gray-800/80 text-white placeholder-gray-600 focus:outline-none"
                     placeholder="{$tSlugPh}">
            </div>
            <p class="text-xs text-gray-600 mt-1.5">{$tSlugHint}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">{$tPass} <span class="text-gray-600 font-normal">{$tPassOpt}</span></label>
            <input type="password" name="password" autocomplete="new-password"
                   class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition"
                   placeholder="{$tPassPh}">
          </div>
        </div>
        <div id="panel-adv" class="hidden space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">{$tOgTitle}</label>
            <input type="text" name="og_title" class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition" placeholder="{$tOgTitlePh}">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">{$tOgDesc}</label>
            <textarea name="og_description" rows="2" class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition resize-none" placeholder="{$tOgDescPh}"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">{$tOgImg}</label>
            <input type="url" name="og_image_url" class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition" placeholder="{$tOgImgPh}">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">{$tExpires}</label>
            <input type="datetime-local" name="expires_at" class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition">
          </div>
        </div>
        <button type="submit" id="submitBtn"
                class="w-full py-3 px-4 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-semibold rounded-xl transition-all shadow-lg shadow-indigo-900/30 flex items-center justify-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101"/></svg>
          {$tSubmit}
        </button>
      </form>
    </div>

    <!-- Placeholder -->
    <div id="placeholderPanel" class="bg-gray-900/40 border border-dashed border-gray-800 rounded-2xl p-10 flex flex-col items-center justify-center text-center gap-4 min-h-64">
      <div class="w-16 h-16 rounded-2xl bg-gray-800/60 flex items-center justify-center">
        <svg class="w-8 h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
        </svg>
      </div>
      <p class="text-gray-600 text-sm">{$tPlaceh}</p>
    </div>

    <!-- Result -->
    <div id="resultPanel" class="hidden bg-gray-900/80 border border-gray-800 rounded-2xl p-6 space-y-5 shadow-xl">
      <div class="flex items-center gap-2 text-emerald-400 font-medium text-sm">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {$tSuccess}
      </div>
      <div>
        <label class="block text-xs text-gray-600 uppercase tracking-widest mb-2">{$tShortLnk}</label>
        <div class="flex gap-2">
          <input type="text" id="shortUrlDisplay" readonly class="flex-1 px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-indigo-300 font-mono text-sm focus:outline-none cursor-text select-all">
          <button id="copyBtn" class="px-4 py-3 bg-indigo-600 hover:bg-indigo-500 rounded-xl transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          </button>
        </div>
      </div>
      <div class="flex flex-col items-center gap-4">
        <div class="bg-white p-3 rounded-2xl shadow-lg"><img id="qrImage" src="" alt="QR Code" class="w-44 h-44 block"></div>
        <div class="flex gap-2 w-full">
          <button id="downloadQr" class="flex-1 py-2.5 px-4 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl text-sm transition-colors flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            {$tDlQr}
          </button>
          <button id="shareBtn" class="flex-1 py-2.5 px-4 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl text-sm transition-colors flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            {$tShare}
          </button>
        </div>
      </div>
      <button onclick="resetForm()" class="w-full py-2.5 border border-gray-700 hover:bg-gray-800 rounded-xl text-sm text-gray-500 hover:text-gray-300 transition-colors">{$tNewLink}</button>
    </div>
  </div>
</div>
<script>
const _T={submit:'{$tSubmit}',creating:'{$tCreating}',errQr:'{$tErrQr}',errGen:'{$tErrGen}'};
function switchTab(tab){
  document.getElementById('panel-basic').classList.toggle('hidden',tab!=='basic');
  document.getElementById('panel-adv').classList.toggle('hidden',tab!=='adv');
  document.getElementById('tab-basic').className='flex-1 py-3.5 px-4 text-sm font-medium transition-colors border-b-2 '+(tab==='basic'?'border-indigo-500 text-indigo-400 bg-indigo-600/10':'border-transparent text-gray-500 hover:text-gray-300');
  document.getElementById('tab-adv').className='flex-1 py-3.5 px-4 text-sm font-medium transition-colors border-b-2 '+(tab==='adv'?'border-indigo-500 text-indigo-400 bg-indigo-600/10':'border-transparent text-gray-500 hover:text-gray-300');
}
const SPINNER=`<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>`;
document.getElementById('createForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const btn=document.getElementById('submitBtn');
  btn.disabled=true;
  btn.innerHTML=SPINNER+' '+_T.creating;
  try{
    const res=await fetch('{$base}/create',{method:'POST',body:new FormData(this)});
    const data=await res.json();
    if(data.error){showToast(data.error,'error');}
    else{
      document.getElementById('shortUrlDisplay').value=data.short_url;
      document.getElementById('qrImage').src=data.qr_url;
      document.getElementById('placeholderPanel').classList.add('hidden');
      document.getElementById('resultPanel').classList.remove('hidden');
      document.getElementById('copyBtn').onclick=()=>copyText(data.short_url);
      document.getElementById('downloadQr').onclick=async()=>{
        try{const r=await fetch(data.qr_url);const blob=await r.blob();
          const a=Object.assign(document.createElement('a'),{href:URL.createObjectURL(blob),download:'qr-'+data.short_code+'.png'});
          a.click();URL.revokeObjectURL(a.href);}
        catch{showToast(_T.errQr,'error');}
      };
      document.getElementById('shareBtn').onclick=()=>{
        if(navigator.share)navigator.share({url:data.short_url,title:'Short link'}).catch(()=>{});
        else copyText(data.short_url);
      };
    }
  }catch{showToast(_T.errGen,'error');}
  btn.disabled=false;
  btn.innerHTML='<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101"/></svg> '+_T.submit;
});
function resetForm(){
  document.getElementById('createForm').reset();
  document.getElementById('resultPanel').classList.add('hidden');
  document.getElementById('placeholderPanel').classList.remove('hidden');
  switchTab('basic');
}
</script>
HTML;
    return renderLayout($tTitle, $content);
}

// ===== VIEW: DASHBOARD =====
function renderDashboard(array $links, int $page, int $total): string {
    $base    = BASE_URL;
    $perPage = 20;
    $totalPages = max(1, (int)ceil($total / $perPage));

    $tTitle    = t('dash.title');
    $tSub      = t('dash.subtitle', number_format($total));
    $tNew      = t('dash.create_new');
    $tSearchPh = t('dash.search.ph');
    $tColSlug  = t('dash.col.slug');
    $tColUrl   = t('dash.col.url');
    $tColClick = t('dash.col.clicks');
    $tColUniq  = t('dash.col.unique');
    $tColDate  = t('dash.col.created');
    $tColStat  = t('dash.col.status');
    $tColAct   = t('dash.col.actions');
    $tEmpty    = t('dash.empty');
    $tQrDl     = t('dash.qr.download');
    $tQrClose  = t('dash.qr.close');
    $tQrTitle  = t('dash.qr.title');
    $tDelConf  = h(t('dash.delete.confirm'));

    $rows = '';
    foreach ($links as $link) {
        $isExpired = $link['expires_at'] && strtotime($link['expires_at']) < time();
        $isOn      = $link['is_active'] && !$isExpired;
        $badgeTxt  = $isOn ? t('dash.status.active') : ($isExpired ? t('dash.status.expired') : t('dash.status.off'));
        $badgeCls  = $isOn ? 'bg-emerald-950/60 text-emerald-400 border-emerald-800/60' : 'bg-red-950/60 text-red-400 border-red-800/60';
        $badge     = '<span class="px-2 py-0.5 rounded-full text-xs border font-medium ' . $badgeCls . '">' . h($badgeTxt) . '</span>';
        $shortUrl  = BASE_URL . '/' . h($link['short_code']);
        $truncUrl  = mb_strimwidth($link['original_url'], 0, 55, '…');
        $search    = h(strtolower($link['short_code'] . ' ' . $link['original_url']));
        $rows .= '<tr class="border-b border-gray-800/70 hover:bg-gray-800/30 transition-colors" data-search="' . $search . '">';
        $rows .= '<td class="px-4 py-3 whitespace-nowrap"><a href="' . $shortUrl . '" target="_blank" class="text-indigo-400 hover:text-indigo-300 font-mono text-sm font-medium">' . h($link['short_code']) . '</a></td>';
        $rows .= '<td class="px-4 py-3 text-gray-400 text-sm max-w-xs" title="' . h($link['original_url']) . '"><span class="truncate block">' . h($truncUrl) . '</span></td>';
        $rows .= '<td class="px-4 py-3 text-center text-white font-semibold tabular-nums">' . number_format($link['click_count']) . '</td>';
        $rows .= '<td class="px-4 py-3 text-center text-gray-400 tabular-nums">' . number_format($link['unique_clicks']) . '</td>';
        $rows .= '<td class="px-4 py-3 text-gray-500 text-sm whitespace-nowrap">' . date('d/m/Y', strtotime($link['created_at'])) . '</td>';
        $rows .= '<td class="px-4 py-3">' . $badge . '</td>';
        $rows .= '<td class="px-4 py-3 whitespace-nowrap">
          <div class="flex items-center gap-0.5">
            <button onclick="copyText(\'' . $shortUrl . '\')" title="Copy" class="p-2 rounded-lg hover:bg-gray-700 text-gray-500 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </button>
            <button onclick="showQR(\'' . $shortUrl . '\',\'' . h($link['short_code']) . '\')" title="QR" class="p-2 rounded-lg hover:bg-gray-700 text-gray-500 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            </button>
            <a href="' . $base . '/stats/' . h($link['short_code']) . '" class="p-2 rounded-lg hover:bg-gray-700 text-gray-500 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </a>
            <a href="' . $base . '/edit/' . h($link['short_code']) . '" class="p-2 rounded-lg hover:bg-gray-700 text-gray-500 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </a>
            <a href="' . $base . '/delete/' . h($link['short_code']) . '" onclick="return confirm(\'' . $tDelConf . '\')" class="p-2 rounded-lg hover:bg-red-900/40 text-gray-500 hover:text-red-400 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </a>
          </div>
        </td>';
        $rows .= '</tr>';
    }

    $pag = '';
    if ($totalPages > 1) {
        $pag = '<div class="flex items-center justify-center gap-1.5 mt-6">';
        if ($page > 1) $pag .= '<a href="' . $base . '/dashboard?page=' . ($page-1) . '" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-sm transition-colors">←</a>';
        for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++) {
            $cls = $i === $page ? 'bg-indigo-600 border-indigo-500 text-white' : 'bg-gray-800 border-gray-700 text-gray-400 hover:bg-gray-700';
            $pag .= '<a href="' . $base . '/dashboard?page=' . $i . '" class="px-3 py-2 border rounded-lg text-sm transition-colors ' . $cls . '">' . $i . '</a>';
        }
        if ($page < $totalPages) $pag .= '<a href="' . $base . '/dashboard?page=' . ($page+1) . '" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-sm transition-colors">→</a>';
        $pag .= '</div>';
    }

    $content = <<<HTML
<div class="max-w-7xl mx-auto px-4 py-10">
  <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
      <h1 class="text-3xl font-bold text-white tracking-tight">{$tTitle}</h1>
      <p class="text-gray-500 mt-1">{$tSub}</p>
    </div>
    <a href="{$base}/" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-sm font-medium transition-colors shadow-lg shadow-indigo-900/30">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
      {$tNew}
    </a>
  </div>
  <div class="relative mb-5">
    <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-600 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <input type="text" id="searchInput" placeholder="{$tSearchPh}"
           class="w-full pl-11 pr-4 py-3 bg-gray-900 border border-gray-800 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/60 focus:border-indigo-500 transition">
  </div>
  <div class="bg-gray-900/80 border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-800 bg-gray-800/40">
            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{$tColSlug}</th>
            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{$tColUrl}</th>
            <th class="px-4 py-3.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">{$tColClick}</th>
            <th class="px-4 py-3.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">{$tColUniq}</th>
            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{$tColDate}</th>
            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{$tColStat}</th>
            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{$tColAct}</th>
          </tr>
        </thead>
        <tbody id="tableBody">{$rows}</tbody>
      </table>
    </div>
    <div id="emptyState" class="hidden px-6 py-16 text-center">
      <p class="text-gray-600">{$tEmpty}</p>
    </div>
  </div>
  {$pag}
</div>

<!-- QR Modal -->
<div id="qrModal" class="hidden fixed inset-0 bg-black/75 backdrop-blur-sm flex items-center justify-center z-50 p-4" onclick="if(event.target===this)closeQR()">
  <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6 text-center shadow-2xl w-full max-w-xs">
    <h3 class="text-white font-semibold mb-1">{$tQrTitle}</h3>
    <p class="text-gray-600 text-xs mb-4" id="qrModalCode"></p>
    <div class="bg-white p-3 rounded-xl inline-block mb-4 shadow-lg"><img id="modalQrImg" src="" alt="QR" class="w-52 h-52 block"></div>
    <div class="flex gap-2">
      <button id="modalDlBtn" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-500 rounded-xl text-sm font-medium transition-colors">{$tQrDl}</button>
      <button onclick="closeQR()" class="flex-1 py-2.5 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl text-sm transition-colors">{$tQrClose}</button>
    </div>
  </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('input',function(){
  const q=this.value.toLowerCase().trim();
  const rows=document.querySelectorAll('#tableBody tr');
  let vis=0;
  rows.forEach(r=>{const show=!q||(r.dataset.search||'').includes(q);r.style.display=show?'':'none';if(show)vis++;});
  document.getElementById('emptyState').classList.toggle('hidden',vis>0||rows.length===0);
});
function showQR(url,code){
  const qrUrl='https://api.qrserver.com/v1/create-qr-code/?size=208x208&data='+encodeURIComponent(url);
  document.getElementById('modalQrImg').src=qrUrl;
  document.getElementById('qrModalCode').textContent='/'+code;
  document.getElementById('modalDlBtn').onclick=async()=>{
    const r=await fetch(qrUrl);const blob=await r.blob();
    const a=Object.assign(document.createElement('a'),{href:URL.createObjectURL(blob),download:'qr-'+code+'.png'});
    a.click();URL.revokeObjectURL(a.href);
  };
  document.getElementById('qrModal').classList.remove('hidden');
  document.body.style.overflow='hidden';
}
function closeQR(){document.getElementById('qrModal').classList.add('hidden');document.body.style.overflow='';}
</script>
HTML;
    return renderLayout($tTitle, $content);
}

// ===== VIEW: STATS =====
function renderStats(array $link, array $stats): string {
    $base         = BASE_URL;
    $code         = h($link['short_code']);
    $origUrl      = h($link['original_url']);
    $shortUrl     = BASE_URL . '/' . h($link['short_code']);
    $chartLabels  = json_encode(array_keys($stats['chart']));
    $chartVals    = json_encode(array_values($stats['chart']));

    $tTitle      = t('stats.title') . ': ' . h($link['short_code']);
    $tTotal      = t('stats.total');
    $tUniq       = t('stats.unique');
    $tToday      = t('stats.today');
    $tWeek       = t('stats.week');
    $tChart      = t('stats.chart.title');
    $tRefTitle   = t('stats.referers.title');
    $tRecTitle   = t('stats.recent.title');
    $tColSrc     = t('stats.col.source');
    $tColClk     = t('stats.col.clicks');
    $tColTime    = t('stats.col.time');
    $tColIp      = t('stats.col.ip');
    $tColRef     = t('stats.col.referer');
    $tNoData     = t('stats.no_data');
    $tNoClicks   = t('stats.no_clicks');
    $tCopy       = t('stats.copy');
    $tEdit       = t('stats.edit');

    $cTotal  = number_format($link['click_count']);
    $cUniq   = number_format($link['unique_clicks']);
    $cToday  = number_format($stats['today']);
    $cWeek   = number_format($stats['week']);

    $refRows = '';
    foreach ($stats['referers'] as $r) {
        $refRows .= '<tr class="border-b border-gray-800/60 hover:bg-gray-800/30 transition-colors">
            <td class="px-4 py-3 text-gray-300 text-sm truncate max-w-xs">' . h($r['referer'] ?: '(Direct)') . '</td>
            <td class="px-4 py-3 text-white font-semibold tabular-nums">' . number_format($r['count']) . '</td>
        </tr>';
    }
    if (!$refRows) $refRows = '<tr><td colspan="2" class="px-4 py-10 text-center text-gray-700">' . $tNoData . '</td></tr>';

    $clickRows = '';
    foreach ($stats['recent'] as $c) {
        $parts    = explode('.', $c['ip_address'] ?? '');
        $maskedIp = count($parts) === 4 ? $parts[0] . '.' . $parts[1] . '.x.x' : h($c['ip_address']);
        $clickRows .= '<tr class="border-b border-gray-800/60">
            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">' . date('d/m H:i', strtotime($c['clicked_at'])) . '</td>
            <td class="px-4 py-3 text-gray-300 text-xs font-mono">' . h($maskedIp) . '</td>
            <td class="px-4 py-3 text-gray-500 text-xs truncate max-w-xs">' . h($c['referer'] ?: '(Direct)') . '</td>
        </tr>';
    }
    if (!$clickRows) $clickRows = '<tr><td colspan="3" class="px-4 py-10 text-center text-gray-700">' . $tNoClicks . '</td></tr>';

    $content = <<<HTML
<div class="max-w-6xl mx-auto px-4 py-10">
  <div class="flex items-start gap-4 mb-8">
    <a href="{$base}/dashboard" class="mt-1 text-gray-600 hover:text-white transition-colors shrink-0">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <div class="min-w-0 flex-1">
      <h1 class="text-2xl font-bold text-white">{$tTitle}</h1>
      <p class="text-gray-600 text-sm mt-1 truncate">{$origUrl}</p>
    </div>
    <div class="shrink-0 flex gap-2">
      <button onclick="copyText('{$shortUrl}')" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs text-gray-400 hover:text-white transition-colors">{$tCopy}</button>
      <a href="{$base}/edit/{$code}" class="px-3 py-2 bg-indigo-600/20 hover:bg-indigo-600/40 border border-indigo-700/40 rounded-lg text-xs text-indigo-400 transition-colors">{$tEdit}</a>
    </div>
  </div>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-5">
      <p class="text-gray-600 text-xs uppercase tracking-wider mb-2">{$tTotal}</p>
      <p class="text-3xl font-bold text-white">{$cTotal}</p>
    </div>
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-5">
      <p class="text-gray-600 text-xs uppercase tracking-wider mb-2">{$tUniq}</p>
      <p class="text-3xl font-bold text-indigo-400">{$cUniq}</p>
    </div>
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-5">
      <p class="text-gray-600 text-xs uppercase tracking-wider mb-2">{$tToday}</p>
      <p class="text-3xl font-bold text-violet-400">{$cToday}</p>
    </div>
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-5">
      <p class="text-gray-600 text-xs uppercase tracking-wider mb-2">{$tWeek}</p>
      <p class="text-3xl font-bold text-emerald-400">{$cWeek}</p>
    </div>
  </div>
  <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-6 mb-6">
    <h2 class="text-white font-semibold mb-5">{$tChart}</h2>
    <canvas id="clicksChart" height="80"></canvas>
  </div>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-800"><h2 class="text-white font-semibold">{$tRefTitle}</h2></div>
      <table class="w-full text-sm">
        <thead><tr class="border-b border-gray-800 bg-gray-800/30">
          <th class="px-4 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">{$tColSrc}</th>
          <th class="px-4 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">{$tColClk}</th>
        </tr></thead>
        <tbody>{$refRows}</tbody>
      </table>
    </div>
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-800"><h2 class="text-white font-semibold">{$tRecTitle}</h2></div>
      <div class="overflow-y-auto max-h-80">
        <table class="w-full text-sm">
          <thead class="sticky top-0 bg-gray-900"><tr class="border-b border-gray-800 bg-gray-800/50">
            <th class="px-4 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">{$tColTime}</th>
            <th class="px-4 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">{$tColIp}</th>
            <th class="px-4 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">{$tColRef}</th>
          </tr></thead>
          <tbody>{$clickRows}</tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('clicksChart'),{
  type:'line',
  data:{labels:{$chartLabels},datasets:[{label:'Clicks',data:{$chartVals},borderColor:'#818cf8',backgroundColor:'rgba(99,102,241,0.08)',borderWidth:2.5,pointBackgroundColor:'#818cf8',pointBorderColor:'#0d0d18',pointBorderWidth:2,pointRadius:5,pointHoverRadius:7,tension:0.4,fill:true}]},
  options:{responsive:true,plugins:{legend:{display:false},tooltip:{backgroundColor:'#1f2937',borderColor:'#374151',borderWidth:1,titleColor:'#f9fafb',bodyColor:'#9ca3af',padding:10}},scales:{y:{beginAtZero:true,grid:{color:'#1a1f2e'},ticks:{color:'#6b7280',precision:0}},x:{grid:{color:'#1a1f2e'},ticks:{color:'#6b7280'}}}}
});
</script>
HTML;
    return renderLayout($tTitle, $content);
}

// ===== VIEW: EDIT =====
function renderEdit(array $link, string $error = ''): string {
    $base      = BASE_URL;
    $code      = h($link['short_code']);
    $tTitle    = t('edit.title') . ': ' . h($link['short_code']);
    $tOrigUrl  = t('edit.original_url');
    $tSlug     = t('edit.slug');
    $tNewPass  = t('edit.new_password');
    $tPassPh   = !empty($link['password']) ? t('edit.password.has') : t('edit.password.none');
    $tOgSec    = t('edit.og.section');
    $tOgTitle  = t('edit.og_title');
    $tOgDesc   = t('edit.og_desc');
    $tOgImg    = t('edit.og_image');
    $tExpires  = t('edit.expires_at');
    $tStatLbl  = t('edit.status.label');
    $tStatAct  = t('edit.status.active');
    $tSave     = t('edit.save');
    $tCancel   = t('edit.cancel');

    $origUrl   = h($link['original_url']);
    $slug      = h($link['short_code']);
    $ogTitle   = h($link['og_title'] ?? '');
    $ogDesc    = h($link['og_description'] ?? '');
    $ogImage   = h($link['og_image_url'] ?? '');
    $expiresAt = $link['expires_at'] ? date('Y-m-d\TH:i', strtotime($link['expires_at'])) : '';
    $checked   = $link['is_active'] ? 'checked' : '';

    $errHtml = $error
        ? '<div class="flex items-start gap-2 bg-red-950/60 border border-red-800/60 text-red-300 px-4 py-3 rounded-xl text-sm mb-5"><svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>' . h($error) . '</span></div>'
        : '';

    $content = <<<HTML
<div class="max-w-2xl mx-auto px-4 py-10">
  <div class="flex items-center gap-4 mb-8">
    <a href="{$base}/dashboard" class="text-gray-600 hover:text-white transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <h1 class="text-2xl font-bold text-white">{$tTitle}</h1>
  </div>
  <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-7 shadow-xl">
    {$errHtml}
    <form method="POST" action="{$base}/edit/{$code}" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">{$tOrigUrl} <span class="text-red-400">*</span></label>
        <input type="url" name="original_url" required value="{$origUrl}"
               class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">{$tSlug}</label>
        <div class="flex rounded-xl overflow-hidden border border-gray-700 focus-within:ring-2 focus-within:ring-indigo-500/70 transition">
          <span class="inline-flex items-center px-3 bg-gray-800 text-gray-500 text-sm border-r border-gray-700 select-none">/</span>
          <input type="text" name="custom_slug" value="{$slug}" pattern="[a-zA-Z0-9_-]*"
                 class="flex-1 px-4 py-3 bg-gray-800/80 text-white focus:outline-none">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">{$tNewPass}</label>
        <input type="password" name="password" autocomplete="new-password"
               class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition"
               placeholder="{$tPassPh}">
      </div>
      <div class="border-t border-gray-800/80 pt-5">
        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-4">{$tOgSec}</p>
        <div class="space-y-4">
          <div><label class="block text-sm text-gray-400 mb-2">{$tOgTitle}</label>
            <input type="text" name="og_title" value="{$ogTitle}" class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition"></div>
          <div><label class="block text-sm text-gray-400 mb-2">{$tOgDesc}</label>
            <textarea name="og_description" rows="2" class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition resize-none">{$ogDesc}</textarea></div>
          <div><label class="block text-sm text-gray-400 mb-2">{$tOgImg}</label>
            <input type="url" name="og_image_url" value="{$ogImage}" class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition"></div>
        </div>
      </div>
      <div class="flex flex-wrap gap-4 border-t border-gray-800/80 pt-5">
        <div class="flex-1 min-w-48">
          <label class="block text-sm text-gray-400 mb-2">{$tExpires}</label>
          <input type="datetime-local" name="expires_at" value="{$expiresAt}"
                 class="w-full px-4 py-3 bg-gray-800/80 border border-gray-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition">
        </div>
        <div>
          <label class="block text-sm text-gray-400 mb-2">{$tStatLbl}</label>
          <label class="flex items-center gap-3 h-12 cursor-pointer select-none">
            <div class="relative">
              <input type="checkbox" name="is_active" {$checked} class="sr-only peer">
              <div class="w-11 h-6 bg-gray-700 peer-checked:bg-indigo-600 rounded-full transition-colors"></div>
              <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5 shadow"></div>
            </div>
            <span class="text-gray-300 text-sm">{$tStatAct}</span>
          </label>
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="flex-1 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-semibold rounded-xl transition-all shadow-lg shadow-indigo-900/30">{$tSave}</button>
        <a href="{$base}/dashboard" class="px-6 py-3 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl text-gray-400 hover:text-white text-sm font-medium transition-colors flex items-center">{$tCancel}</a>
      </div>
    </form>
  </div>
</div>
HTML;
    return renderLayout($tTitle, $content);
}

// ===== VIEW: SETTINGS =====
function renderSettings(string $success = '', string $error = ''): string {
    $base       = BASE_URL;
    $tTitle     = t('settings.title');
    $tSub       = t('settings.subtitle');
    $tRole      = t('settings.account.role');
    $tStatus    = t('settings.account.status');
    // Đếm số token remember đang hoạt động
    $activeTokens = (int) getDB()
        ->query("SELECT COUNT(*) FROM remember_tokens WHERE expires_at > datetime('now')")
        ->fetchColumn();
    $tRevokeTitle = t('settings.revoke_title');
    $tRevokeDesc  = t('settings.revoke_desc', $activeTokens);
    $tRevokeBtn   = t('settings.revoke_btn');
    $tPwTitle   = t('settings.pw.title');
    $tCurrent   = t('settings.pw.current');
    $tNew       = t('settings.pw.new');
    $tNewHint   = t('settings.pw.new.hint');
    $tConfirm   = t('settings.pw.confirm');
    $tConfirmPh = t('settings.pw.confirm.ph');
    $tSubmit    = t('settings.pw.submit');
    $tSaving    = t('settings.pw.saving');
    $tMatch     = t('settings.pw.match');
    $tNoMatch   = t('settings.pw.nomatch');
    $tStr0      = t('settings.pw.str.0');
    $tStr1      = t('settings.pw.str.1');
    $tStr2      = t('settings.pw.str.2');
    $tStr3      = t('settings.pw.str.3');
    $tNote      = t('settings.note.body');

    $alertHtml = '';
    if ($success) {
        $alertHtml = '<div class="flex items-start gap-3 bg-emerald-950/60 border border-emerald-800/60 text-emerald-300 px-4 py-4 rounded-xl text-sm mb-6">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>' . h($success) . '</span></div>';
    } elseif ($error) {
        $alertHtml = '<div class="flex items-start gap-3 bg-red-950/60 border border-red-800/60 text-red-300 px-4 py-4 rounded-xl text-sm mb-6">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>' . h($error) . '</span></div>';
    }

    $content = <<<HTML
<div class="max-w-xl mx-auto px-4 py-10">
  <div class="mb-8">
    <h1 class="text-3xl font-bold text-white tracking-tight">{$tTitle}</h1>
    <p class="text-gray-500 mt-1">{$tSub}</p>
  </div>
  <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-6 mb-6 flex items-center gap-4">
    <div class="w-14 h-14 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center shrink-0">
      <svg class="w-7 h-7 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    </div>
    <div>
      <p class="text-white font-semibold text-lg">admin</p>
      <p class="text-gray-500 text-sm">{$tRole} • {$tStatus}</p>
    </div>
    <div class="ml-auto"><span class="px-2.5 py-1 bg-emerald-950/60 border border-emerald-800/60 text-emerald-400 text-xs font-medium rounded-full">Active</span></div>
  </div>
  <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-6 shadow-xl">
    <div class="flex items-center gap-3 mb-6">
      <div class="w-9 h-9 rounded-xl bg-violet-600/20 border border-violet-500/30 flex items-center justify-center">
        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
      </div>
      <h2 class="text-white font-semibold">{$tPwTitle}</h2>
    </div>
    {$alertHtml}
    <form method="POST" action="{$base}/settings" id="settingsForm" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">{$tCurrent} <span class="text-red-400">*</span></label>
        <div class="relative">
          <input type="password" name="current_password" id="p0" required autofocus
                 class="w-full px-4 py-3 pr-11 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition" placeholder="••••••••">
          <button type="button" onclick="tp('p0')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 p-1 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">{$tNew} <span class="text-red-400">*</span></label>
        <div class="relative">
          <input type="password" name="new_password" id="p1" required minlength="6" oninput="chkStr(this.value)"
                 class="w-full px-4 py-3 pr-11 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition" placeholder="{$tNewHint}">
          <button type="button" onclick="tp('p1')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 p-1 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>
        </div>
        <div id="strBox" style="display:none" class="mt-2 space-y-1">
          <div class="flex gap-1">
            <div id="s0" class="h-1 flex-1 rounded-full bg-gray-700 transition-colors"></div>
            <div id="s1" class="h-1 flex-1 rounded-full bg-gray-700 transition-colors"></div>
            <div id="s2" class="h-1 flex-1 rounded-full bg-gray-700 transition-colors"></div>
            <div id="s3" class="h-1 flex-1 rounded-full bg-gray-700 transition-colors"></div>
          </div>
          <p id="strLbl" class="text-xs text-gray-600"></p>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">{$tConfirm} <span class="text-red-400">*</span></label>
        <div class="relative">
          <input type="password" name="confirm_password" id="p2" required oninput="chkMatch()"
                 class="w-full px-4 py-3 pr-11 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/70 focus:border-indigo-500 transition" placeholder="{$tConfirmPh}">
          <button type="button" onclick="tp('p2')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 p-1 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>
        </div>
        <p id="matchMsg" class="text-xs mt-1.5 hidden"></p>
      </div>
      <button type="submit" id="saveBtn" class="w-full py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-semibold rounded-xl transition-all shadow-lg shadow-indigo-900/30">{$tSubmit}</button>
    </form>
    <div class="mt-6 p-4 bg-gray-800/50 border border-gray-700/50 rounded-xl">
      <p class="text-xs text-gray-600 leading-relaxed">{$tNote}</p>
    </div>
  </div>

  <!-- Active sessions card -->
  <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-6 mt-6 shadow-xl">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-9 h-9 rounded-xl bg-amber-600/20 border border-amber-500/30 flex items-center justify-center">
        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
        </svg>
      </div>
      <h2 class="text-white font-semibold">{$tRevokeTitle}</h2>
      <span class="ml-auto inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold bg-amber-600/20 text-amber-400 border border-amber-500/30">{$activeTokens}</span>
    </div>
    <p class="text-sm text-gray-400 mb-5 leading-relaxed">{$tRevokeDesc}</p>
    <form method="POST" action="{$base}/settings/revoke-all" onsubmit="return confirm('{$tRevokeBtn}?')">
      <button type="submit" class="flex items-center gap-2 px-4 py-2.5 bg-red-950/60 border border-red-800/60 hover:bg-red-900/60 text-red-400 font-medium text-sm rounded-xl transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        {$tRevokeBtn}
      </button>
    </form>
  </div>
</div>
<script>
const _S={match:'{$tMatch}',nomatch:'{$tNoMatch}',str:['{$tStr0}','{$tStr1}','{$tStr2}','{$tStr3}'],saving:'{$tSaving}',submit:'{$tSubmit}'};
function tp(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}
function chkStr(v){
  const box=document.getElementById('strBox'),lbl=document.getElementById('strLbl');
  if(!v){box.style.display='none';return;}box.style.display='block';
  let sc=0;if(v.length>=8)sc++;if(/[A-Z]/.test(v))sc++;if(/[0-9]/.test(v))sc++;if(/[^A-Za-z0-9]/.test(v))sc++;
  const col=['bg-red-500','bg-orange-500','bg-yellow-500','bg-emerald-500'];
  const txt=['text-red-400','text-orange-400','text-yellow-400','text-emerald-400'];
  for(let i=0;i<4;i++)document.getElementById('s'+i).className='h-1 flex-1 rounded-full transition-colors '+(i<sc?col[sc-1]:'bg-gray-700');
  lbl.className='text-xs '+(txt[sc-1]||'text-gray-600');lbl.textContent=_S.str[sc-1]||'';
}
function chkMatch(){
  const p1=document.getElementById('p1').value,p2=document.getElementById('p2').value,m=document.getElementById('matchMsg');
  if(!p2){m.classList.add('hidden');return;}m.classList.remove('hidden');
  if(p1===p2){m.className='text-xs mt-1.5 text-emerald-400';m.textContent=_S.match;}
  else{m.className='text-xs mt-1.5 text-red-400';m.textContent=_S.nomatch;}
}
document.getElementById('settingsForm').addEventListener('submit',function(e){
  if(document.getElementById('p1').value!==document.getElementById('p2').value){e.preventDefault();document.getElementById('p2').focus();return;}
  const btn=document.getElementById('saveBtn');btn.disabled=true;btn.textContent=_S.saving;
});
</script>
HTML;
    return renderLayout($tTitle, $content);
}

// ===== VIEW: ANALYTICS =====
function renderAnalytics(array $d): string {
    $base = BASE_URL;

    $tTitle   = t('analytics.title');
    $tSub     = t('analytics.subtitle');
    $tLnkList = t('analytics.link_list');
    $tCLinks  = t('analytics.card.links');
    $tCActive = t('analytics.card.active', number_format($d['active_links']));
    $tCClicks = t('analytics.card.clicks');
    $tCUniq   = t('analytics.card.unique_sub', number_format($d['total_unique']));
    $tCToday  = t('analytics.card.today');
    $tCMonth  = t('analytics.card.month');
    $tCWeekSb = t('analytics.card.week_sub', number_format($d['week_clicks']));

    $diff     = $d['today_clicks'] - $d['yest_clicks'];
    $diffSign = $diff >= 0 ? '+' : '';
    $diffClr  = $diff >= 0 ? 'text-emerald-400' : 'text-red-400';
    $tCVsYday = t('analytics.card.vs_yday', $diffSign . $diff, '');
    $diffHtml = '<span class="text-xs ' . $diffClr . ' font-medium">' . $diffSign . $diff . ' ' . $tCVsYday . '</span>';

    $tTbHour  = t('analytics.tab.hour');
    $tTbHrSub = t('analytics.tab.hour.sub');
    $tTbDay   = t('analytics.tab.day');
    $tTbDaSub = t('analytics.tab.day.sub');
    $tTbWeek  = t('analytics.tab.week');
    $tTbWkSub = t('analytics.tab.week.sub');
    $tTbMonth = t('analytics.tab.month');
    $tTbMoSub = t('analytics.tab.month.sub');

    $tChHour  = t('analytics.chart.hour');
    $tChDay   = t('analytics.chart.day');
    $tChWeek  = t('analytics.chart.week');
    $tChMonth = t('analytics.chart.month');

    $tTopTitle = t('analytics.top.title');
    $tColRank  = t('analytics.col.rank');
    $tColSlug  = t('analytics.col.slug');
    $tColUrl   = t('analytics.col.url');
    $tColClk   = t('analytics.col.clicks');
    $tColUniq  = t('analytics.col.unique');
    $tColDet   = t('analytics.col.detail');
    $tNoData   = t('analytics.no_data');

    $totalLinks  = number_format($d['total_links']);
    $totalClicks = number_format($d['total_clicks']);
    $todayClicks = number_format($d['today_clicks']);
    $monthClicks = number_format($d['month_clicks']);

    $hourLabels  = json_encode(array_map(fn($h) => $h . ':00', range(0, 23)));
    $hourVals    = json_encode(array_values($d['hour_data']));
    $dayLabels   = json_encode(array_map(fn($k) => date('d/m', strtotime($k)), array_keys($d['day_data'])));
    $dayVals     = json_encode(array_values($d['day_data']));
    $weekLabels  = json_encode(array_keys($d['week_data']));
    $weekVals    = json_encode(array_values($d['week_data']));
    $monthLabels = json_encode(array_keys($d['month_data']));
    $monthVals   = json_encode(array_values($d['month_data']));

    $topRows = '';
    foreach ($d['top_links'] as $i => $lnk) {
        $rank = $i + 1;
        $rc   = match($rank) { 1 => 'text-yellow-400', 2 => 'text-gray-300', 3 => 'text-amber-600', default => 'text-gray-600' };
        $su   = BASE_URL . '/' . h($lnk['short_code']);
        $tr   = mb_strimwidth($lnk['original_url'], 0, 50, '…');
        $topRows .= '<tr class="border-b border-gray-800/60 hover:bg-gray-800/30 transition-colors">
            <td class="px-4 py-3 text-center font-bold ' . $rc . '">' . $rank . '</td>
            <td class="px-4 py-3"><a href="' . $su . '" target="_blank" class="text-indigo-400 hover:text-indigo-300 font-mono text-sm">' . h($lnk['short_code']) . '</a></td>
            <td class="px-4 py-3 text-gray-500 text-xs truncate max-w-xs" title="' . h($lnk['original_url']) . '">' . h($tr) . '</td>
            <td class="px-4 py-3 text-right text-white font-semibold tabular-nums">' . number_format($lnk['click_count']) . '</td>
            <td class="px-4 py-3 text-right text-indigo-400 tabular-nums">' . number_format($lnk['unique_clicks']) . '</td>
            <td class="px-4 py-3 text-center"><a href="' . $base . '/stats/' . h($lnk['short_code']) . '" class="px-2 py-1 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-xs text-gray-400 hover:text-white transition-colors">' . $tColDet . '</a></td>
        </tr>';
    }
    if (!$topRows) $topRows = '<tr><td colspan="6" class="px-4 py-10 text-center text-gray-700">' . $tNoData . '</td></tr>';

    $content = <<<HTML
<div class="max-w-7xl mx-auto px-4 py-10">
  <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
      <h1 class="text-3xl font-bold text-white tracking-tight">{$tTitle}</h1>
      <p class="text-gray-500 mt-1">{$tSub}</p>
    </div>
    <a href="{$base}/dashboard" class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-800 hover:bg-gray-700 border border-gray-700 text-gray-300 hover:text-white rounded-xl text-sm font-medium transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
      {$tLnkList}
    </a>
  </div>
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-5">
      <div class="flex items-center justify-between mb-3">
        <p class="text-gray-600 text-xs uppercase tracking-wider font-semibold">{$tCLinks}</p>
        <span class="w-8 h-8 rounded-lg bg-indigo-600/20 flex items-center justify-center">
          <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101"/></svg>
        </span>
      </div>
      <p class="text-3xl font-bold text-white">{$totalLinks}</p>
      <p class="text-xs text-emerald-400 mt-1">{$tCActive}</p>
    </div>
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-5">
      <div class="flex items-center justify-between mb-3">
        <p class="text-gray-600 text-xs uppercase tracking-wider font-semibold">{$tCClicks}</p>
        <span class="w-8 h-8 rounded-lg bg-violet-600/20 flex items-center justify-center">
          <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5"/></svg>
        </span>
      </div>
      <p class="text-3xl font-bold text-white">{$totalClicks}</p>
      <p class="text-xs text-gray-600 mt-1">{$tCUniq}</p>
    </div>
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-5">
      <div class="flex items-center justify-between mb-3">
        <p class="text-gray-600 text-xs uppercase tracking-wider font-semibold">{$tCToday}</p>
        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 flex items-center justify-center">
          <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3"/></svg>
        </span>
      </div>
      <p class="text-3xl font-bold text-white">{$todayClicks}</p>
      <p class="mt-1">{$diffHtml}</p>
    </div>
    <div class="bg-gray-900/80 border border-gray-800 rounded-2xl p-5">
      <div class="flex items-center justify-between mb-3">
        <p class="text-gray-600 text-xs uppercase tracking-wider font-semibold">{$tCMonth}</p>
        <span class="w-8 h-8 rounded-lg bg-amber-600/20 flex items-center justify-center">
          <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </span>
      </div>
      <p class="text-3xl font-bold text-white">{$monthClicks}</p>
      <p class="text-xs text-gray-600 mt-1">{$tCWeekSb}</p>
    </div>
  </div>

  <div class="bg-gray-900/80 border border-gray-800 rounded-2xl overflow-hidden shadow-xl mb-6">
    <div class="flex overflow-x-auto border-b border-gray-800">
      <button onclick="swChart('hour')" id="ctab-hour" class="px-5 py-3.5 text-sm font-medium transition-colors border-b-2 border-indigo-500 text-indigo-400 bg-indigo-600/10 whitespace-nowrap">
        {$tTbHour} <span class="text-xs opacity-60">{$tTbHrSub}</span>
      </button>
      <button onclick="swChart('day')" id="ctab-day" class="px-5 py-3.5 text-sm font-medium transition-colors border-b-2 border-transparent text-gray-500 hover:text-gray-300 whitespace-nowrap">
        {$tTbDay} <span class="text-xs opacity-60">{$tTbDaSub}</span>
      </button>
      <button onclick="swChart('week')" id="ctab-week" class="px-5 py-3.5 text-sm font-medium transition-colors border-b-2 border-transparent text-gray-500 hover:text-gray-300 whitespace-nowrap">
        {$tTbWeek} <span class="text-xs opacity-60">{$tTbWkSub}</span>
      </button>
      <button onclick="swChart('month')" id="ctab-month" class="px-5 py-3.5 text-sm font-medium transition-colors border-b-2 border-transparent text-gray-500 hover:text-gray-300 whitespace-nowrap">
        {$tTbMonth} <span class="text-xs opacity-60">{$tTbMoSub}</span>
      </button>
    </div>
    <div class="p-6">
      <p id="chartTitle" class="text-sm text-gray-500 mb-4">{$tChHour}</p>
      <canvas id="mainChart" height="90"></canvas>
    </div>
  </div>

  <div class="bg-gray-900/80 border border-gray-800 rounded-2xl overflow-hidden shadow-xl">
    <div class="px-6 py-4 border-b border-gray-800">
      <h2 class="text-white font-semibold">{$tTopTitle}</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-gray-800 bg-gray-800/30">
          <th class="px-4 py-3 text-center text-xs text-gray-600 uppercase tracking-wider w-10">{$tColRank}</th>
          <th class="px-4 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">{$tColSlug}</th>
          <th class="px-4 py-3 text-left text-xs text-gray-600 uppercase tracking-wider">{$tColUrl}</th>
          <th class="px-4 py-3 text-right text-xs text-gray-600 uppercase tracking-wider">{$tColClk}</th>
          <th class="px-4 py-3 text-right text-xs text-gray-600 uppercase tracking-wider">{$tColUniq}</th>
          <th class="px-4 py-3 text-center text-xs text-gray-600 uppercase tracking-wider">{$tColDet}</th>
        </tr></thead>
        <tbody>{$topRows}</tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const DS={
  hour:{labels:{$hourLabels},data:{$hourVals},title:'{$tChHour}',color:'#818cf8'},
  day:{labels:{$dayLabels},data:{$dayVals},title:'{$tChDay}',color:'#34d399'},
  week:{labels:{$weekLabels},data:{$weekVals},title:'{$tChWeek}',color:'#a78bfa'},
  month:{labels:{$monthLabels},data:{$monthVals},title:'{$tChMonth}',color:'#fb923c'},
};
const ctx=document.getElementById('mainChart').getContext('2d');
let chart=null;
function buildChart(k){
  const d=DS[k];if(chart)chart.destroy();
  chart=new Chart(ctx,{
    type:k==='hour'?'bar':'line',
    data:{labels:d.labels,datasets:[{label:'Clicks',data:d.data,borderColor:d.color,backgroundColor:k==='hour'?d.color+'55':d.color+'18',borderWidth:k==='hour'?0:2.5,borderRadius:k==='hour'?4:0,pointBackgroundColor:d.color,pointBorderColor:'#0d0d18',pointBorderWidth:2,pointRadius:k==='hour'?0:4,pointHoverRadius:6,tension:0.4,fill:k!=='hour'}]},
    options:{responsive:true,plugins:{legend:{display:false},tooltip:{backgroundColor:'#1f2937',borderColor:'#374151',borderWidth:1,titleColor:'#f9fafb',bodyColor:'#9ca3af',padding:10,callbacks:{label:c=>' '+c.parsed.y+' clicks'}}},scales:{y:{beginAtZero:true,grid:{color:'#1a1f2e'},ticks:{color:'#6b7280',precision:0}},x:{grid:{color:'#1a1f2e'},ticks:{color:'#6b7280',maxRotation:45}}}}
  });
}
const TA='px-5 py-3.5 text-sm font-medium transition-colors border-b-2 border-indigo-500 text-indigo-400 bg-indigo-600/10 whitespace-nowrap';
const TI='px-5 py-3.5 text-sm font-medium transition-colors border-b-2 border-transparent text-gray-500 hover:text-gray-300 whitespace-nowrap';
function swChart(k){
  ['hour','day','week','month'].forEach(t=>document.getElementById('ctab-'+t).className=t===k?TA:TI);
  document.getElementById('chartTitle').textContent=DS[k].title;
  buildChart(k);
}
buildChart('hour');
</script>
HTML;
    return renderLayout($tTitle, $content);
}

// ===== VIEW: ABOUT =====
function renderAbout(): string {
    $base = BASE_URL;

    $tTitle       = t('about.title');
    $tHeroTitle   = t('about.hero.title');
    $tHeroIntro   = t('about.hero.intro');
    $tHeroBy      = t('about.hero.by');
    $tHeroStudio  = t('about.hero.studio');
    $tHeroTagline = t('about.hero.tagline');
    $tFeatTitle   = t('about.feat.title');
    $tWhyTitle    = t('about.why.title');
    $tWhyDesc     = t('about.why.desc');
    $tWhyTagline  = t('about.why.tagline');
    $tBrTitle     = t('about.backrun.title');
    $tBrDesc1     = t('about.backrun.desc1');
    $tBrDesc2     = t('about.backrun.desc2');
    $tBrDesc3     = t('about.backrun.desc3');
    $tBrLink      = t('about.backrun.link');

    $features = [
        ['🔗', t('about.feat.1.name'), t('about.feat.1.desc')],
        ['🔒', t('about.feat.2.name'), t('about.feat.2.desc')],
        ['📊', t('about.feat.3.name'), t('about.feat.3.desc')],
        ['🖼️', t('about.feat.4.name'), t('about.feat.4.desc')],
        ['📱', t('about.feat.5.name'), t('about.feat.5.desc')],
        ['⚡', t('about.feat.6.name'), t('about.feat.6.desc')],
    ];

    $featHtml = '';
    foreach ($features as [$icon, $name, $desc]) {
        $featHtml .= '<div class="flex items-start gap-3 py-2.5">
            <span class="text-lg shrink-0 w-6 text-center">' . $icon . '</span>
            <div>
                <span class="text-white font-medium text-sm">' . h($name) . '</span>
                <span class="text-gray-500 text-sm"> — ' . h($desc) . '</span>
            </div>
        </div>';
    }

    $content = <<<HTML
<div class="max-w-3xl mx-auto px-4 py-14">

  <!-- Hero -->
  <div class="mb-12">
    <div class="flex items-center gap-4 mb-6">
      <div class="w-12 h-12 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center shrink-0">
        <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
        </svg>
      </div>
      <h1 class="text-2xl font-black text-white tracking-tight">{$tHeroTitle}</h1>
    </div>
    <p class="text-gray-400 leading-relaxed mb-4">{$tHeroIntro}</p>
    <p class="text-gray-400 leading-relaxed mb-4">
      {$tHeroBy} <a href="https://backrun.co" target="_blank" rel="noopener" class="text-indigo-400 hover:text-indigo-300 font-semibold transition-colors">Backrun.co</a> — {$tHeroStudio}
    </p>
    <p class="text-gray-500 leading-relaxed text-sm">{$tHeroTagline}</p>
  </div>

  <!-- Features -->
  <div class="bg-gray-900/60 border border-gray-800 rounded-2xl p-6 mb-6">
    <h2 class="text-white font-bold mb-5 flex items-center gap-2">
      <span class="w-5 h-5 rounded-md bg-indigo-600/30 flex items-center justify-center text-indigo-400 text-xs">✦</span>
      {$tFeatTitle}
    </h2>
    <div class="divide-y divide-gray-800/60">
      {$featHtml}
    </div>
  </div>

  <!-- Why MyShort -->
  <div class="bg-gray-900/60 border border-gray-800 rounded-2xl p-6 mb-6">
    <h2 class="text-white font-bold mb-4">{$tWhyTitle}</h2>
    <p class="text-gray-400 text-sm leading-relaxed mb-4">{$tWhyDesc}</p>
    <p class="text-indigo-300 text-sm font-medium italic border-l-2 border-indigo-600/60 pl-4">{$tWhyTagline}</p>
  </div>

  <!-- About Backrun -->
  <div class="bg-gray-900/60 border border-gray-800 rounded-2xl p-6">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-8 h-8 rounded-xl bg-violet-600/20 border border-violet-500/30 flex items-center justify-center shrink-0">
        <svg class="w-4 h-4 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
        </svg>
      </div>
      <h2 class="text-white font-bold">{$tBrTitle}</h2>
    </div>
    <p class="text-gray-400 text-sm leading-relaxed mb-3">{$tBrDesc1}</p>
    <p class="text-gray-500 text-sm leading-relaxed mb-3">{$tBrDesc2}</p>
    <p class="text-gray-500 text-sm leading-relaxed mb-5">{$tBrDesc3}</p>
    <a href="https://backrun.co" target="_blank" rel="noopener"
       class="inline-flex items-center gap-2 px-4 py-2 bg-violet-950/60 border border-violet-800/60 hover:bg-violet-900/60 text-violet-300 hover:text-violet-200 text-sm font-medium rounded-xl transition-colors">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
      {$tBrLink}
    </a>
  </div>

</div>
HTML;
    return renderLayout($tTitle . ' — MyShort', $content);
}

// ===== VIEW: 404 =====
function renderNotFound(): string {
    $base    = BASE_URL;
    $tTitle  = t('404.title');
    $tMsg    = t('404.message');
    $tBtn    = t('404.button');
    $content = <<<HTML
<div class="min-h-screen flex items-center justify-center px-4">
  <div class="text-center">
    <p class="text-8xl font-black text-gray-800/80 mb-2 leading-none select-none">404</p>
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-indigo-600/15 border border-indigo-500/20 mb-6">
      <svg class="w-10 h-10 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <h1 class="text-2xl font-bold text-white mb-2">{$tTitle}</h1>
    <p class="text-gray-500 mb-8 text-sm">{$tMsg}</p>
    <a href="{$base}/" class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-medium transition-colors shadow-lg shadow-indigo-900/30">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      {$tBtn}
    </a>
  </div>
</div>
HTML;
    return renderLayout($tTitle, $content);
}
