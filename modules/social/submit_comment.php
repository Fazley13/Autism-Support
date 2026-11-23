<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once '../../config/config.php';
if (!isLoggedIn()) { redirect('auth/login.php'); }

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$postId = $data['post_id'];
$commentContent = $data['comment'];

$stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comment) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $postId, $userId, $commentContent);
$stmt->execute();

$commentId = $stmt->insert_id;
$stmt->close();

$commentQuery = "
    SELECT comments.*, user.username 
    FROM comments
    JOIN user ON comments.user_id = user.user_id
    WHERE comments.comment_id = ?
";

$stmt = $conn->prepare($commentQuery);
$stmt->bind_param('i', $commentId);
$stmt->execute();
$commentResult = $stmt->get_result();
$comment = $commentResult->fetch_assoc();
$stmt->close();

echo json_encode(['success' => true, 'comment' => $comment]);
?>
