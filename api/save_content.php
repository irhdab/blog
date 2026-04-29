<?php
// save_content.php
require_once 'db.php';

function send_json_response($message, $data = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(["message" => $message], $data));
    exit;
}

function validate_input($data) {
    if (empty($data['content'])) {
        send_json_response("Content is required.", [], 400);
    }

    // CSRF Protection
    $clientToken = $data['csrf_token'] ?? '';
    if (empty($clientToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
        send_json_response("Invalid CSRF token.", [], 403);
    }

    // Basic security check: ensure it's an AJAX/JSON request
    $contentType = $_SERVER["CONTENT_TYPE"] ?? "";
    if (strpos($contentType, 'application/json') === false) {
        send_json_response("Invalid request format.", [], 400);
    }

    if (strlen($data['content']) > 1048576) {
        send_json_response("Content is too large. Maximum size is 1MB.", [], 413);
    }
}

function handle_edit_mode($pdo, $data) {
    $uid = $data['uid'];
    $content = $data['content'];
    $title = $data['title'] ?? null;
    $edit_token = $data['edit_token'] ?? null;
    $password = $data['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, password_hash, edit_token_hash FROM writings WHERE uid = ?");
    $stmt->execute([$uid]);
    $existing = $stmt->fetch();

    if (!$existing) {
        send_json_response("Post not found.", [], 404);
    }

    // Verify edit_token
    if (empty($existing['edit_token_hash']) || empty($edit_token) || !password_verify($edit_token, $existing['edit_token_hash'])) {
        send_json_response("Invalid or missing edit token. Unauthorized to edit this post.", [], 403);
    }

    // Verify password if set
    if ($existing['password_hash'] && !password_verify($password, $existing['password_hash'])) {
        send_json_response("Invalid password for editing.", [], 403);
    }

    $stmt = $pdo->prepare("UPDATE writings SET content = ?, title = ? WHERE uid = ?");
    $success = $stmt->execute([$content, $title, $uid]);

    if ($success) {
        send_json_response("Content updated successfully.", ["id" => $uid]);
    } else {
        send_json_response("Failed to update content.", [], 500);
    }
}

function handle_create_mode($pdo, $data) {
    $uid = bin2hex(random_bytes(16));
    $new_edit_token = bin2hex(random_bytes(16));
    $edit_token_hash = password_hash($new_edit_token, PASSWORD_BCRYPT);

    $content = $data['content'];
    $title = $data['title'] ?? null;
    $expiration = $data['expiration'] ?? 'never';
    $exposure = $data['exposure'] ?? 'public';
    $password = $data['password'] ?? '';
    $view_limit = isset($data['view_limit']) ? (int) $data['view_limit'] : null;
    $is_encrypted = !empty($data['is_encrypted']);

    if ($view_limit <= 0) $view_limit = null;
    $password_hash = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : null;

    $burn_on_read = ($expiration === 'burn');
    $interval = null;

    $intervals = [
        '10m' => '10 minutes',
        '1h'  => '1 hour',
        '1d'  => '1 day',
        '1w'  => '1 week'
    ];
    $interval = $intervals[$expiration] ?? null;

    if ($interval) {
        $sql = "INSERT INTO writings (uid, content, title, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted, edit_token_hash) 
                VALUES (?, ?, ?, NOW() + CAST(? AS INTERVAL), ?, ?, FALSE, ?, ?, ?)";
        $params = [$uid, $content, $title, $interval, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0, $edit_token_hash];
    } else {
        $sql = "INSERT INTO writings (uid, content, title, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted, edit_token_hash) 
                VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)";
        $params = [$uid, $content, $title, $exposure, $password_hash, $burn_on_read ? 1 : 0, $view_limit, $is_encrypted ? 1 : 0, $edit_token_hash];
    }

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute($params);

    if ($success) {
        send_json_response("Content saved successfully.", ["id" => $uid, "edit_token" => $new_edit_token]);
    } else {
        send_json_response("Failed to save content.", [], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    validate_input($data);

    if (!check_rate_limit($pdo, "save_content", 5, 60)) {
        send_json_response("Too many requests. Please wait a minute.", [], 429);
    }

    try {
        if (!class_exists('PDO')) throw new Exception("PHP PDO extension missing.");

        if (!empty($data['uid'])) {
            handle_edit_mode($pdo, $data);
        } else {
            handle_create_mode($pdo, $data);
        }
    } catch (Exception $e) {
        error_log("Save Error: " . $e->getMessage());
        send_json_response("A server error occurred.", [], 500);
    } catch (Throwable $t) {
        error_log("Save Crash: " . $t->getMessage());
        send_json_response("A critical error occurred.", [], 500);
    }
}
?>