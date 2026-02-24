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
        $expires_at = null;

        if ($expiration !== 'never') {
            $interval = '';
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
                default:
                    $interval = '';
                    break;
            }

            if ($interval) {
                $stmt = $pdo->prepare("INSERT INTO writings (content, expires_at, exposure) VALUES (?, NOW() + CAST(? AS INTERVAL), ?)");
                $success = $stmt->execute([$content, $interval, $exposure]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO writings (content, expires_at, exposure) VALUES (?, NULL, ?)");
                $success = $stmt->execute([$content, $exposure]);
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO writings (content, expires_at, exposure) VALUES (?, NULL, ?)");
            $success = $stmt->execute([$content, $exposure]);
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