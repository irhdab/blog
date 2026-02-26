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

    require_once 'db.php';

    // CSRF Protection
    $clientToken = $data['csrf_token'] ?? '';
    if (empty($clientToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
        http_response_code(403);
        echo json_encode(["message" => "Invalid CSRF token."]);
        exit;
    }

    // Rate Limiting: 5 posts per minute
    if (!check_rate_limit($pdo, "save_content", 5, 60)) {
        http_response_code(429);
        echo json_encode(["message" => "Too many requests. Please wait a minute."]);
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
        // require_once 'db.php'; // Already included above for CSRF check

        $title = $data['title'] ?? null;
        $expiration = $data['expiration'] ?? 'never';
        $exposure = $data['exposure'] ?? 'public';
        $password = $data['password'] ?? '';
        $view_limit = isset($data['view_limit']) ? (int) $data['view_limit'] : null;
        $is_encrypted = !empty($data['is_encrypted']);
        $edit_uid = $data['uid'] ?? null;
        $edit_token = $data['edit_token'] ?? null;

        if ($view_limit <= 0)
            $view_limit = null;
        $password_hash = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : null;

        if ($edit_uid) {
            // Edit Mode
            $stmt = $pdo->prepare("SELECT id, password_hash, is_encrypted, edit_token_hash FROM writings WHERE uid = ?");
            $stmt->execute([$edit_uid]);
            $existing = $stmt->fetch();

            if (!$existing) {
                http_response_code(404);
                echo json_encode(["message" => "Post not found."]);
                exit;
            }

            // Verify edit_token for editing updates (protects E2EE posts)
            if (empty($existing['edit_token_hash']) || empty($edit_token) || !password_verify($edit_token, $existing['edit_token_hash'])) {
                http_response_code(403);
                echo json_encode(["message" => "Invalid or missing edit token. Unauthorized to edit this post."]);
                exit;
            }

            // Verify password for editing if it was set (extra layer of security for non-E2EE posts)
            if ($existing['password_hash']) {
                if (!password_verify($password, $existing['password_hash'])) {
                    http_response_code(403);
                    echo json_encode(["message" => "Invalid password for editing."]);
                    exit;
                }
            }

            // Update post (Only content and title for now)
            $stmt = $pdo->prepare("UPDATE writings SET content = ?, title = ? WHERE uid = ?");
            $success = $stmt->execute([$content, $title, $edit_uid]);
            $uid = $edit_uid;
            $new_edit_token = null; // Don't return a new token on edit
        } else {
            // Create Mode
            $uid = bin2hex(random_bytes(16));
            $new_edit_token = bin2hex(random_bytes(16));
            $edit_token_hash = password_hash($new_edit_token, PASSWORD_BCRYPT);

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
                $stmt = $pdo->prepare("INSERT INTO writings (uid, content, title, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted, edit_token_hash) VALUES (?, ?, ?, NOW() + CAST(? AS INTERVAL), ?, ?, FALSE, ?, ?, ?)");
                $success = $stmt->execute([$uid, $content, $title, $interval, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0, $edit_token_hash]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO writings (uid, content, title, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted, edit_token_hash) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$uid, $content, $title, $exposure, $password_hash, $burn_on_read ? 1 : 0, $view_limit, $is_encrypted ? 1 : 0, $edit_token_hash]);
            }
        }

        if ($success) {
            http_response_code(200);
            $response = ["message" => "Content saved successfully.", "id" => $uid];
            if ($new_edit_token) {
                $response["edit_token"] = $new_edit_token;
            }
            echo json_encode($response);
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