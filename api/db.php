<?php
// db.php - Centralized database connection logic

$host = getenv('PGHOST');
$db = getenv('PGDATABASE');
$user = getenv('PGUSER');
$pass = getenv('PGPASSWORD');
$port = getenv('PGPORT') ?: "5432";

if (!$host || !$db || !$user || !$pass) {
    throw new \PDOException("Missing database environment variables. Please check your Vercel settings or local environment.");
}

// Set security headers
header("Content-Security-Policy: default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-src https://www.youtube.com;");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require;options='endpoint=ep-proud-hill-ai7aun9w'";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = new PDO($dsn, $user, $pass, $options);

// Helper for CSRF/Rate Limiting
session_start();
if (empty($_COOKIE['csrf_token'])) {
    $token = bin2hex(random_bytes(32));
    setcookie('csrf_token', $token, [
        'expires' => time() + 86400, // 24 hours
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    $_SESSION['csrf_token'] = $token;
} else {
    $_SESSION['csrf_token'] = $_COOKIE['csrf_token'];
}

function check_rate_limit($pdo, $key, $limit, $window_seconds)
{
    // Use HTTP_X_FORWARDED_FOR for Vercel/Cloudflare, fallback to REMOTE_ADDR
    $ip = 'unknown';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ipList[0]);
    } else if (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $identifier = "rl_" . $key . "_" . $ip;

    $stmt = $pdo->prepare("SELECT count FROM rate_limits WHERE identifier = ? AND last_request > (NOW() - CAST(? AS INTERVAL))");
    $stmt->execute([$identifier, $window_seconds . ' seconds']);
    $row = $stmt->fetch();

    if ($row && $row['count'] >= $limit) {
        return false;
    }

    if ($row) {
        $stmt = $pdo->prepare("UPDATE rate_limits SET count = count + 1, last_request = NOW() WHERE identifier = ?");
        $stmt->execute([$identifier]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (identifier, count, last_request) VALUES (?, 1, NOW()) ON CONFLICT (identifier) DO UPDATE SET count = 1, last_request = NOW()");
        $stmt->execute([$identifier]);
    }
    return true;
}

function run_migrations($pdo)
{
    try {
        // Check for rate_limits table
        $stmt = $pdo->query("SELECT 1 FROM information_schema.tables WHERE table_name = 'rate_limits'");
        if (!$stmt->fetch()) {
            $pdo->exec("CREATE TABLE rate_limits (
                id SERIAL PRIMARY KEY,
                identifier VARCHAR(255) UNIQUE NOT NULL,
                count INTEGER DEFAULT 0,
                last_request TIMESTAMP DEFAULT NOW()
            )");
            $pdo->exec("CREATE INDEX idx_rate_limits_identifier ON rate_limits(identifier)");
            $pdo->exec("CREATE INDEX idx_rate_limits_last_request ON rate_limits(last_request)");
        }

        // Helper to add missing columns
        $ensureColumn = function($pdo, $table, $column, $definition) {
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name=? AND column_name=?");
            $stmt->execute([$table, $column]);
            if (!$stmt->fetch()) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
                return true;
            }
            return false;
        };

        $ensureColumn($pdo, 'writings', 'expires_at', "TIMESTAMP NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_writings_expires_at ON writings(expires_at)");

        $ensureColumn($pdo, 'writings', 'exposure', "VARCHAR(20) DEFAULT 'public'");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_writings_exposure ON writings(exposure)");

        $ensureColumn($pdo, 'writings', 'password_hash', "TEXT NULL");
        $ensureColumn($pdo, 'writings', 'burn_on_read', "BOOLEAN DEFAULT FALSE");
        $ensureColumn($pdo, 'writings', 'view_count', "INTEGER DEFAULT 0");
        $ensureColumn($pdo, 'writings', 'view_limit', "INTEGER NULL");
        $ensureColumn($pdo, 'writings', 'is_encrypted', "BOOLEAN DEFAULT FALSE");
        
        if ($ensureColumn($pdo, 'writings', 'uid', "VARCHAR(36) UNIQUE NULL")) {
            $pdo->exec("CREATE INDEX idx_writings_uid ON writings(uid)");
            $pdo->exec("UPDATE writings SET uid = MD5(id::text || random()::text) WHERE uid IS NULL");
        } else {
            // Ensure all existing rows have UIDs
            $stmt = $pdo->query("SELECT 1 FROM writings WHERE uid IS NULL LIMIT 1");
            if ($stmt->fetch()) {
                $pdo->exec("UPDATE writings SET uid = MD5(id::text || random()::text) WHERE uid IS NULL");
            }
        }

        $ensureColumn($pdo, 'writings', 'title', "TEXT NULL");
        $ensureColumn($pdo, 'writings', 'edit_token_hash', "TEXT NULL");

    } catch (Exception $e) {
        error_log("Migration error: " . $e->getMessage());
    }
}

// Run migrations on every request for now to maintain "auto-migration" feature
// In a larger app, this would be move to a CLI script or deployment hook.
run_migrations($pdo);
?>