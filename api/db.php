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
header("Content-Security-Policy: default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self'; frame-src https://www.youtube.com;");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");

$charset = 'utf8mb4';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require;options='endpoint=ep-proud-hill-ai7aun9w'";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = new PDO($dsn, $user, $pass, $options);

// Helper for CSRF/Rate Limiting
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function check_rate_limit($pdo, $key, $limit, $window_seconds)
{
    // Basic IP based rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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

// Auto-migration: Ensure columns and tables exist
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

    // Check if expires_at column exists
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='writings' AND column_name='expires_at'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE writings ADD COLUMN expires_at TIMESTAMP NULL");
        $pdo->exec("CREATE INDEX idx_writings_expires_at ON writings(expires_at)");
    }

    // Check if exposure column exists
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='writings' AND column_name='exposure'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE writings ADD COLUMN exposure VARCHAR(20) DEFAULT 'public'");
        $pdo->exec("CREATE INDEX idx_writings_exposure ON writings(exposure)");
    }

    // Check if password_hash column exists
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='writings' AND column_name='password_hash'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE writings ADD COLUMN password_hash TEXT NULL");
    }

    // Check if burn_on_read column exists
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='writings' AND column_name='burn_on_read'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE writings ADD COLUMN burn_on_read BOOLEAN DEFAULT FALSE");
    }

    // Check if view_count column exists
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='writings' AND column_name='view_count'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE writings ADD COLUMN view_count INTEGER DEFAULT 0");
    }

    // Check if view_limit column exists
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='writings' AND column_name='view_limit'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE writings ADD COLUMN view_limit INTEGER NULL");
    }

    // Check if is_encrypted column exists
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='writings' AND column_name='is_encrypted'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE writings ADD COLUMN is_encrypted BOOLEAN DEFAULT FALSE");
    }

    // Check if uid column exists (UUID for security)
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='writings' AND column_name='uid'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE writings ADD COLUMN uid VARCHAR(36) UNIQUE NULL");
        $pdo->exec("CREATE INDEX idx_writings_uid ON writings(uid)");
        // Populate existing rows
        $pdo->exec("UPDATE writings SET uid = MD5(id::text || random()::text) WHERE uid IS NULL");
    } else {
        // Optional: Ensure all existing rows have UIDs if the column was added manually
        $stmt = $pdo->query("SELECT 1 FROM writings WHERE uid IS NULL LIMIT 1");
        if ($stmt->fetch()) {
            $pdo->exec("UPDATE writings SET uid = MD5(id::text || random()::text) WHERE uid IS NULL");
        }
    }

    // Check if title column exists
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name='writings' AND column_name='title'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE writings ADD COLUMN title TEXT NULL");
    }
} catch (Exception $e) {
    // Silently handle migration errors in production or log them
    error_log("Migration error: " . $e->getMessage());
}
?>