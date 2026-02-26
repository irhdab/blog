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

        $expiration = $data['expiration'] ?? 'never';
        $exposure = $data['exposure'] ?? 'public';
        $view_limit = isset($data['view_limit']) ? (int) $data['view_limit'] : null;
        $is_encrypted = !empty($data['is_encrypted']);

        // Generate a random UID (UUID v4-like or just random hex)
        $uid = bin2hex(random_bytes(16));

        if ($view_limit <= 0)
            $view_limit = null;

        $password_hash = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : null;
        $expires_at = null;

        if ($expiration !== 'never') {
            $interval = '';
            if ($expiration === 'burn') {
                $stmt = $pdo->prepare("INSERT INTO writings (uid, content, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, ?, NULL, ?, ?, TRUE, ?, ?)");
                $success = $stmt->execute([$uid, $content, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0]);
            } else {
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

                if ($interval) {
                    $stmt = $pdo->prepare("INSERT INTO writings (uid, content, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, ?, NOW() + CAST(? AS INTERVAL), ?, ?, FALSE, ?, ?)");
                    $success = $stmt->execute([$uid, $content, $interval, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO writings (uid, content, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, ?, NULL, ?, ?, FALSE, ?, ?)");
                    $success = $stmt->execute([$uid, $content, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0]);
                }
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO writings (uid, content, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, ?, NULL, ?, ?, FALSE, ?, ?)");
            $success = $stmt->execute([$uid, $content, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0]);
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