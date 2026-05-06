<?php
// =======================================================
// My Applications — applicant's own submissions + status
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$me = (int)$_SESSION['user_id'];

$apps = $conn->prepare("
    SELECT a.*, j.title, j.company, j.location, j.salary,
           u.name AS poster_name, u.user_id AS poster_id
      FROM applications a
      JOIN job_posts j ON j.job_id = a.job_id
      JOIN users u ON u.user_id = j.poster_id
     WHERE a.user_id = ?
     ORDER BY a.created_at DESC
");
$apps->bind_param("i", $me);
$apps->execute();
$rows = $apps->get_result();

$page_title = 'My Applications';
include 'includes/header.php';
?>

<main class="container" style="padding: 48px 0 80px; max-width: 900px;">

    <div class="page-head" style="padding-top: 0;">
        <div class="eyebrow">Tracking your applications</div>
        <h1>Your <em>applications.</em></h1>
        <p class="lead">Status updates from job posters appear here.</p>
    </div>

    <?php if ($rows->num_rows === 0): ?>
        <div class="empty">
            <h3>No applications yet.</h3>
            <p>Browse the <a href="jobs.php" class="btn-link">job board</a> to apply.</p>
        </div>
    <?php else: ?>
        <?php while ($a = $rows->fetch_assoc()): ?>
        <article class="job-card">
            <div style="display: flex; justify-content: space-between; align-items: start; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <h3><?= htmlspecialchars($a['title']) ?></h3>
                    <div class="company"><?= htmlspecialchars($a['company']) ?></div>
                </div>
                <span class="app-status <?= $a['status'] ?>"><?= $a['status'] ?></span>
            </div>

            <div class="job-meta-list">
                <?php if (!empty($a['location'])): ?>
                    <span class="pill">📍 <?= htmlspecialchars($a['location']) ?></span>
                <?php endif; ?>
                <?php if (!empty($a['salary'])): ?>
                    <span class="pill">💰 <?= htmlspecialchars($a['salary']) ?></span>
                <?php endif; ?>
                <span>📅 Applied <?= date('M j, Y', strtotime($a['created_at'])) ?></span>
            </div>

            <?php if (!empty($a['application_letter'])): ?>
                <div style="font-size: 14px; color: var(--ink-soft); margin-bottom: 12px;">
                    <strong style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted);">Your cover letter:</strong><br>
                    <span style="font-style: italic;">"<?= htmlspecialchars(mb_substr($a['application_letter'], 0, 200)) ?><?= mb_strlen($a['application_letter']) > 200 ? '…' : '' ?>"</span>
                </div>
            <?php endif; ?>

            <div class="actions">
                <a href="messages.php?with=<?= (int)$a['poster_id'] ?>" class="btn btn--ghost btn--sm">💬 Message <?= htmlspecialchars(explode(' ', $a['poster_name'])[0]) ?></a>
                <?php if (!empty($a['resume_file']) && file_exists(__DIR__ . '/' . $a['resume_file'])): ?>
                    <a href="<?= htmlspecialchars($a['resume_file']) ?>" target="_blank" class="btn-link" style="align-self:center;">📄 Your resume</a>
                <?php endif; ?>
            </div>
        </article>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
