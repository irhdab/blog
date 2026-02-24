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
        $password = $data['password'] ?? '';
        $view_limit = isset($data['view_limit']) ? (int) $data['view_limit'] : null;
        $is_encrypted = !empty($data['is_encrypted']);

        if ($view_limit <= 0)
            $view_limit = null;

        $password_hash = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : null;
        $expires_at = null;

        if ($expiration !== 'never') {
            $interval = '';
            if ($expiration === 'burn') {
                $stmt = $pdo->prepare("INSERT INTO writings (content, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, NULL, ?, ?, TRUE, ?, ?)");
                $success = $stmt->execute([$content, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0]);
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
                    $stmt = $pdo->prepare("INSERT INTO writings (content, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, NOW() + CAST(? AS INTERVAL), ?, ?, FALSE, ?, ?)");
                    $success = $stmt->execute([$content, $interval, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO writings (content, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, NULL, ?, ?, FALSE, ?, ?)");
                    $success = $stmt->execute([$content, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0]);
                }
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO writings (content, expires_at, exposure, password_hash, burn_on_read, view_limit, is_encrypted) VALUES (?, NULL, ?, ?, FALSE, ?, ?)");
            $success = $stmt->execute([$content, $exposure, $password_hash, $view_limit, $is_encrypted ? 1 : 0]);
        }

        if ($success) {
            $newId = $pdo->lastInsertId();
            http_response_code(200);
            echo json_encode(["message" => "Content saved successfully.", "id" => $newId]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to save content."]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["message" => "Server logic error: " . $e->getMessage()]);
    } catch (Throwable $t) {
        http_response_code(500);
        echo json_encode(["message" => "Server crash error: " . $t->getMessage()]);
    }
}
?>