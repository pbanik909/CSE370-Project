<?php
// =======================================================
// Job Applicants — for the job poster (or admin)
// View who applied, see resume/letter, change status
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$me     = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';
$job_id = (int)($_GET['job_id'] ?? 0);

// Fetch job + check permission
$j = $conn->prepare("SELECT * FROM job_posts WHERE job_id = ?");
$j->bind_param("i", $job_id);
$j->execute();
$job = $j->get_result()->fetch_assoc();

if (!$job) {
    header("Location: jobs.php");
    exit;
}

$is_owner = ((int)$job['poster_id'] === $me);
$is_admin = ($role === 'Admin');

if (!$is_owner && !$is_admin) {
    header("Location: jobs.php");
    exit;
}

// --- Handle status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $app_id = (int)$_POST['application_id'];
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['submitted', 'reviewed', 'shortlisted', 'rejected'])) {
        $upd = $conn->prepare(
            "UPDATE applications SET status = ? WHERE application_id = ? AND job_id = ?"
        );
        $upd->bind_param("sii", $status, $app_id, $job_id);
        $upd->execute();
    }
    header("Location: job_applicants.php?job_id=" . $job_id);
    exit;
}

// --- Fetch applicants ---
$apps = $conn->prepare("
    SELECT a.*, u.name, u.pic, u.role, p.headline,
           s.dept, s.batch, s.cgpa
      FROM applications a
      JOIN users u ON u.user_id = a.user_id
      LEFT JOIN profile p ON p.user_id = u.user_id
      LEFT JOIN students s ON s.student_id = u.user_id
     WHERE a.job_id = ?
     ORDER BY a.created_at DESC
");
$apps->bind_param("i", $job_id);
$apps->execute();
$applicants = $apps->get_result();

function avatar_url($pic, $name) {
    if (!empty($pic) && file_exists(__DIR__ . '/' . $pic)) return $pic;
    return 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($name);
}

$page_title = 'Applicants';
include 'includes/header.php';
?>

<main class="container" style="padding: 48px 0 80px; max-width: 900px;">

    <div class="page-head" style="padding-top: 0;">
        <div class="eyebrow">Applicants for</div>
        <h1><?= htmlspecialchars($job['title']) ?></h1>
        <p class="lead">at <?= htmlspecialchars($job['company']) ?> — <?= $applicants->num_rows ?> application<?= $applicants->num_rows == 1 ? '' : 's' ?></p>
    </div>

    <p style="margin-bottom: 24px;">
        <a href="jobs.php" class="btn btn--ghost btn--sm">← Back to all jobs</a>
    </p>

    <?php if ($applicants->num_rows === 0): ?>
        <div class="empty">
            <h3>No applicants yet.</h3>
            <p>Share the listing — applicants will appear here.</p>
        </div>
    <?php else: ?>
        <?php while ($a = $applicants->fetch_assoc()):
            $av = avatar_url($a['pic'], $a['name']);
        ?>
        <div class="application-row">
            <a href="profile.php?id=<?= (int)$a['user_id'] ?>">
                <img src="<?= htmlspecialchars($av) ?>" alt="">
            </a>
            <div class="who" style="flex: 1;">
                <h4>
                    <a href="profile.php?id=<?= (int)$a['user_id'] ?>"><?= htmlspecialchars($a['name']) ?></a>
                </h4>
                <p>
                    <?= htmlspecialchars($a['role']) ?>
                    <?php if (!empty($a['dept'])): ?>
                        &middot; <?= htmlspecialchars($a['dept']) ?>
                        <?php if (!empty($a['batch'])): ?>(Batch <?= htmlspecialchars($a['batch']) ?>)<?php endif; ?>
                        <?php if (!empty($a['cgpa'])): ?> &middot; CGPA <?= htmlspecialchars($a['cgpa']) ?><?php endif; ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($a['headline'])): ?>
                    <p style="font-style: italic; margin-top: 4px;">"<?= htmlspecialchars($a['headline']) ?>"</p>
                <?php endif; ?>
                <p style="font-size: 12px; margin-top: 4px;">
                    Applied <?= date('M j, Y · g:i A', strtotime($a['created_at'])) ?>
                </p>
            </div>

            <span class="app-status <?= $a['status'] ?>"><?= $a['status'] ?></span>
        </div>

        <!-- Letter + resume + status form -->
        <div style="background: var(--paper); border: 1px solid var(--border); border-top: none; border-radius: 0 0 var(--radius) var(--radius); padding: 18px 22px; margin-top: -12px; margin-bottom: 12px;">
            <?php if (!empty($a['application_letter'])): ?>
                <div style="margin-bottom: 14px;">
                    <strong style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted);">Cover letter</strong>
                    <p style="margin-top: 6px; font-size: 14px; line-height: 1.6; white-space: pre-wrap;">
                        <?= htmlspecialchars($a['application_letter']) ?>
                    </p>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <?php if (!empty($a['resume_file']) && file_exists(__DIR__ . '/' . $a['resume_file'])): ?>
                    <a href="<?= htmlspecialchars($a['resume_file']) ?>" target="_blank" class="btn btn--ghost btn--sm">📄 View resume</a>
                <?php else: ?>
                    <span style="color: var(--muted); font-size: 13px;">No resume uploaded</span>
                <?php endif; ?>

                <a href="messages.php?with=<?= (int)$a['user_id'] ?>" class="btn btn--ghost btn--sm">💬 Message</a>

                <form method="POST" action="job_applicants.php?job_id=<?= (int)$job_id ?>" style="display:flex; gap:8px; margin-left:auto;">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="application_id" value="<?= (int)$a['application_id'] ?>">
                    <select name="status" style="padding:6px 12px; font-size: 13px;">
                        <option value="submitted"   <?= $a['status']=='submitted'?'selected':'' ?>>Submitted</option>
                        <option value="reviewed"    <?= $a['status']=='reviewed'?'selected':'' ?>>Reviewed</option>
                        <option value="shortlisted" <?= $a['status']=='shortlisted'?'selected':'' ?>>Shortlisted</option>
                        <option value="rejected"    <?= $a['status']=='rejected'?'selected':'' ?>>Rejected</option>
                    </select>
                    <button type="submit" class="btn btn--sm">Update</button>
                </form>
            </div>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
