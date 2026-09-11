<?php
// =============================================================
// PROTEKSI API: Hanya izinkan request dari aplikasi
// =============================================================

// Daftar origin yang diizinkan (tambahkan domain produksi di sini)
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:3001',
    'http://localhost:5173',
    'http://localhost',
    'http://127.0.0.1',
    'http://127.0.0.1:3000',
    'https://test.uydapz.site',
    // 'https://sdn1mulyoagung.sch.id',  // aktifkan untuk produksi
];

// ---- Cek 1: Tolak navigasi langsung browser ----
// Browser modern mengirim Sec-Fetch-Mode: navigate saat user mengetik URL langsung.
// Request fetch/XHR dari React mengirim Sec-Fetch-Mode: cors / same-origin.
$fetchMode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
$fetchDest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';

if ($fetchMode === 'navigate' || $fetchDest === 'document') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => '403 Forbidden: Akses langsung tidak diizinkan.']);
    exit();
}

// ---- Cek 2: CORS — hanya origin yang dikenal ----
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (!empty($origin)) {
    $isAllowed = in_array($origin, $allowedOrigins, true) ||
                 preg_match('/^https?:\/\/(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+)(:\d+)?$/i', $origin) ||
                 preg_match('/^https?:\/\/[a-z0-9.-]+\.sch\.id$/i', $origin);

    if ($isAllowed) {
        header("Access-Control-Allow-Origin: $origin");
        header("Vary: Origin");
    } else {
        // Origin tidak dikenal — tolak tanpa mengirim ACAO header
        // Browser akan otomatis memblokir response (SOP)
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => '403 Forbidden: Origin tidak diizinkan.']);
        exit();
    }
}
// Jika tidak ada Origin header (curl/postman/internal):
// Tetap lanjut — karena require_once juga tidak mengirim Origin.
// Proteksi utama untuk ini ada di layer .htaccess (IP restriction).

header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Credentials: true");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Preflight request (OPTIONS) — jawab dan hentikan
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// =============================================================
// KONEKSI DATABASE (Membaca dari .env / environment)
// =============================================================

$envFiles = [
    dirname(__DIR__, 2) . '/.env',
    dirname(__DIR__, 2) . '/.env.production',
    dirname(__DIR__, 2) . '/.env.local',
];

foreach ($envFiles as $envFile) {
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                $v = trim($v, "\"'");
                putenv("$k=$v");
                $_ENV[$k] = $v;
            }
        }
    }
}

$host     = getenv('DB_HOST') !== false ? getenv('DB_HOST') : ($_ENV['DB_HOST'] ?? 'localhost');
$username = getenv('DB_USER') !== false ? getenv('DB_USER') : ($_ENV['DB_USER'] ?? 'root');
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? '');
$database = getenv('DB_NAME') !== false ? getenv('DB_NAME') : ($_ENV['DB_NAME'] ?? 'db_sdn1');

try {
    $conn = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Database connection failed."
    ]);
    exit();
}
?>
