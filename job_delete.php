<?php
// =======================================================
// Job delete — poster OR admin can delete a job
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: jobs.php");
    exit;
}

$me     = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';
$job_id = (int)($_POST['job_id'] ?? 0);

if ($job_id <= 0) {
    header("Location: jobs.php");
    exit;
}

// Find job poster
$check = $conn->prepare("SELECT poster_id FROM job_posts WHERE job_id = ?");
$check->bind_param("i", $job_id);
$check->execute();
$j = $check->get_result()->fetch_assoc();

if ($j) {
    $is_owner = ((int)$j['poster_id'] === $me);
    $is_admin = ($role === 'Admin');

    if ($is_owner || $is_admin) {
        $del = $conn->prepare("DELETE FROM job_posts WHERE job_id = ?");
        $del->bind_param("i", $job_id);
        $del->execute();

        // Log admin action
        if ($is_admin && !$is_owner) {
            $log = $conn->prepare(
                "INSERT INTO admin_actions (admin_id, target_type, target_id, action)
                 VALUES (?, 'job', ?, 'delete')"
            );
            $log->bind_param("ii", $me, $job_id);
            $log->execute();
        }
    }
}

header("Location: jobs.php?deleted=1");
exit;
?>
