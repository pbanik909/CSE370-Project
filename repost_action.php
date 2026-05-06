<?php
// =======================================================
// FEATURE 8 — Repost / Share
// Adds a row to `reposts` and bumps share_count.
// Toggles: if already reposted, undoes it.
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: feed.php");
    exit;
}

$me      = (int)$_SESSION['user_id'];
$post_id = (int)($_POST['post_id'] ?? 0);
$note    = trim($_POST['note'] ?? '');

if ($post_id <= 0) {
    header("Location: feed.php");
    exit;
}

// Don't allow reposting your own post
$check = $conn->prepare("SELECT user_id FROM post_manage WHERE post_id = ?");
$check->bind_param("i", $post_id);
$check->execute();
$orig = $check->get_result()->fetch_assoc();
if (!$orig || (int)$orig['user_id'] === $me) {
    header("Location: feed.php");
    exit;
}

// Check if already reposted
$existing = $conn->prepare("SELECT repost_id FROM reposts WHERE post_id = ? AND reposter_id = ?");
$existing->bind_param("ii", $post_id, $me);
$existing->execute();
$existing->store_result();

if ($existing->num_rows > 0) {
    // Undo repost
    $del = $conn->prepare("DELETE FROM reposts WHERE post_id = ? AND reposter_id = ?");
    $del->bind_param("ii", $post_id, $me);
    $del->execute();
    $upd = $conn->prepare("UPDATE post_manage SET share_count = GREATEST(share_count - 1, 0) WHERE post_id = ?");
    $upd->bind_param("i", $post_id);
    $upd->execute();
} else {
    $ins = $conn->prepare("INSERT INTO reposts (post_id, reposter_id, note) VALUES (?, ?, ?)");
    $ins->bind_param("iis", $post_id, $me, $note);
    $ins->execute();
    $upd = $conn->prepare("UPDATE post_manage SET share_count = share_count + 1 WHERE post_id = ?");
    $upd->bind_param("i", $post_id);
    $upd->execute();
}

header("Location: feed.php#post-" . $post_id);
exit;
?>
