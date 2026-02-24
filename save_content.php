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
        require_once 'db.php';
        $stmt = $pdo->prepare("INSERT INTO writings (content) VALUES (?)");

        if ($stmt->execute([$content])) {
            http_response_code(200);
            echo json_encode(["message" => "Content saved successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to save content."]);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(["message" => "Server error: " . $e->getMessage()]);
    }
}
?>