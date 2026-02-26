<?php
// api/get_token.php - Return the current CSRF token for the session
require_once 'db.php'; // This starts the session and generates the token

header('Content-Type: application/json');
echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
?>