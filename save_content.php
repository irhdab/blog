<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
        $stmt = $pdo->prepare("INSERT INTO writings (content) VALUES (?)");

        if ($stmt->execute([$content])) {
            http_response_code(200);
            echo json_encode(["message" => "Content saved successfully."]);
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