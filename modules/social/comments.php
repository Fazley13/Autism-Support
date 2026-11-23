<?php
include_once '../../config/config.php';

$postId = $_GET['post_id'];

$commentsQuery = "
    SELECT comments.*, user.username 
    FROM comments
    JOIN user ON comments.user_id = user.user_id
    WHERE comments.post_id = ?
    ORDER BY comments.created_at ASC
";

$stmt = $conn->prepare($commentsQuery);
$stmt->bind_param('i', $postId);
$stmt->execute();
$commentsResult = $stmt->get_result();

$comments = [];
while ($comment = $commentsResult->fetch_assoc()) {
    $comments[] = $comment;
}

$stmt->close();

echo json_encode(['comments' => $comments]);
?>