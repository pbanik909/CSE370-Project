<?php
// =======================================================
// FEATURE 4 + 7 + 8 — Feed with Reactions, Comments, Repost
// Shows a unified timeline of posts AND reposts.
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$error   = '';

// --- Handle new post submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {

    $context = trim($_POST['context'] ?? '');
    $name    = trim($_POST['name'] ?? '');

    if ($context === '' && empty($_FILES['media']['name'])) {
        $error = 'Write something or attach an image.';
    } else {
        $media_path = null;
        if (!empty($_FILES['media']['name']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
            $ok  = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $ok)) {
                $newName = 'post_' . $user_id . '_' . time() . '.' . $ext;
                $dest    = __DIR__ . '/uploads/' . $newName;
                if (move_uploaded_file($_FILES['media']['tmp_name'], $dest)) {
                    $media_path = 'uploads/' . $newName;
                }
            }
        }

        $stmt = $conn->prepare(
            "INSERT INTO post_manage (user_id, name, media_url, context)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("isss", $user_id, $name, $media_path, $context);
        $stmt->execute();

        header("Location: feed.php");
        exit;
    }
}

// Build a unified timeline: original posts UNION reposts.
// For each item we need: who published it, whose original it is, the post data, and timestamps.
$tl_sql = "
(
    SELECT
        pm.post_id, pm.name, pm.media_url, pm.context,
        pm.react_count, pm.share_count, pm.comment_count,
        pm.created_at AS event_at,
        u.user_id AS author_id, u.name AS author_name, u.pic AS author_pic, u.role AS author_role,
        NULL AS reposter_id, NULL AS reposter_name, NULL AS repost_note,
        'post' AS item_type
      FROM post_manage pm
      JOIN users u ON u.user_id = pm.user_id
)
UNION ALL
(
    SELECT
        pm.post_id, pm.name, pm.media_url, pm.context,
        pm.react_count, pm.share_count, pm.comment_count,
        r.created_at AS event_at,
        u.user_id AS author_id, u.name AS author_name, u.pic AS author_pic, u.role AS author_role,
        ru.user_id AS reposter_id, ru.name AS reposter_name, r.note AS repost_note,
        'repost' AS item_type
      FROM reposts r
      JOIN post_manage pm ON pm.post_id = r.post_id
      JOIN users u  ON u.user_id  = pm.user_id
      JOIN users ru ON ru.user_id = r.reposter_id
)
ORDER BY event_at DESC
LIMIT 80
";
$res = $conn->query($tl_sql);

// Helpers
function getComments($conn, $post_id) {
    $stmt = $conn->prepare(
        "SELECT c.comment_id, c.content, c.created_at,
                u.user_id, u.name, u.pic
           FROM comments c
           JOIN users u ON u.user_id = c.user_id
          WHERE c.post_id = ?
          ORDER BY c.created_at ASC"
    );
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    return $stmt->get_result();
}

function avatar($pic, $name) {
    if (!empty($pic) && file_exists(__DIR__ . '/' . $pic)) return $pic;
    return 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($name);
}

// Bulk: figure out which posts I've liked + reposted (one query each)
$liked_ids = [];
$lr = $conn->prepare("SELECT post_id FROM reactions WHERE user_id = ?");
$lr->bind_param("i", $user_id); $lr->execute();
$ll = $lr->get_result();
while ($x = $ll->fetch_assoc()) $liked_ids[(int)$x['post_id']] = true;

$reposted_ids = [];
$rr = $conn->prepare("SELECT post_id FROM reposts WHERE reposter_id = ?");
$rr->bind_param("i", $user_id); $rr->execute();
$rl = $rr->get_result();
while ($x = $rl->fetch_assoc()) $reposted_ids[(int)$x['post_id']] = true;

$page_title = 'Feed';
include 'includes/header.php';
?>

