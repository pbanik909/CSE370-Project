<?php
// =======================================================
// FEATURE 10 — Apply to a job
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$me     = (int)$_SESSION['user_id'];
$job_id = (int)($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
$error  = '';

// Fetch job
if ($job_id > 0) {
    $j = $conn->prepare(
        "SELECT j.*, u.name AS poster_name
           FROM job_posts j
           JOIN users u ON u.user_id = j.poster_id
          WHERE j.job_id = ?"
    );
    $j->bind_param("i", $job_id);
    $j->execute();
    $job = $j->get_result()->fetch_assoc();
}

if (empty($job)) {
    $page_title = 'Job not found';
    include 'includes/header.php';
    echo '<main class="container" style="padding:80px 0;"><div class="empty"><h3>Job not found.</h3><p><a href="jobs.php" class="btn-link">Back to jobs</a></p></div></main>';
    include 'includes/footer.php';
    exit;
}

// Don't let the poster apply to their own job
if ((int)$job['poster_id'] === $me) {
    header("Location: jobs.php");
    exit;
}

// Already applied?
$check = $conn->prepare("SELECT application_id FROM applications WHERE job_id = ? AND user_id = ?");
$check->bind_param("ii", $job_id, $me);
$check->execute();
$check->store_result();
$already_applied = $check->num_rows > 0;

// --- Handle submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_applied) {

    $letter = trim($_POST['letter'] ?? '');
    $resume_path = null;

    if (!empty($_FILES['resume']['name']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'doc', 'docx'])) {
            // Make sure resumes folder exists
            if (!is_dir(__DIR__ . '/resumes')) {
                @mkdir(__DIR__ . '/resumes', 0755, true);
            }
            $newName = 'resume_' . $me . '_' . $job_id . '_' . time() . '.' . $ext;
            $dest    = __DIR__ . '/resumes/' . $newName;
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $dest)) {
                $resume_path = 'resumes/' . $newName;
            }
        } else {
            $error = 'Resume must be a PDF or Word document.';
        }
    }

    if ($error === '' && $letter === '' && $resume_path === null) {
        $error = 'Upload a resume or write a cover letter (or both).';
    }

    if ($error === '') {
        $ins = $conn->prepare(
            "INSERT INTO applications (job_id, user_id, resume_file, application_letter)
             VALUES (?, ?, ?, ?)"
        );
        $ins->bind_param("iiss", $job_id, $me, $resume_path, $letter);
        $ins->execute();
        header("Location: jobs.php?applied=1");
        exit;
    }
}

$page_title = 'Apply';
include 'includes/header.php';
?>

<main class="container container--narrow" style="padding: 48px 0 80px;">

    <div class="page-head" style="padding-top: 0;">
        <div class="eyebrow">Application</div>
        <h1>Apply for <em><?= htmlspecialchars($job['title']) ?></em></h1>
        <p class="lead">at <strong><?= htmlspecialchars($job['company']) ?></strong> — posted by <?= htmlspecialchars($job['poster_name']) ?></p>
    </div>

    <?php if ($already_applied): ?>
        <div class="alert alert--info">
            You've already applied for this position. Track your application status on
            <a href="my_applications.php" class="btn-link">My applications</a>.
        </div>
        <p><a href="jobs.php" class="btn btn--ghost">← Back to jobs</a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>Send your application</h2>
            <p class="sub">Upload your resume and add a short cover letter explaining why you're a fit.</p>

            <form method="POST" action="apply.php" enctype="multipart/form-data">
                <input type="hidden" name="job_id" value="<?= (int)$job_id ?>">

                <div class="form-row">
                    <label>Resume (PDF or Word)</label>
                    <input type="file" name="resume" accept=".pdf,.doc,.docx">
                </div>

                <div class="form-row">
                    <label>Cover letter</label>
                    <textarea name="letter" rows="6" placeholder="Hi, I'm interested in this role because…"></textarea>
                </div>

                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <a href="jobs.php" class="btn btn--ghost">Cancel</a>
                    <button type="submit" class="btn">Submit application</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
