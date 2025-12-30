<?php
// ===================================================
// CapitalRise - config.php (VERCEL PRODUCTION SAFE)
// ===================================================

// ---------- ERROR HANDLING ----------
if (getenv('VERCEL')) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// ---------- DATABASE (ENV FIRST) ----------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'capitalrise_db');

// ---------- PLATFORM ----------
define('SITE_NAME', 'CapitalRise Trading Platform');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost');
define('ADMIN_EMAIL', 'admin@capitalrise.com');

// ---------- SECURITY ----------
define('SECRET_KEY', getenv('SECRET_KEY') ?: 'change_this_secret');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'change_this_jwt_secret');

// ---------- FILE UPLOAD (NOTE) ----------
// ⚠️ Vercel local upload NOT recommended
define('MAX_FILE_SIZE', 2 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png',
    'image/jpg',
    'application/pdf'
]);

// ---------- DATABASE CONNECTION (SINGLETON) ----------
function getDBConnection() {
    static $db = null;
    if ($db !== null) return $db;

    try {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            throw new Exception($db->connect_error);
        }
        $db->set_charset('utf8mb4');
        return $db;
    } catch (Exception $e) {
        error_log('DB ERROR: ' . $e->getMessage());
        return null;
    }
}

// ---------- HELPERS ----------
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateMobile($mobile) {
    return preg_match('/^[6-9]\d{9}$/', $mobile);
}

// ---------- PASSWORD ----------
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ---------- UNIQUE ID (SECURE) ----------
function generateUniqueId($prefix = '') {
    return $prefix . strtoupper(bin2hex(random_bytes(6)));
}

// ---------- JWT ----------
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

function generateJWT($payload) {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload['iat'] = time();
    $payload['exp'] = time() + (60 * 60 * 24); // 24 hours

    $h = base64UrlEncode(json_encode($header));
    $p = base64UrlEncode(json_encode($payload));
    $s = base64UrlEncode(
        hash_hmac('sha256', "$h.$p", JWT_SECRET, true)
    );

    return "$h.$p.$s";
}

function verifyJWT($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    [$h, $p, $s] = $parts;
    $check = base64UrlEncode(
        hash_hmac('sha256', "$h.$p", JWT_SECRET, true)
    );

    if (!hash_equals($check, $s)) return false;

    $payload = json_decode(base64UrlDecode($p), true);
    if (!$payload || ($payload['exp'] ?? 0) < time()) return false;

    return $payload;
}

// ---------- RESPONSE ----------
function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'time' => time()
    ]);
    exit;
}

// ---------- SETTINGS ----------
function getSetting($key, $default = '') {
    $db = getDBConnection();
    if (!$db) return $default;

    $stmt = $db->prepare(
        "SELECT setting_value FROM platform_settings WHERE setting_key=?"
    );
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $res = $stmt->get_result();

    return ($row = $res->fetch_assoc())
        ? $row['setting_value']
        : $default;
}

// ---------- FINANCE ----------
function calculateDailyTaskIncome($fund) {
    return ($fund * floatval(getSetting('daily_task_income_percent', 5))) / 100;
}

function calculatePerClickIncome($fund) {
    return calculateDailyTaskIncome($fund)
        / intval(getSetting('task_clicks_needed', 15));
}
?>
