<?php
// api/get_token.php - Return the current CSRF token for the session
require_once 'db.php'; // This starts the session and generates the token

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
?>