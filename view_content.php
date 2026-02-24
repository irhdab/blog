<?php
require_once 'db.php';

try {
    // Fetch all writings from the database
    $sql = "SELECT id, content, created_at FROM writings ORDER BY created_at DESC";
    $result = $pdo->query($sql);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}



include 'view.phtml';
?>