<main class="container" style="padding: 48px 0 80px;">

    <div class="feed-layout">

        <section>
            <?php if ($error): ?>
                <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Composer -->
            <form method="POST" action="feed.php" enctype="multipart/form-data" class="composer">
                <input type="hidden" name="create_post" value="1">
                <input type="text" name="name" placeholder="Title (optional)"
                       style="border:none; background:transparent; font-family: var(--font-display); font-size: 24px; padding: 0; margin-bottom: 8px;">
                <textarea name="context" rows="3" placeholder="What's on your mind, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?>?"></textarea>
                <div class="composer-foot">
                    <input type="file" name="media" accept="image/*">
                    <button type="submit" class="btn">Post</button>
                </div>
            </form>

            <!-- Feed -->
            <?php if ($res && $res->num_rows > 0): ?>
                <?php while ($p = $res->fetch_assoc()):
                    $author_avatar = avatar($p['author_pic'], $p['author_name']);
                    $i_liked    = isset($liked_ids[(int)$p['post_id']]);
                    $i_reposted = isset($reposted_ids[(int)$p['post_id']]);
                    $is_repost  = ($p['item_type'] === 'repost');
                    $can_repost = ((int)$p['author_id'] !== $user_id);
                ?>
                <article class="post" id="post-<?= (int)$p['post_id'] ?>">

                    <?php if ($is_repost): ?>
                        <div class="repost-tag">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                                <path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                            </svg>
                            <strong><?= htmlspecialchars($p['reposter_name']) ?></strong>&nbsp;reposted
                            <?php if (!empty($p['repost_note'])): ?>
                                <span style="margin-left: 8px; font-style: italic;">— "<?= htmlspecialchars($p['repost_note']) ?>"</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <header class="post-head">
                        <a href="profile.php?id=<?= (int)$p['author_id'] ?>">
                            <img src="<?= htmlspecialchars($author_avatar) ?>" alt="" class="post-avatar">
                        </a>
                        <div>
                            <div class="post-author">
                                <a href="profile.php?id=<?= (int)$p['author_id'] ?>">
                                    <?= htmlspecialchars($p['author_name']) ?>
                                </a>
                            </div>
                            <div class="post-meta">
                                <?= htmlspecialchars($p['author_role']) ?> &middot;
                                <?= date('M j, Y · g:i A', strtotime($p['event_at'])) ?>
                            </div>
                        </div>
                    </header>

                    <?php if (!empty($p['name'])): ?>
                        <h3 style="font-family: var(--font-display); font-size: 28px; font-weight: 400; margin-bottom: 10px; line-height: 1.15;">
                            <?= htmlspecialchars($p['name']) ?>
                        </h3>
                    <?php endif; ?>

                    <?php if (!empty($p['context'])): ?>
                        <div class="post-body"><?= nl2br(htmlspecialchars($p['context'])) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($p['media_url']) && file_exists(__DIR__ . '/' . $p['media_url'])): ?>
                        <div class="post-media">
                            <img src="<?= htmlspecialchars($p['media_url']) ?>" alt="">
                        </div>
                    <?php endif; ?>

                    <div class="post-actions">
                        <!-- Like -->
                        <button type="button"
                                class="action-btn like-btn <?= $i_liked ? 'liked' : '' ?>"
                                data-post-id="<?= (int)$p['post_id'] ?>"
                                onclick="toggleLike(this)">
                            <svg viewBox="0 0 24 24" fill="<?= $i_liked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                                <path d="M12 21s-7-4.5-9.5-9C.5 8 3 4 7 4c2 0 3.5 1 5 3 1.5-2 3-3 5-3 4 0 6.5 4 4.5 8C19 16.5 12 21 12 21z"/>
                            </svg>
                            <span class="count"><?= (int)$p['react_count'] ?></span>
                            <span>Like<?= $p['react_count'] == 1 ? '' : 's' ?></span>
                        </button>

                        <!-- Comment toggle -->
                        <button type="button" class="action-btn"
                                onclick="this.closest('.post').querySelector('.comments-section').classList.toggle('open')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/>
                            </svg>
                            <span><?= (int)$p['comment_count'] ?> Comment<?= $p['comment_count'] == 1 ? '' : 's' ?></span>
                        </button>

                        <!-- Repost (only on others' posts) -->
                        <?php if ($can_repost): ?>
                            <button type="button" class="action-btn <?= $i_reposted ? 'reposted' : '' ?>"
                                    onclick="document.getElementById('repost-modal-<?= (int)$p['post_id'] ?>').style.display='flex'">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                                    <path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                                </svg>
                                <span><?= (int)$p['share_count'] ?></span>
                                <span><?= $i_reposted ? 'Reposted' : 'Repost' ?></span>
                            </button>

                            <!-- Repost modal -->
                            <div id="repost-modal-<?= (int)$p['post_id'] ?>" class="modal-overlay" style="display:none;">
                                <div class="modal">
                                    <h3><?= $i_reposted ? 'Undo repost?' : 'Repost this?' ?></h3>
                                    <p class="sub">
                                        <?= $i_reposted
                                            ? 'You already reposted this. Click confirm to undo.'
                                            : 'Share <strong>' . htmlspecialchars($p['author_name']) . "</strong>'s post with your network. You can add a quick note." ?>
                                    </p>
                                    <form method="POST" action="repost_action.php">
                                        <input type="hidden" name="post_id" value="<?= (int)$p['post_id'] ?>">
                                        <?php if (!$i_reposted): ?>
                                            <div class="form-row">
                                                <label>Add a note (optional)</label>
                                                <textarea name="note" rows="2" placeholder="What do you think?"></textarea>
                                            </div>
                                        <?php endif; ?>
                                        <div style="display:flex; gap:10px; justify-content:flex-end;">
                                            <button type="button" class="btn btn--ghost"
                                                    onclick="document.getElementById('repost-modal-<?= (int)$p['post_id'] ?>').style.display='none'">
                                                Cancel
                                            </button>
                                            <button type="submit" class="btn">
                                                <?= $i_reposted ? 'Undo repost' : 'Repost' ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Message author -->
                        <?php if ((int)$p['author_id'] !== $user_id): ?>
                            <a href="messages.php?with=<?= (int)$p['author_id'] ?>" class="action-btn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                                <span>Message</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Comments -->
                    <div class="comments-section">
                        <?php $c_res = getComments($conn, $p['post_id']); ?>
                        <?php if ($c_res->num_rows === 0): ?>
                            <div class="no-comments">No comments yet — be the first.</div>
                        <?php else: ?>
                            <?php while ($c = $c_res->fetch_assoc()):
                                $cav = avatar($c['pic'], $c['name']);
                            ?>
                                <div class="comment">
                                    <a href="profile.php?id=<?= (int)$c['user_id'] ?>">
                                        <img src="<?= htmlspecialchars($cav) ?>" alt="" class="comment-avatar">
                                    </a>
                                    <div class="comment-bubble">
                                        <div class="comment-author">
                                            <a href="profile.php?id=<?= (int)$c['user_id'] ?>"><?= htmlspecialchars($c['name']) ?></a>
                                            <span class="when"><?= date('M j · g:i A', strtotime($c['created_at'])) ?></span>
                                        </div>
                                        <div class="comment-body"><?= nl2br(htmlspecialchars($c['content'])) ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>

                        <?php
                            $my_pic = $conn->query("SELECT pic, name FROM users WHERE user_id = $user_id")->fetch_assoc();
                            $my_av  = avatar($my_pic['pic'] ?? '', $my_pic['name'] ?? 'You');
                        ?>
                        <form method="POST" action="add_comment.php" class="comment-form">
                            <img src="<?= htmlspecialchars($my_av) ?>" alt="">
                            <input type="hidden" name="post_id" value="<?= (int)$p['post_id'] ?>">
                            <textarea name="content" rows="1" placeholder="Write a comment…" required></textarea>
                            <button type="submit" class="btn btn--sm">Post</button>
                        </form>
                    </div>
                </article>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty">
                    <h3>The feed is quiet.</h3>
                    <p>Be the first to post something.</p>
                </div>
            <?php endif; ?>
        </section>

        <aside class="sidebar">
            <div class="side-card">
                <h4>Welcome, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?></h4>
                <p>You're signed in as <strong><?= htmlspecialchars($_SESSION['role']) ?></strong>. Round out your <a href="profile.php" class="btn-link">profile</a>.</p>
            </div>
            <div class="side-card">
                <h4>Looking for opportunities?</h4>
                <p>Check the <a href="jobs.php" class="btn-link">Jobs</a> board for openings posted by alumni.</p>
            </div>
            <div class="side-card">
                <h4>Grow your network</h4>
                <p>Visit <a href="people.php" class="btn-link">Network</a> to find peers and alumni to connect with.</p>
            </div>
        </aside>
    </div>
</main>

<script>
// AJAX like toggle
async function toggleLike(btn) {
    const postId = btn.dataset.postId;
    try {
        const res = await fetch('like_toggle.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'post_id=' + encodeURIComponent(postId)
        });
        const data = await res.json();
        if (data.error) { alert('Please sign in again.'); return; }

        btn.classList.toggle('liked', data.liked);
        btn.querySelector('.count').textContent = data.count;
        btn.querySelector('svg').setAttribute('fill', data.liked ? 'currentColor' : 'none');
    } catch (e) { console.error(e); }
}

// Close modal on backdrop click
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; });
});
</script>

<?php include 'includes/footer.php'; ?>
