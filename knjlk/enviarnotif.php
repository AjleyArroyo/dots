<?php
// Firebase server key
$serverKey = 'YOUR_FIREBASE_SERVER_KEY';  // Replace with your Firebase server key

// Get the incoming request data
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['token']) && isset($data['message'])) {
    $token = $data['token'];
    $message = $data['message'];

    // Prepare the payload
    $notification = [
        'title' => 'New Notification',
        'body'  => $message,
        'sound' => 'default'
    ];

    $fields = [
        'to'        => $token,
        'notification' => $notification
    ];

    // Firebase URL
    $url = 'https://fcm.googleapis.com/fcm/send';

    // Headers
    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];

    // Initialize cURL
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

    // Execute cURL and get the response
    $response = curl_exec($ch);

    if ($response === FALSE) {
        die('FCM send failed: ' . curl_error($ch));
    }

    // Close cURL
    curl_close($ch);

    // Return the response
    echo json_encode(['status' => 'success', 'response' => $response]);
}
?>
