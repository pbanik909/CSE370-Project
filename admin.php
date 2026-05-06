<?php
// =======================================================
// FEATURE 12 — Admin Moderation Panel
// Visible only to users with role = 'Admin'
// Tabs: Overview, Users, Posts, Jobs, Activity log
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    // Not allowed — redirect to feed
    header("Location: feed.php");
    exit;
}

$me  = (int)$_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'overview';
if (!in_array($tab, ['overview', 'users', 'posts', 'jobs', 'log'])) $tab = 'overview';

// --- Action handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $target_id = (int)($_POST['target_id'] ?? 0);

    if ($action === 'delete_post' && $target_id > 0) {
        $del = $conn->prepare("DELETE FROM post_manage WHERE post_id = ?");
        $del->bind_param("i", $target_id);
        $del->execute();

        $log = $conn->prepare("INSERT INTO admin_actions (admin_id, target_type, target_id, action) VALUES (?, 'post', ?, 'delete')");
        $log->bind_param("ii", $me, $target_id);
        $log->execute();

    } elseif ($action === 'delete_comment' && $target_id > 0) {
        // Find post first to decrement its count
        $f = $conn->prepare("SELECT post_id FROM comments WHERE comment_id = ?");
        $f->bind_param("i", $target_id); $f->execute();
        $cm = $f->get_result()->fetch_assoc();

        $del = $conn->prepare("DELETE FROM comments WHERE comment_id = ?");
        $del->bind_param("i", $target_id);
        $del->execute();

        if ($cm) {
            $upd = $conn->prepare("UPDATE post_manage SET comment_count = GREATEST(comment_count - 1, 0) WHERE post_id = ?");
            $upd->bind_param("i", $cm['post_id']);
            $upd->execute();
        }

        $log = $conn->prepare("INSERT INTO admin_actions (admin_id, target_type, target_id, action) VALUES (?, 'comment', ?, 'delete')");
        $log->bind_param("ii", $me, $target_id);
        $log->execute();

    } elseif ($action === 'delete_user' && $target_id > 0 && $target_id !== $me) {
        $del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $del->bind_param("i", $target_id);
        $del->execute();

        $log = $conn->prepare("INSERT INTO admin_actions (admin_id, target_type, target_id, action) VALUES (?, 'user', ?, 'delete')");
        $log->bind_param("ii", $me, $target_id);
        $log->execute();
    }

    header("Location: admin.php?tab=" . $tab);
    exit;
}

// --- Stats ---
function count_q($conn, $sql) {
    $r = $conn->query($sql);
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}
$stats = [
    'users'    => count_q($conn, "SELECT COUNT(*) c FROM users"),
    'students' => count_q($conn, "SELECT COUNT(*) c FROM users WHERE role='Student'"),
    'alumni'   => count_q($conn, "SELECT COUNT(*) c FROM users WHERE role='Alumni'"),
    'admins'   => count_q($conn, "SELECT COUNT(*) c FROM users WHERE role='Admin'"),
    'posts'    => count_q($conn, "SELECT COUNT(*) c FROM post_manage"),
    'comments' => count_q($conn, "SELECT COUNT(*) c FROM comments"),
    'jobs'     => count_q($conn, "SELECT COUNT(*) c FROM job_posts"),
    'apps'     => count_q($conn, "SELECT COUNT(*) c FROM applications"),
    'connections' => count_q($conn, "SELECT COUNT(*) c FROM requests WHERE status='accepted'"),
    'messages' => count_q($conn, "SELECT COUNT(*) c FROM messages"),
];

function avatar_url($pic, $name) {
    if (!empty($pic) && file_exists(__DIR__ . '/' . $pic)) return $pic;
    return 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($name);
}

$page_title = 'Admin';
include 'includes/header.php';
?>

