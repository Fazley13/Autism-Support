<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once '../../config/config.php';
if (!isLoggedIn()) { redirect('auth/login.php'); }


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $session_id = $_POST['session_id'];
    $feedback_score = $_POST['feedback_score'];
    $comments = $_POST['comments'];
    
    $stmt = $conn->prepare("INSERT INTO feedback (session_id, feedback_score, comments) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $session_id, $feedback_score, $comments);

    if ($stmt->execute()) {
        echo "feedback submitted successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
