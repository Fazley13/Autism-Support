<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once '../../config/config.php';
if (!isLoggedIn()) { redirect('auth/login.php'); }

$commentId = $_GET['comment_id'];
$userId = $_SESSION['user_id'];

// cmnt ownr or pst ownr
$commentCheckQuery = "SELECT comments.*, post.user_id AS post_owner_id FROM comments
                      JOIN post ON comments.post_id = post.post_id
                      WHERE comments.comment_id = ? AND (comments.user_id = ? OR post.user_id = ?)";
$stmt = $conn->prepare($commentCheckQuery);
$stmt->bind_param('iii', $commentId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this comment.']);
    exit();
}

$deleteCommentQuery = "DELETE FROM comments WHERE comment_id = ?";
$stmt = $conn->prepare($deleteCommentQuery);
$stmt->bind_param('i', $commentId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
?>
