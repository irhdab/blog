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

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "telegraph";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
http_response_code(500);
echo json_encode(["message" => "Database connection failed."]);
exit;
}

$stmt = $conn->prepare("INSERT INTO writings (content) VALUES (?)");
$stmt->bind_param("s", $content);

if ($stmt->execute()) {
http_response_code(200);
echo json_encode(["message" => "Content saved successfully."]);
} else {
http_response_code(500);
echo json_encode(["message" => "Failed to save content."]);
}

$stmt->close();
$conn->close();
}
?>