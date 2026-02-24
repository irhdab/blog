<?php
try {
    require_once 'db.php';

    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if ($id) {
        // Individual post: Must not be expired
        $sql = "SELECT id, content, created_at FROM writings WHERE id = ? AND (expires_at IS NULL OR expires_at > NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt;
        $isSingle = true;
    } else {
        // List view: Must not be expired AND must be public
        $sql = "SELECT id, content, created_at FROM writings WHERE (expires_at IS NULL OR expires_at > NOW()) AND exposure = 'public' ORDER BY created_at DESC";
        $result = $pdo->query($sql);
        $isSingle = false;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}



include 'view.phtml';
?>