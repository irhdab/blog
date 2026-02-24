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

$charset = 'utf8mb4';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require;options='endpoint=ep-proud-hill-ai7aun9w'";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = new PDO($dsn, $user, $pass, $options);

// Auto-migration: Ensure columns exist
try {
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
} catch (Exception $e) {
    // Silently handle migration errors in production or log them
    error_log("Migration error: " . $e->getMessage());
}
?>