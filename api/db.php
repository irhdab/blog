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
?>