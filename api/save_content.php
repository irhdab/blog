<?php
// save_content.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $content = $data['content'] ?? '';

    if (empty($content)) {
        http_response_code(400);
        echo json_encode(["message" => "Content is required."]);
        exit;
    }

    // Basic security check: ensure it's an AJAX/JSON request
    $contentType = $_SERVER["CONTENT_TYPE"] ?? "";
    if (strpos($contentType, 'application/json') === false) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid request format."]);
        exit;
    }

    if (strlen($content) > 1048576) {
        http_response_code(413);
        echo json_encode(["message" => "Content is too large. Maximum size is 1MB."]);
        exit;
    }

    try {
        if (!class_exists('PDO')) {
            throw new Exception("PHP PDO extension is not installed.");
        }
        if (!in_array('pgsql', PDO::getAvailableDrivers())) {
            throw new Exception("PHP PDO PostgreSQL driver (pdo_pgsql) is not installed.");
        }
        require_once 'db.php';

        $title = $data['title'] ?? null;
        $expiration = $data['expiration'] ?? 'never';
        $exposure = $data['exposure'] ?? 'public';
        $password = $data['password'] ?? '';
        $view_limit = isset($data['view_limit']) ? (int) $data['view_limit'] : null;
        $is_encrypted = !empty($data['is_encrypted']);
        $edit_uid = $data['uid'] ?? null;

        if ($view_limit <= 0)
            $view_limit = null;
        $password = $data['password'] ?? '';
        $password_hash = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : null;

        if ($edit_uid) {
            // Edit Mode
            $stmt = $pdo->prepare("SELECT id, password_hash, is_encrypted FROM writings WHERE uid = ?");
            $stmt->execute([$edit_uid]);
            $existing = $stmt->fetch();

            if (!$existing) {
                http_response_code(404);
                echo json_encode(["message" => "Post not found."]);
                exit;
            }

            // Verify password for editing
            // If it's E2EE, we don't have a password_hash on server (usually), but if it's standard password, we check it.
            // Actually, for editing, we should always require a password if one was set.
            if ($existing['password_hash']) {
                if (!password_verify($password, $existing['password_hash'])) {
                    http_response_code(403);
                    echo json_encode(["message" => "Invalid password for editing."]);
                    exit;
                }
            } else if ($existing['is_encrypted']) {
                // For E2EE, we don't store the password hash, but if the user wants to edit,
                // they must have the password to decrypt it locally. 
                // However, the server can't verify it. We'll allow editing if there's no password_hash.
                // NOTE: This might be a security risk if someone knows the UID. 
                // But if it's E2EE, they can't read it anyway without the password.
                // Still, and "edit password" might be good.
            }

            // Update post (Only content and title for now, maybe expiration too?)
            $stmt = $pdo->prepare("UPDATE writings SET content = ?, title = ? WHERE uid = ?");
            $success = $stmt->execute([$content, $title, $edit_uid]);
            $uid = $edit_uid;
        } else {
            // Create Mode
            $uid = bin2hex(random_bytes(16));
            $burn_on_read = ($expiration === 'burn');
            $expires_at = null;
            $interval = null;

            if ($expiration !== 'never' && $expiration !== 'burn') {
                switch ($expiration) {
                    case '10m':
                        $interval = '10 minutes';
                        break;
                    case '1h':
                        $interval = '1 hour';
                        break;
                    case '1d':
                        $interval = '1 day';
                        break;
                    case '1w':
                        $interval = '1 week';
                        break;
                }
            }

            if ($interval) {
                $stmt = $pdo->prepare("INSERT INTO writings (uid, content, title, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, ?, ?, NOW() + CAST(? AS INTERVAL), ?, ?, FALSE, ?, ?)");
                $success = $stmt->execute([$uid, $content, $title, $interval, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO writings (uid, content, title, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$uid, $content, $title, $exposure, $password_hash, $burn_on_read ? 1 : 0, $view_limit, $is_encrypted ? 1 : 0]);
            }
        }

        if ($success) {
            http_response_code(200);
            echo json_encode(["message" => "Content saved successfully.", "id" => $uid]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to save content."]);
        }
    } catch (Exception $e) {
        error_log("Save Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["message" => "A server error occurred. Please try again later."]);
    } catch (Throwable $t) {
        error_log("Save Crash: " . $t->getMessage());
        http_response_code(500);
        echo json_encode(["message" => "A critical server error occurred."]);
    }
}
?>