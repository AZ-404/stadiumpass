<?php
session_start();
require_once 'db.php';

if (isset($_POST['idtoken'])) {
    $id_token = $_POST['idtoken'];
    
    // Verify token with Google
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
    $response = @file_get_contents($url);
    
    if ($response === FALSE) {
        echo json_encode(['success' => false, 'message' => 'Failed to connect to Google']);
        exit;
    }
    
    $payload = json_decode($response, true);

    if (isset($payload['email'])) {
        $email = $payload['email'];
        $name = $payload['name'];

        // Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
        } else {
            // Register new fan user
            $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, '', 'fan')");
            $stmt->bind_param("ss", $name, $email);
            $stmt->execute();
            
            $user_id = $stmt->insert_id;
            $user = ['id' => $user_id, 'name' => $name, 'email' => $email, 'role' => 'fan'];
        }

        // Set sessions
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid Token']);