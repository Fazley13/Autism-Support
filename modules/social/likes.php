<?php
include_once '../../config/config.php';

$postId = $_GET['post_id'];

$likesQuery = "
    SELECT user.username 
    FROM likes
    JOIN user ON likes.user_id = user.user_id
    WHERE likes.post_id = ?
";

$stmt = $conn->prepare($likesQuery);
$stmt->bind_param('i', $postId);
$stmt->execute();
$likesResult = $stmt->get_result();

$likes = [];
while ($like = $likesResult->fetch_assoc()) {
    $likes[] = $like;
}

$stmt->close();

echo json_encode(['likes' => $likes]);
?>