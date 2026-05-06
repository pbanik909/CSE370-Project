<?php
// =======================================================
// FEATURE 9 — Job Posts (board + create)
// Anyone can browse. Alumni and Admin can post.
// Students can apply (handled in apply.php).
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$me   = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$can_post = in_array($role, ['Alumni', 'Admin']);

$error = '';
$flash = '';

// --- Handle new job post ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_job']) && $can_post) {

    $title       = trim($_POST['title'] ?? '');
    $company     = trim($_POST['company'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $salary      = trim($_POST['salary'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $apply_link  = trim($_POST['apply_link'] ?? '');

    if ($title === '' || $company === '') {
        $error = 'Job title and company are required.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO job_posts
                (poster_id, title, company, location, salary, category, description, apply_link)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssssss",
            $me, $title, $company, $location, $salary, $category, $description, $apply_link);
        $stmt->execute();
        header("Location: jobs.php?posted=1");
        exit;
    }
}

if (isset($_GET['posted'])) $flash = 'Job posted successfully.';
if (isset($_GET['applied'])) $flash = 'Application submitted!';
if (isset($_GET['deleted'])) $flash = 'Job removed.';

// --- Fetch all jobs ---
$jobs = $conn->query("
    SELECT j.*, u.name AS poster_name, u.role AS poster_role, u.pic AS poster_pic,
           (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) AS apply_count,
           (SELECT 1 FROM applications a WHERE a.job_id = j.job_id AND a.user_id = $me) AS i_applied
      FROM job_posts j
      JOIN users u ON u.user_id = j.poster_id
     ORDER BY j.created_at DESC
");

function avatar_url($pic, $name) {
    if (!empty($pic) && file_exists(__DIR__ . '/' . $pic)) return $pic;
    return 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($name);
}

$page_title = 'Jobs';
include 'includes/header.php';
?>

<main class="container" style="padding: 48px 0 80px;">

    <div class="page-head" style="padding-top: 0;">
        <div class="eyebrow">Opportunities for the network</div>
        <h1>The <em>job board.</em></h1>
        <p class="lead">Openings posted by alumni and admins. Apply with a resume and a quick cover letter.</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert--success"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="jobs-layout">

        <!-- LEFT: jobs list -->
        <section>
            <?php if ($jobs->num_rows === 0): ?>
                <div class="empty">
                    <h3>No jobs yet.</h3>
                    <p>
                        <?php if ($can_post): ?>
                            Be the first to post — fill the form on the right.
                        <?php else: ?>
                            Check back soon, or ask an alumni in your network to post one.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php while ($j = $jobs->fetch_assoc()):
                    $pav = avatar_url($j['poster_pic'], $j['poster_name']);
                    $is_owner = ((int)$j['poster_id'] === $me);
                    $is_admin = ($role === 'Admin');
                ?>
                <article class="job-card" id="job-<?= (int)$j['job_id'] ?>">
                    <h3><?= htmlspecialchars($j['title']) ?></h3>
                    <div class="company"><?= htmlspecialchars($j['company']) ?></div>

                    <div class="job-meta-list">
                        <?php if (!empty($j['location'])): ?>
                            <span class="pill">📍 <?= htmlspecialchars($j['location']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($j['salary'])): ?>
                            <span class="pill">💰 <?= htmlspecialchars($j['salary']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($j['category'])): ?>
                            <span class="pill">🏷️ <?= htmlspecialchars($j['category']) ?></span>
                        <?php endif; ?>
                        <span>📅 <?= date('M j, Y', strtotime($j['created_at'])) ?></span>
                        <span>👥 <?= (int)$j['apply_count'] ?> applicant<?= $j['apply_count'] == 1 ? '' : 's' ?></span>
                    </div>

                    <?php if (!empty($j['description'])): ?>
                        <div class="desc"><?= nl2br(htmlspecialchars($j['description'])) ?></div>
                    <?php endif; ?>

                    <div class="actions">
                        <?php if ($is_owner): ?>
                            <a href="job_applicants.php?job_id=<?= (int)$j['job_id'] ?>" class="btn btn--sm">
                                View applicants (<?= (int)$j['apply_count'] ?>)
                            </a>
                            <form method="POST" action="job_delete.php" onsubmit="return confirm('Delete this job post?');" style="display:inline;">
                                <input type="hidden" name="job_id" value="<?= (int)$j['job_id'] ?>">
                                <button type="submit" class="btn btn--reject btn--sm">Delete</button>
                            </form>
                        <?php elseif ($is_admin): ?>
                            <a href="job_applicants.php?job_id=<?= (int)$j['job_id'] ?>" class="btn btn--ghost btn--sm">
                                View applicants
                            </a>
                            <form method="POST" action="job_delete.php" onsubmit="return confirm('As admin, remove this job?');" style="display:inline;">
                                <input type="hidden" name="job_id" value="<?= (int)$j['job_id'] ?>">
                                <button type="submit" class="btn btn--reject btn--sm">Remove (admin)</button>
                            </form>
                        <?php elseif ($j['i_applied']): ?>
                            <span class="btn btn--disabled btn--sm">✓ Applied</span>
                            <a href="my_applications.php" class="btn-link" style="align-self:center;">Track status</a>
                        <?php else: ?>
                            <a href="apply.php?job_id=<?= (int)$j['job_id'] ?>" class="btn btn--sm">Apply now</a>
                        <?php endif; ?>

                        <?php if (!empty($j['apply_link'])): ?>
                            <a href="<?= htmlspecialchars($j['apply_link']) ?>" target="_blank" class="btn-link"
                               style="align-self:center;">External link ↗</a>
                        <?php endif; ?>
                    </div>

                    <div class="posted-by">
                        Posted by
                        <a href="profile.php?id=<?= (int)$j['poster_id'] ?>">
                            <?= htmlspecialchars($j['poster_name']) ?>
                        </a>
                        <span style="color: var(--muted);">(<?= htmlspecialchars($j['poster_role']) ?>)</span>
                    </div>
                </article>
                <?php endwhile; ?>
            <?php endif; ?>
        </section>

        <!-- RIGHT: post form (alumni/admin) or info (students) -->
        <aside class="sidebar">
            <?php if ($can_post): ?>
                <div class="side-card">
                    <h4>Post a job</h4>
                    <p style="margin-bottom: 16px;">Share an opening with the campus.</p>

                    <form method="POST" action="jobs.php">
                        <input type="hidden" name="create_job" value="1">

                        <div class="form-row">
                            <label>Title *</label>
                            <input type="text" name="title" required placeholder="e.g. Frontend Developer">
                        </div>
                        <div class="form-row">
                            <label>Company *</label>
                            <input type="text" name="company" required placeholder="Company name">
                        </div>
                        <div class="form-row">
                            <label>Location</label>
                            <input type="text" name="location" placeholder="e.g. Dhaka, Remote">
                        </div>
                        <div class="form-row">
                            <label>Salary</label>
                            <input type="text" name="salary" placeholder="e.g. 50,000 BDT/mo">
                        </div>
                        <div class="form-row">
                            <label>Category</label>
                            <select name="category">
                                <option value="">— Select —</option>
                                <option>Full-time</option>
                                <option>Part-time</option>
                                <option>Internship</option>
                                <option>Contract</option>
                                <option>Remote</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Description</label>
                            <textarea name="description" rows="4" placeholder="What you'll do…"></textarea>
                        </div>
                        <div class="form-row">
                            <label>External apply link</label>
                            <input type="url" name="apply_link" placeholder="https://…">
                        </div>

                        <button type="submit" class="btn btn--full">Publish</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="side-card">
                    <h4>How to apply</h4>
                    <p>Click <strong>Apply now</strong> on any job. You'll upload your resume (PDF) and write a short cover letter.</p>
                </div>
                <div class="side-card">
                    <h4>Track your applications</h4>
                    <p>Visit <a href="my_applications.php" class="btn-link">My applications</a> to see status updates.</p>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
