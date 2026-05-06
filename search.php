<?php
// =======================================================
// FEATURE 11 — Search
// Searches: users (name/headline/dept), posts (title/context), jobs (title/company)
// URL: search.php?q=keyword
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$me = (int)$_SESSION['user_id'];
$q  = trim($_GET['q'] ?? '');

$users_res = null;
$posts_res = null;
$jobs_res  = null;

if ($q !== '') {
    $like = '%' . $q . '%';

    // Users (name, headline, dept)
    $us = $conn->prepare("
        SELECT DISTINCT u.user_id, u.name, u.role, u.pic, p.headline, s.dept
          FROM users u
          LEFT JOIN profile p ON p.user_id = u.user_id
          LEFT JOIN students s ON s.student_id = u.user_id
         WHERE u.user_id <> ?
           AND (u.name LIKE ? OR p.headline LIKE ? OR s.dept LIKE ?)
         ORDER BY u.name ASC
         LIMIT 20
    ");
    $us->bind_param("isss", $me, $like, $like, $like);
    $us->execute();
    $users_res = $us->get_result();

    // Posts (title, context)
    $ps = $conn->prepare("
        SELECT pm.post_id, pm.name, pm.context, pm.created_at,
               u.user_id, u.name AS author_name, u.pic, u.role
          FROM post_manage pm
          JOIN users u ON u.user_id = pm.user_id
         WHERE pm.name LIKE ? OR pm.context LIKE ?
         ORDER BY pm.created_at DESC
         LIMIT 15
    ");
    $ps->bind_param("ss", $like, $like);
    $ps->execute();
    $posts_res = $ps->get_result();

    // Jobs (title, company, description)
    $js = $conn->prepare("
        SELECT j.*, u.name AS poster_name
          FROM job_posts j
          JOIN users u ON u.user_id = j.poster_id
         WHERE j.title LIKE ?
            OR j.company LIKE ?
            OR j.description LIKE ?
            OR j.category LIKE ?
         ORDER BY j.created_at DESC
         LIMIT 10
    ");
    $js->bind_param("ssss", $like, $like, $like, $like);
    $js->execute();
    $jobs_res = $js->get_result();
}

function avatar_url($pic, $name) {
    if (!empty($pic) && file_exists(__DIR__ . '/' . $pic)) return $pic;
    return 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($name);
}

function highlight($text, $q) {
    if ($q === '') return htmlspecialchars($text);
    return preg_replace(
        '/(' . preg_quote($q, '/') . ')/i',
        '<mark style="background: #fef3d6; padding: 0 2px;">$1</mark>',
        htmlspecialchars($text)
    );
}

$page_title = 'Search';
include 'includes/header.php';
?>

<main class="container" style="padding: 48px 0 80px;">

    <div class="page-head" style="padding-top: 0;">
        <div class="eyebrow">Search</div>
        <?php if ($q): ?>
            <h1>Results for <em>"<?= htmlspecialchars($q) ?>"</em></h1>
        <?php else: ?>
            <h1>Search the <em>network.</em></h1>
            <p class="lead">Find people, posts, and jobs by keyword.</p>
        <?php endif; ?>
    </div>

    <form action="search.php" method="GET" style="margin-bottom: 48px;">
        <div style="display: flex; gap: 10px;">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                   placeholder="Search by name, dept, post, job…" autofocus
                   style="font-size: 17px;">
            <button type="submit" class="btn">Search</button>
        </div>
    </form>

    <?php if ($q !== ''): ?>

        <!-- ---------- People ---------- -->
        <div class="search-section">
            <h2>
                People
                <span class="count"><?= $users_res ? $users_res->num_rows : 0 ?> result<?= ($users_res && $users_res->num_rows == 1) ? '' : 's' ?></span>
            </h2>

            <?php if (!$users_res || $users_res->num_rows === 0): ?>
                <div class="search-empty">No people matched "<?= htmlspecialchars($q) ?>".</div>
            <?php else: ?>
                <div class="people-grid">
                    <?php while ($u = $users_res->fetch_assoc()):
                        $av = avatar_url($u['pic'], $u['name']);
                    ?>
                    <div class="people-card">
                        <a href="profile.php?id=<?= (int)$u['user_id'] ?>">
                            <img src="<?= htmlspecialchars($av) ?>" alt="" class="avatar">
                        </a>
                        <h4>
                            <a href="profile.php?id=<?= (int)$u['user_id'] ?>">
                                <?= highlight($u['name'], $q) ?>
                            </a>
                        </h4>
                        <div class="role-tag"><?= htmlspecialchars($u['role']) ?></div>
                        <p class="headline">
                            <?php if (!empty($u['headline'])): ?>
                                <?= highlight($u['headline'], $q) ?>
                            <?php elseif (!empty($u['dept'])): ?>
                                <?= highlight($u['dept'], $q) ?>
                            <?php else: ?>
                                <em style="color: var(--muted);">No headline</em>
                            <?php endif; ?>
                        </p>
                        <a href="profile.php?id=<?= (int)$u['user_id'] ?>" class="btn btn--ghost btn--sm">View profile</a>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ---------- Posts ---------- -->
        <div class="search-section">
            <h2>
                Posts
                <span class="count"><?= $posts_res ? $posts_res->num_rows : 0 ?> result<?= ($posts_res && $posts_res->num_rows == 1) ? '' : 's' ?></span>
            </h2>

            <?php if (!$posts_res || $posts_res->num_rows === 0): ?>
                <div class="search-empty">No posts matched "<?= htmlspecialchars($q) ?>".</div>
            <?php else: ?>
                <?php while ($p = $posts_res->fetch_assoc()):
                    $pav = avatar_url($p['pic'], $p['author_name']);
                ?>
                <article class="post">
                    <header class="post-head">
                        <a href="profile.php?id=<?= (int)$p['user_id'] ?>">
                            <img src="<?= htmlspecialchars($pav) ?>" alt="" class="post-avatar">
                        </a>
                        <div>
                            <div class="post-author">
                                <a href="profile.php?id=<?= (int)$p['user_id'] ?>"><?= htmlspecialchars($p['author_name']) ?></a>
                            </div>
                            <div class="post-meta">
                                <?= htmlspecialchars($p['role']) ?> &middot;
                                <?= date('M j, Y', strtotime($p['created_at'])) ?>
                            </div>
                        </div>
                    </header>
                    <?php if (!empty($p['name'])): ?>
                        <h3 style="font-family: var(--font-display); font-size: 26px; font-weight: 400; margin-bottom: 8px;">
                            <?= highlight($p['name'], $q) ?>
                        </h3>
                    <?php endif; ?>
                    <?php if (!empty($p['context'])): ?>
                        <div class="post-body">
                            <?= nl2br(highlight(mb_substr($p['context'], 0, 300), $q)) ?>
                            <?= mb_strlen($p['context']) > 300 ? '…' : '' ?>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 12px;">
                        <a href="feed.php#post-<?= (int)$p['post_id'] ?>" class="btn-link">View on feed →</a>
                    </div>
                </article>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- ---------- Jobs ---------- -->
        <div class="search-section">
            <h2>
                Jobs
                <span class="count"><?= $jobs_res ? $jobs_res->num_rows : 0 ?> result<?= ($jobs_res && $jobs_res->num_rows == 1) ? '' : 's' ?></span>
            </h2>

            <?php if (!$jobs_res || $jobs_res->num_rows === 0): ?>
                <div class="search-empty">No jobs matched "<?= htmlspecialchars($q) ?>".</div>
            <?php else: ?>
                <?php while ($j = $jobs_res->fetch_assoc()): ?>
                <article class="job-card">
                    <h3><?= highlight($j['title'], $q) ?></h3>
                    <div class="company"><?= highlight($j['company'], $q) ?></div>
                    <div class="job-meta-list">
                        <?php if (!empty($j['location'])): ?>
                            <span class="pill">📍 <?= htmlspecialchars($j['location']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($j['category'])): ?>
                            <span class="pill">🏷️ <?= htmlspecialchars($j['category']) ?></span>
                        <?php endif; ?>
                        <span>📅 <?= date('M j, Y', strtotime($j['created_at'])) ?></span>
                    </div>
                    <?php if (!empty($j['description'])): ?>
                        <div class="desc"><?= nl2br(highlight(mb_substr($j['description'], 0, 200), $q)) ?>…</div>
                    <?php endif; ?>
                    <div class="actions">
                        <a href="jobs.php#job-<?= (int)$j['job_id'] ?>" class="btn btn--sm">View on jobs</a>
                    </div>
                </article>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
