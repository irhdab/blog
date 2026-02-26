<?php
try {
    require_once 'db.php';
    $raw = isset($_GET['raw']);

    if ($id_raw = $_GET['id'] ?? null) {
        // Individual post: Search by UID or ID (for backward compatibility)
        $isNumeric = is_numeric($id_raw);
        $condition = $isNumeric ? "id = ?" : "uid = ?";

        $sql = "SELECT id, uid, content, title, created_at, expires_at, password_hash, burn_on_read, view_count, view_limit, is_encrypted 
                FROM writings 
                WHERE $condition AND (expires_at IS NULL OR expires_at > NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_raw]);
        $row = $stmt->fetch();

        if ($row) {
            $isSingle = true;
            $hasPassword = !empty($row['password_hash']);
            $passwordCorrect = false;

            // Handle Deletion
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
                if ($hasPassword && isset($_POST['password']) && password_verify($_POST['password'], $row['password_hash'])) {
                    $stmt = $pdo->prepare("DELETE FROM writings WHERE id = ?");
                    $stmt->execute([$row['id']]);
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

            // Increment view count if accessible
            if ($passwordCorrect) {
                $stmt = $pdo->prepare("UPDATE writings SET view_count = view_count + 1 WHERE id = ?");
                $stmt->execute([$row['id']]);
                $row['view_count']++; // Update local row for current view

                // Check view limit or burn_on_read
                $shouldDelete = false;
                if (!empty($row['burn_on_read'])) {
                    $shouldDelete = true;
                    $burnMessage = "This post has been burned after reading.";
                } else if ($row['view_limit'] !== null && $row['view_count'] >= $row['view_limit']) {
                    $shouldDelete = true;
                    $burnMessage = "This post has reached its view limit and has been deleted.";
                }

                if ($shouldDelete) {
                    $stmt = $pdo->prepare("DELETE FROM writings WHERE id = ?");
                    $stmt->execute([$row['id']]);
                }
            }

            // Raw API Support
            if ($raw && $passwordCorrect) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $row['content'];
                exit;
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
        $sql = "SELECT id, uid, content, title, created_at, expires_at, password_hash, burn_on_read, view_count, is_encrypted 
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
    error_log("Database error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}



include 'view.phtml';
?>