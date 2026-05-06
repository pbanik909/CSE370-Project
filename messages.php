<?php
// =======================================================
// FEATURE 6 — Messaging
// Left panel: list of conversations
// Right panel: open conversation with one person
// URL: messages.php           -> inbox only
//      messages.php?with=NN   -> open conversation with user NN
// =======================================================
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$me   = (int)$_SESSION['user_id'];
$with = isset($_GET['with']) ? (int)$_GET['with'] : 0;

// --- Send message ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $to   = (int)($_POST['to'] ?? 0);
    $body = trim($_POST['content'] ?? '');
    if ($to > 0 && $to !== $me && $body !== '') {
        $ins = $conn->prepare(
            "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)"
        );
        $ins->bind_param("iis", $me, $to, $body);
        $ins->execute();
    }
    header("Location: messages.php?with=" . $to);
    exit;
}

// --- Mark as read when opening a conversation ---
if ($with > 0) {
    $upd = $conn->prepare(
        "UPDATE messages SET is_read = 1
          WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"
    );
    $upd->bind_param("ii", $with, $me);
    $upd->execute();
}

function avatar_url($pic, $name) {
    if (!empty($pic) && file_exists(__DIR__ . '/' . $pic)) return $pic;
    return 'https://api.dicebear.com/7.x/initials/svg?seed=' . urlencode($name);
}

// --- Load all conversations (the "inbox") ---
// Find each unique partner + their latest message + unread count
$threads_sql = "
    SELECT
        partner.user_id, partner.name, partner.role, partner.pic,
        latest.content AS last_message,
        latest.created_at AS last_at,
        latest.sender_id AS last_sender_id,
        unread.cnt AS unread_count
      FROM (
          SELECT
              CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END AS partner_id,
              MAX(created_at) AS last_at
            FROM messages
           WHERE sender_id = ? OR receiver_id = ?
           GROUP BY partner_id
      ) AS conv
      JOIN users partner ON partner.user_id = conv.partner_id
      JOIN messages latest
        ON latest.created_at = conv.last_at
       AND ((latest.sender_id = ? AND latest.receiver_id = conv.partner_id)
         OR (latest.receiver_id = ? AND latest.sender_id = conv.partner_id))
      LEFT JOIN (
          SELECT sender_id AS partner_id, COUNT(*) AS cnt
            FROM messages
           WHERE receiver_id = ? AND is_read = 0
           GROUP BY sender_id
      ) AS unread ON unread.partner_id = conv.partner_id
     ORDER BY conv.last_at DESC
";
$tstmt = $conn->prepare($threads_sql);
$tstmt->bind_param("iiiiii", $me, $me, $me, $me, $me, $me);
$tstmt->execute();
$threads = $tstmt->get_result();

// --- If a partner is selected, load conversation + their info ---
$partner = null;
$conversation = null;
if ($with > 0) {
    $ps = $conn->prepare("SELECT user_id, name, role, pic FROM users WHERE user_id = ?");
    $ps->bind_param("i", $with);
    $ps->execute();
    $partner = $ps->get_result()->fetch_assoc();

    if ($partner) {
        $cs = $conn->prepare(
            "SELECT message_id, sender_id, content, created_at
               FROM messages
              WHERE (sender_id = ? AND receiver_id = ?)
                 OR (sender_id = ? AND receiver_id = ?)
              ORDER BY created_at ASC"
        );
        $cs->bind_param("iiii", $me, $with, $with, $me);
        $cs->execute();
        $conversation = $cs->get_result();
    }
}

$page_title = 'Messages';
include 'includes/header.php';
?>

<main class="container container--wide" style="padding: 32px 0 48px;">

    <div class="msg-layout">

        <!-- LEFT: thread list -->
        <aside class="msg-list-panel">
            <div class="msg-list-head">
                <h3>Messages</h3>
            </div>

            <?php if ($threads->num_rows === 0): ?>
                <div style="padding: 40px 22px; text-align:center; color: var(--muted); font-size: 14px;">
                    No conversations yet.<br>
                    Start one from <a href="people.php" class="btn-link">Network</a>.
                </div>
            <?php else: ?>
                <?php while ($t = $threads->fetch_assoc()):
                    $av = avatar_url($t['pic'], $t['name']);
                    $is_active = ($with === (int)$t['user_id']);
                    $is_unread = (int)($t['unread_count'] ?? 0) > 0;
                    $preview = ((int)$t['last_sender_id'] === $me ? 'You: ' : '') . $t['last_message'];
                ?>
                <a href="messages.php?with=<?= (int)$t['user_id'] ?>"
                   class="thread-item <?= $is_active ? 'active' : '' ?> <?= $is_unread ? 'unread' : '' ?>">
                    <img src="<?= htmlspecialchars($av) ?>" alt="">
                    <div class="thread-meta">
                        <div class="thread-name">
                            <span><?= htmlspecialchars($t['name']) ?></span>
                            <span class="when"><?= date('M j', strtotime($t['last_at'])) ?></span>
                        </div>
                        <div class="thread-preview">
                            <?= htmlspecialchars(mb_substr($preview, 0, 50)) ?>
                            <?= mb_strlen($preview) > 50 ? '…' : '' ?>
                        </div>
                    </div>
                </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </aside>

        <!-- RIGHT: conversation pane -->
        <section class="msg-conv-panel">
            <?php if (!$partner): ?>
                <div class="msg-empty">
                    <h3>Pick a conversation.</h3>
                    <p>Or start a new one from someone's profile or the Network page.</p>
                </div>
            <?php else:
                $pav = avatar_url($partner['pic'], $partner['name']);
            ?>
                <header class="msg-conv-head">
                    <a href="profile.php?id=<?= (int)$partner['user_id'] ?>">
                        <img src="<?= htmlspecialchars($pav) ?>" alt="">
                    </a>
                    <div>
                        <h3>
                            <a href="profile.php?id=<?= (int)$partner['user_id'] ?>">
                                <?= htmlspecialchars($partner['name']) ?>
                            </a>
                        </h3>
                        <span class="role-tag"><?= htmlspecialchars($partner['role']) ?></span>
                    </div>
                </header>

                <div class="msg-conv-body" id="conv-body">
                    <?php if (!$conversation || $conversation->num_rows === 0): ?>
                        <div class="msg-empty">
                            <h3>Say hi.</h3>
                            <p>This is the start of your conversation with <?= htmlspecialchars($partner['name']) ?>.</p>
                        </div>
                    <?php else: ?>
                        <?php while ($m = $conversation->fetch_assoc()):
                            $is_me = ((int)$m['sender_id'] === $me);
                        ?>
                            <div class="msg-bubble <?= $is_me ? 'me' : 'them' ?>">
                                <?= nl2br(htmlspecialchars($m['content'])) ?>
                                <span class="when"><?= date('M j · g:i A', strtotime($m['created_at'])) ?></span>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" action="messages.php" class="msg-conv-foot">
                    <input type="hidden" name="send_message" value="1">
                    <input type="hidden" name="to" value="<?= (int)$partner['user_id'] ?>">
                    <textarea name="content" rows="1" placeholder="Write a message…" required></textarea>
                    <button type="submit" class="btn">Send</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
// Auto-scroll to bottom of the conversation
const body = document.getElementById('conv-body');
if (body) body.scrollTop = body.scrollHeight;
</script>

<?php include 'includes/footer.php'; ?>
