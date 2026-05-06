<?php
// =======================================================
// FEATURE 3 — Profile
// View your own profile and edit it.
// Joins users + profile + (students | alumni | admins).
// =======================================================
session_start();
require 'config/db.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $headline     = trim($_POST['headline'] ?? '');
    $bio          = trim($_POST['bio'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $title        = trim($_POST['title'] ?? '');
    $website      = trim($_POST['website_link'] ?? '');
    $github       = trim($_POST['github_link'] ?? '');

    // Handle profile picture upload (optional)
    $pic_path = null;
    if (!empty($_FILES['pic']['name']) && $_FILES['pic']['error'] === UPLOAD_ERR_OK) {
        $ext   = strtolower(pathinfo($_FILES['pic']['name'], PATHINFO_EXTENSION));
        $allow = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allow)) {
            $newName  = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $dest     = __DIR__ . '/uploads/' . $newName;
            if (move_uploaded_file($_FILES['pic']['tmp_name'], $dest)) {
                $pic_path = 'uploads/' . $newName;
            }
        }
    }

    // Update profile table
    $upd = $conn->prepare(
        "UPDATE profile
         SET headline = ?, bio = ?, organization = ?, title = ?,
             website_link = ?, github_link = ?
         WHERE user_id = ?"
    );
    $upd->bind_param("ssssssi", $headline, $bio, $organization, $title, $website, $github, $user_id);
    $upd->execute();

    // Update profile picture in users table if uploaded
    if ($pic_path !== null) {
        $up2 = $conn->prepare("UPDATE users SET pic = ? WHERE user_id = ?");
        $up2->bind_param("si", $pic_path, $user_id);
        $up2->execute();
    }

    $message = 'Profile updated.';
}

