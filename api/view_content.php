<?php
try {
    require_once 'db.php';
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $raw = isset($_GET['raw']);
    $json = isset($_GET['json']);

    if ($id_raw = $_GET['id'] ?? null) {
        // Individual post: Search by UID ONLY for security (prevent sequential ID guessing)
        $condition = "uid = ?";

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

            // Handle session or cookie-based unlocking (robust for serverless)
            if (isset($_SESSION['unlocked_' . $row['id']]) || isset($_COOKIE['unlocked_' . $row['uid']])) {
                $passwordCorrect = true;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // CSRF check for all POST actions in view
                $clientToken = $_POST['csrf_token'] ?? '';
                if (empty($clientToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
                    $passwordError = "Invalid session or CSRF token.";
                } else if (!check_rate_limit($pdo, "password_attempt", 10, 60)) {
                    // Rate Limit password attempts: 10 per minute
                    $passwordError = "Too many attempts. Please wait.";
                } else {
                    // Handle Deletion
                    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
                        if ($hasPassword && isset($_POST['password']) && password_verify($_POST['password'], $row['password_hash'])) {
                            $stmt = $pdo->prepare("DELETE FROM writings WHERE id = ?");
                            $stmt->execute([$row['id']]);
                            setcookie('unlocked_' . $row['uid'], '', time() - 3600, '/');
                            header("Location: /view");
                            exit;
                        } else if (!$hasPassword) {
                            // Non-password protected deletion
                        } else {
                            $passwordError = "Incorrect password for deletion.";
                        }
                    }

                    // Handle View Unlock
                    if (isset($_POST['password']) && (!isset($_POST['action']) || $_POST['action'] !== 'delete')) {
                        if (password_verify($_POST['password'], $row['password_hash'])) {
                            $passwordCorrect = true;
                            $_SESSION['unlocked_' . $row['id']] = true;
                            // Set a short-lived cookie for serverless persistence
                            setcookie('unlocked_' . $row['uid'], '1', [
                                'expires' => time() + 3600, // 1 hour
                                'path' => '/',
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                        } else {
                            $passwordError = "Incorrect password.";
                        }
                    }
                }
            } else if (!$hasPassword) {
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
            if ($raw) {
                if ($passwordCorrect) {
                    header('Content-Type: text/plain; charset=utf-8');
                    echo $row['content'];
                    exit;
                } else {
                    http_response_code(401);
                    echo "Unauthorized: Password required for raw access.";
                    exit;
                }
            }

            // JSON Metadata Support (for Edit/Clone)
            if ($json) {
                if ($passwordCorrect) {
                    header('Content-Type: application/json');
                    echo json_encode($row);
                    exit;
                } else {
                    http_response_code(401);
                    echo json_encode(["message" => "Unauthorized"]);
                    exit;
                }
            }

            $result = [$row]; // Emulate iterable for simple logic in phtml
        } else {
            $result = [];
            $isSingle = true;
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
            // Hide content in list if password protected, burn-on-read, or E2EE
            if (!empty($r['password_hash']) || !empty($r['burn_on_read']) || !empty($r['is_encrypted'])) {
                if (!empty($r['burn_on_read'])) {
                    $r['content'] = "[Burn on Read]";
                } else if (!empty($r['is_encrypted'])) {
                    $r['content'] = "[Encrypted (E2EE)]";
                } else {
                    $r['content'] = "[Password Protected]";
                }
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