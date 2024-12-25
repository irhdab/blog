<?php
$servername = "localhost"; // Replace with your database server
$username = "root";        // Replace with your database username
$password = "";            // Replace with your database password
$dbname = "telegraph";     // Replace with your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all writings from the database
$sql = "SELECT id, content, created_at FROM writings ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Content</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .entry {
            margin-bottom: 20px;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .entry:last-child {
            border-bottom: none;
        }
        .content {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .timestamp {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Saved Content</h1>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="entry">
                    <div class="content"><?php echo nl2br(htmlspecialchars($row['content'])); ?></div>
                    <div class="timestamp">Saved on: <?php echo $row['created_at']; ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No content available.</p>
        <?php endif; ?>
        <?php $conn->close(); ?>
    </div>
</body>
</html>
