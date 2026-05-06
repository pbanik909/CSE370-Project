<?php
session_start();
require 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$post_id = (int)($_POST['post_id'] ?? 0);

if (!$post_id) { echo json_encode(['error' => 'bad request']); exit; }

// Check if already liked
$check = $conn->prepare("SELECT reaction_id FROM reactions WHERE user_id=? AND post_id=?");
$check->bind_param("ii", $user_id, $post_id);
$check->execute();
$exists = $check->get_result()->num_rows > 0;

if ($exists) {
    $conn->prepare("DELETE FROM reactions WHERE user_id=? AND post_id=?")->bind_param("ii", $user_id, $post_id) && true;
    $conn->query("UPDATE post_manage SET react_count = react_count - 1 WHERE post_id = $post_id AND react_count > 0");
    $liked = false;
} else {
    $conn->prepare("INSERT INTO reactions (user_id, post_id) VALUES (?,?)")->bind_param("ii", $user_id, $post_id) && true;
    $conn->query("UPDATE post_manage SET react_count = react_count + 1 WHERE post_id = $post_id");
    $liked = true;
}

$stmt = $conn->prepare("SELECT react_count FROM post_manage WHERE post_id=?");
$stmt->bind_param("i", $post_id); $stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['react_count'] ?? 0;

echo json_encode(['liked' => $liked, 'count' => (int)$count]);