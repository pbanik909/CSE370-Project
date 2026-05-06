<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$page_title = 'Network';
include 'includes/header.php';
$users = $conn->query("SELECT user_id, name, role, pic FROM users ORDER BY name");
?>
<main class="container" style="padding:48px 0">
  <h2>Network</h2>
  <?php while($u = $users->fetch_assoc()): ?>
    <a href="profile.php?id=<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)</a><br>
  <?php endwhile; ?>
</main>
<?php include 'includes/footer.php'; ?>