<main class="container container--wide" style="padding: 48px 0 80px;">

    <div class="page-head" style="padding-top: 0;">
        <div class="eyebrow">Moderation</div>
        <h1>Admin <em>panel.</em></h1>
        <p class="lead">Manage users, posts, jobs, and review activity. Use carefully — deletions cascade.</p>
    </div>

    <div class="req-tabs">
        <a href="?tab=overview" class="<?= $tab === 'overview' ? 'active' : '' ?>">Overview</a>
        <a href="?tab=users"    class="<?= $tab === 'users' ? 'active' : '' ?>">Users</a>
        <a href="?tab=posts"    class="<?= $tab === 'posts' ? 'active' : '' ?>">Posts</a>
        <a href="?tab=jobs"     class="<?= $tab === 'jobs' ? 'active' : '' ?>">Jobs</a>
        <a href="?tab=log"      class="<?= $tab === 'log' ? 'active' : '' ?>">Activity log</a>
    </div>

    <?php if ($tab === 'overview'): ?>

        <div class="admin-stats">
            <div class="stat-card"><div class="num"><?= $stats['users'] ?></div><div class="label">Total users</div></div>
            <div class="stat-card"><div class="num"><?= $stats['students'] ?></div><div class="label">Students</div></div>
            <div class="stat-card"><div class="num"><?= $stats['alumni'] ?></div><div class="label">Alumni</div></div>
            <div class="stat-card"><div class="num"><?= $stats['admins'] ?></div><div class="label">Admins</div></div>
            <div class="stat-card"><div class="num"><?= $stats['posts'] ?></div><div class="label">Posts</div></div>
            <div class="stat-card"><div class="num"><?= $stats['comments'] ?></div><div class="label">Comments</div></div>
            <div class="stat-card"><div class="num"><?= $stats['jobs'] ?></div><div class="label">Jobs</div></div>
            <div class="stat-card"><div class="num"><?= $stats['apps'] ?></div><div class="label">Applications</div></div>
            <div class="stat-card"><div class="num"><?= $stats['connections'] ?></div><div class="label">Connections</div></div>
            <div class="stat-card"><div class="num"><?= $stats['messages'] ?></div><div class="label">Messages</div></div>
        </div>

    <?php elseif ($tab === 'users'): ?>

        <?php
        $users = $conn->query("
            SELECT u.user_id, u.name, u.gmail, u.role, u.pic, u.created_at,
                   (SELECT COUNT(*) FROM post_manage WHERE user_id = u.user_id) AS post_count
              FROM users u
             ORDER BY u.created_at DESC
        ");
        ?>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Posts</th>
                    <th>Joined</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($u = $users->fetch_assoc()):
                    $av = avatar_url($u['pic'], $u['name']);
                ?>
                <tr>
                    <td>
                        <img src="<?= htmlspecialchars($av) ?>" class="tiny-avatar">
                        <a href="profile.php?id=<?= (int)$u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?></a>
                    </td>
                    <td style="color: var(--muted);"><?= htmlspecialchars($u['gmail']) ?></td>
                    <td><span class="role-pill <?= $u['role'] ?>"><?= $u['role'] ?></span></td>
                    <td><?= (int)$u['post_count'] ?></td>
                    <td style="color: var(--muted); font-size: 13px;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td style="text-align: right;">
                        <?php if ((int)$u['user_id'] !== $me): ?>
                            <form method="POST" action="admin.php?tab=users" style="display:inline;"
                                  onsubmit="return confirm('Delete user <?= htmlspecialchars($u['name']) ?>? This cannot be undone — all their posts, comments, and connections will go too.');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="target_id" value="<?= (int)$u['user_id'] ?>">
                                <button type="submit" class="btn btn--reject btn--sm">Delete</button>
                            </form>
                        <?php else: ?>
                            <span style="color: var(--muted); font-size: 12px;">(you)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php elseif ($tab === 'posts'): ?>

        <?php
        $posts = $conn->query("
            SELECT pm.post_id, pm.name, pm.context, pm.react_count, pm.comment_count, pm.created_at,
                   u.user_id, u.name AS author_name, u.pic
              FROM post_manage pm
              JOIN users u ON u.user_id = pm.user_id
             ORDER BY pm.created_at DESC
             LIMIT 100
        ");
        ?>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Author</th>
                    <th>Content</th>
                    <th>♥</th>
                    <th>💬</th>
                    <th>Posted</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($p = $posts->fetch_assoc()):
                    $av = avatar_url($p['pic'], $p['author_name']);
                    $excerpt = !empty($p['name']) ? $p['name'] : $p['context'];
                ?>
                <tr>
                    <td>
                        <img src="<?= htmlspecialchars($av) ?>" class="tiny-avatar">
                        <a href="profile.php?id=<?= (int)$p['user_id'] ?>"><?= htmlspecialchars($p['author_name']) ?></a>
                    </td>
                    <td style="max-width: 400px;">
                        <a href="feed.php#post-<?= (int)$p['post_id'] ?>" style="color: var(--ink-soft);">
                            <?= htmlspecialchars(mb_substr($excerpt ?? '', 0, 120)) ?>
                            <?= mb_strlen($excerpt ?? '') > 120 ? '…' : '' ?>
                        </a>
                    </td>
                    <td><?= (int)$p['react_count'] ?></td>
                    <td><?= (int)$p['comment_count'] ?></td>
                    <td style="color: var(--muted); font-size: 13px;"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                    <td style="text-align: right;">
                        <form method="POST" action="admin.php?tab=posts" style="display:inline;"
                              onsubmit="return confirm('Delete this post? Comments and reactions go too.');">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="target_id" value="<?= (int)$p['post_id'] ?>">
                            <button type="submit" class="btn btn--reject btn--sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php elseif ($tab === 'jobs'): ?>

        <?php
        $jobs = $conn->query("
            SELECT j.*, u.name AS poster_name,
                   (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) AS app_count
              FROM job_posts j
              JOIN users u ON u.user_id = j.poster_id
             ORDER BY j.created_at DESC
        ");
        ?>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Company</th>
                    <th>Posted by</th>
                    <th>Apps</th>
                    <th>Posted</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($j = $jobs->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($j['title']) ?></strong></td>
                    <td><?= htmlspecialchars($j['company']) ?></td>
                    <td><a href="profile.php?id=<?= (int)$j['poster_id'] ?>"><?= htmlspecialchars($j['poster_name']) ?></a></td>
                    <td><?= (int)$j['app_count'] ?></td>
                    <td style="color: var(--muted); font-size: 13px;"><?= date('M j, Y', strtotime($j['created_at'])) ?></td>
                    <td style="text-align: right;">
                        <a href="job_applicants.php?job_id=<?= (int)$j['job_id'] ?>" class="btn btn--ghost btn--sm">View</a>
                        <form method="POST" action="job_delete.php" style="display:inline;"
                              onsubmit="return confirm('Remove this job posting?');">
                            <input type="hidden" name="job_id" value="<?= (int)$j['job_id'] ?>">
                            <button type="submit" class="btn btn--reject btn--sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    <?php elseif ($tab === 'log'): ?>

        <?php
        $logs = $conn->query("
            SELECT a.*, u.name AS admin_name
              FROM admin_actions a
              JOIN users u ON u.user_id = a.admin_id
             ORDER BY a.created_at DESC
             LIMIT 100
        ");
        ?>

        <?php if ($logs->num_rows === 0): ?>
            <div class="empty">
                <h3>No actions logged yet.</h3>
                <p>Whenever an admin deletes content, it'll show up here.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Target type</th>
                        <th>Target ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($l = $logs->fetch_assoc()): ?>
                    <tr>
                        <td style="color: var(--muted);"><?= date('M j, Y · g:i A', strtotime($l['created_at'])) ?></td>
                        <td><?= htmlspecialchars($l['admin_name']) ?></td>
                        <td><strong><?= htmlspecialchars($l['action']) ?></strong></td>
                        <td><?= htmlspecialchars($l['target_type']) ?></td>
                        <td>#<?= (int)$l['target_id'] ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
