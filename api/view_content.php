<?php
try {
    require_once 'db.php';

    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if ($id) {
        $sql = "SELECT id, content, created_at FROM writings WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt;
        $isSingle = true;
    } else {
        $sql = "SELECT id, content, created_at FROM writings ORDER BY created_at DESC";
        $result = $pdo->query($sql);
        $isSingle = false;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}



include 'view.phtml';
?>