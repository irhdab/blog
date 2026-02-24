<?php
try {
    require_once 'db.php';

    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if ($id) {
        // Individual post: Must not be expired
        $sql = "SELECT id, content, created_at, password_hash FROM writings WHERE id = ? AND (expires_at IS NULL OR expires_at > NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row) {
            $isSingle = true;
            $hasPassword = !empty($row['password_hash']);
            $passwordCorrect = false;

            if ($hasPassword) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                    if (password_verify($_POST['password'], $row['password_hash'])) {
                        $passwordCorrect = true;
                    } else {
                        $passwordError = "Incorrect password.";
                    }
                }
            } else {
                $passwordCorrect = true; // No password required
            }

            // If protected and not verified, hide content
            if ($hasPassword && !$passwordCorrect) {
                $row['content'] = null;
            }

            $result = [$row]; // Emulate iterable for simple logic in phtml
        } else {
            $result = [];
        }
    } else {
        // List view: Must not be expired AND must be public
        $sql = "SELECT id, content, created_at, password_hash FROM writings WHERE (expires_at IS NULL OR expires_at > NOW()) AND exposure = 'public' ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
        $result = [];
        while ($r = $stmt->fetch()) {
            // Hide content in list if password protected
            if (!empty($r['password_hash'])) {
                $r['content'] = "[Password Protected]";
            }
            $result[] = $r;
        }
        $isSingle = false;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}



include 'view.phtml';
?>