// --- Fetch user + profile ---
$stmt = $conn->prepare(
    "SELECT u.user_id, u.name, u.gmail, u.phone_no, u.dob, u.gender,
            u.pic, u.role, u.created_at,
            p.headline, p.bio, p.organization, p.title,
            p.website_link, p.github_link
       FROM users u
       LEFT JOIN profile p ON p.user_id = u.user_id
      WHERE u.user_id = ?"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

// Role-specific extras
$extra = [];
if ($me['role'] === 'Student') {
    $r = $conn->prepare("SELECT semester, cgpa, dept, batch FROM students WHERE student_id = ?");
    $r->bind_param("i", $user_id);
    $r->execute();
    $extra = $r->get_result()->fetch_assoc() ?: [];
} elseif ($me['role'] === 'Alumni') {
    $r = $conn->prepare("SELECT graduation_year, designation FROM alumni WHERE alumni_id = ?");
    $r->bind_param("i", $user_id);
    $r->execute();
    $extra = $r->get_result()->fetch_assoc() ?: [];
} elseif ($me['role'] === 'Admin') {
    $r = $conn->prepare("SELECT role FROM admins WHERE admin_id = ?");
    $r->bind_param("i", $user_id);
    $r->execute();
    $extra = $r->get_result()->fetch_assoc() ?: [];
}

$pic = !empty($me['pic']) && file_exists(__DIR__ . '/' . $me['pic'])
       ? $me['pic']
       : 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($me['name']);

$page_title = 'Profile';
include 'includes/header.php';
?>

<main class="container" style="padding: 48px 0 80px;">

    <?php if ($message): ?>
        <div class="alert alert--success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="profile-grid">

        <!-- Left side: identity card -->
        <aside class="profile-side">
            <img src="<?= htmlspecialchars($pic) ?>" alt="" class="profile-pic">
            <div class="profile-name"><?= htmlspecialchars($me['name']) ?></div>
            <div class="profile-role"><?= htmlspecialchars($me['role']) ?></div>
            <div class="profile-meta"><?= htmlspecialchars($me['gmail']) ?></div>
            <?php if (!empty($me['phone_no'])): ?>
                <div class="profile-meta"><?= htmlspecialchars($me['phone_no']) ?></div>
            <?php endif; ?>
            <?php if (!empty($me['headline'])): ?>
                <p style="margin-top: 16px; font-style: italic; color: var(--ink-soft);">
                    "<?= htmlspecialchars($me['headline']) ?>"
                </p>
            <?php endif; ?>
        </aside>

        <!-- Right side: details + edit form -->
        <section>
            <div class="page-head" style="padding-top:0; margin-bottom:32px;">
                <div class="eyebrow">Member since <?= date('M Y', strtotime($me['created_at'])) ?></div>
                <h1>Hello, <em><?= htmlspecialchars(explode(' ', $me['name'])[0]) ?>.</em></h1>
            </div>

            <!-- Existing profile snapshot -->
            <?php if (!empty($me['bio'])): ?>
                <div class="profile-section">
                    <h3>About</h3>
                    <p><?= nl2br(htmlspecialchars($me['bio'])) ?></p>
                </div>
            <?php endif; ?>

            <div class="profile-section">
                <h3>Details</h3>
                <ul class="detail-list">
                    <?php if (!empty($me['organization'])): ?>
                        <li><span>Organization</span><span><?= htmlspecialchars($me['organization']) ?></span></li>
                    <?php endif; ?>
                    <?php if (!empty($me['title'])): ?>
                        <li><span>Title</span><span><?= htmlspecialchars($me['title']) ?></span></li>
                    <?php endif; ?>

                    <?php if ($me['role'] === 'Student'): ?>
                        <?php if (!empty($extra['dept'])): ?>
                            <li><span>Department</span><span><?= htmlspecialchars($extra['dept']) ?></span></li>
                        <?php endif; ?>
                        <?php if (!empty($extra['batch'])): ?>
                            <li><span>Batch</span><span><?= htmlspecialchars($extra['batch']) ?></span></li>
                        <?php endif; ?>
                        <?php if (!empty($extra['semester'])): ?>
                            <li><span>Semester</span><span><?= htmlspecialchars($extra['semester']) ?></span></li>
                        <?php endif; ?>
                        <?php if (!empty($extra['cgpa'])): ?>
                            <li><span>CGPA</span><span><?= htmlspecialchars($extra['cgpa']) ?></span></li>
                        <?php endif; ?>
                    <?php elseif ($me['role'] === 'Alumni'): ?>
                        <?php if (!empty($extra['graduation_year'])): ?>
                            <li><span>Graduated</span><span><?= htmlspecialchars($extra['graduation_year']) ?></span></li>
                        <?php endif; ?>
                        <?php if (!empty($extra['designation'])): ?>
                            <li><span>Designation</span><span><?= htmlspecialchars($extra['designation']) ?></span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($me['website_link'])): ?>
                        <li><span>Website</span><span><a href="<?= htmlspecialchars($me['website_link']) ?>" target="_blank" class="btn-link">Visit</a></span></li>
                    <?php endif; ?>
                    <?php if (!empty($me['github_link'])): ?>
                        <li><span>GitHub</span><span><a href="<?= htmlspecialchars($me['github_link']) ?>" target="_blank" class="btn-link">Open</a></span></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Edit form -->
            <div class="card" style="margin-top: 40px;">
                <h2>Edit profile</h2>
                <p class="sub">Update what others see when they visit your page.</p>

                <form method="POST" action="profile.php" enctype="multipart/form-data">
                    <div class="form-row">
                        <label>Profile picture</label>
                        <input type="file" name="pic" accept="image/*">
                    </div>
                    <div class="form-row">
                        <label for="headline">Headline</label>
                        <input type="text" id="headline" name="headline" maxlength="255"
                               value="<?= htmlspecialchars($me['headline'] ?? '') ?>"
                               placeholder="e.g. CSE student exploring web dev">
                    </div>
                    <div class="form-row">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="4"><?= htmlspecialchars($me['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row two-col">
                        <div>
                            <label for="organization">Organization</label>
                            <input type="text" id="organization" name="organization"
                                   value="<?= htmlspecialchars($me['organization'] ?? '') ?>">
                        </div>
                        <div>
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title"
                                   value="<?= htmlspecialchars($me['title'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row two-col">
                        <div>
                            <label for="website_link">Website</label>
                            <input type="url" id="website_link" name="website_link"
                                   value="<?= htmlspecialchars($me['website_link'] ?? '') ?>"
                                   placeholder="https://">
                        </div>
                        <div>
                            <label for="github_link">GitHub</label>
                            <input type="url" id="github_link" name="github_link"
                                   value="<?= htmlspecialchars($me['github_link'] ?? '') ?>"
                                   placeholder="https://github.com/...">
                        </div>
                    </div>

                    <button type="submit" class="btn">Save changes</button>
                </form>
            </div>
        </section>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
