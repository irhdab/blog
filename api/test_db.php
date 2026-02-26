<?php
require_once 'db.php';
$stmt = $pdo->query("SELECT * FROM writings ORDER BY created_at DESC LIMIT 5");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($posts, JSON_PRETTY_PRINT);
