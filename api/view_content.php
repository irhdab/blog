<?php
try {
    require_once 'db.php';

    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $raw = isset($_GET['raw']) && $_GET['raw'] == '1';

    if ($id) {
        // Individual post: Must not be expired
        $sql = "SELECT id, content, created_at, password_hash, burn_on_read FROM writings WHERE id = ? AND (expires_at IS NULL OR expires_at > NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row) {
            $isSingle = true;
            $hasPassword = !empty($row['password_hash']);
            $passwordCorrect = false;

            // Handle Deletion
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
                if ($hasPassword && isset($_POST['password']) && password_verify($_POST['password'], $row['password_hash'])) {
                    $stmt = $pdo->prepare("DELETE FROM writings WHERE id = ?");
                    $stmt->execute([$id]);
                    header("Location: /view");
                    exit;
                } else if (!$hasPassword) {
                    // For non-password protected, maybe just delete? Or require a master key?
                    // Plan says "Delete with Password", so if no password is set, it might not be deletable this way.
                    // Let's stick to the plan: if hasPassword, verify.
                } else {
                    $passwordError = "Incorrect password for deletion.";
                }
            }

            if ($hasPassword) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password']) && (!isset($_POST['action']) || $_POST['action'] !== 'delete')) {
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

            // Raw API Support
            if ($raw && $passwordCorrect) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $row['content'];
                // Self-destruct if flagged
                if (!empty($row['burn_on_read'])) {
                    $stmt = $pdo->prepare("DELETE FROM writings WHERE id = ?");
                    $stmt->execute([$id]);
                }
                exit;
            }

            // Self-destruct if flagged (non-raw view)
            if ($passwordCorrect && !empty($row['burn_on_read'])) {
                $stmt = $pdo->prepare("DELETE FROM writings WHERE id = ?");
                $stmt->execute([$id]);
                $burnMessage = "This post has been burned after reading.";
            }

            $result = [$row]; // Emulate iterable for simple logic in phtml
        } else {
            $result = [];
        }
    } else {
        // List view: Must not be expired AND must be public
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;

        // Fetch limit + 1 to check if there is a next page
        $sql = "SELECT id, content, created_at, password_hash, burn_on_read 
                FROM writings 
                WHERE (expires_at IS NULL OR expires_at > NOW()) 
                AND exposure = 'public' 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $result = [];
        $hasNext = false;
        $count = 0;

        while ($r = $stmt->fetch()) {
            $count++;
            if ($count > $limit) {
                $hasNext = true;
                break;
            }
            // Hide content in list if password protected or burn-on-read
            if (!empty($r['password_hash']) || !empty($r['burn_on_read'])) {
                $r['content'] = !empty($r['burn_on_read']) ? "[Burn on Read]" : "[Password Protected]";
            }
            $result[] = $r;
        }
        $isSingle = false;
        $currentPage = $page;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}



include 'view.phtml';
?>