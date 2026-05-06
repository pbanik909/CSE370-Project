<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = (int)$_SESSION['user_id'];
$post_id = (int)($_POST['post_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

if ($post_id && $content !== '') {
    $stmt = $conn->prepare("INSERT INTO comments (user_id, post_id, content) VALUES (?,?,?)");
    $stmt->bind_param("iis", $user_id, $post_id, $content);
    $stmt->execute();
    $conn->query("UPDATE post_manage SET comment_count = comment_count + 1 WHERE post_id = $post_id");
}

header("Location: feed.php#post-$post_id");
exit;