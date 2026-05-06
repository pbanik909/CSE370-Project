<?php
// =======================================================
// FEATURE 2 — Login
// Verifies the email + password and starts a session.
// =======================================================
session_start();
require 'config/db.php';

$error = '';
$success = '';

// Show "registered" banner after sign-up
if (isset($_GET['registered'])) {
    $success = 'Account created — please sign in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gmail    = trim($_POST['gmail'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($gmail === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, name, password, role FROM users WHERE gmail = ?"
        );
        $stmt->bind_param("s", $gmail);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // Success — start session
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['name']    = $row['name'];
                $_SESSION['role']    = $row['role'];
                header("Location: feed.php");
                exit;
            }
        }
        $error = 'Email or password is incorrect.';
    }
}

$page_title = 'Sign in';
include 'includes/header.php';
?>

<main class="container container--narrow" style="padding: 80px 0;">

    <div class="card">
        <h2>Welcome back</h2>
        <p class="sub">Sign in to continue to your network.</p>

        <?php if ($success): ?>
            <div class="alert alert--success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-row">
                <label for="gmail">Email</label>
                <input type="email" id="gmail" name="gmail" required autofocus>
            </div>
            <div class="form-row">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn--full">Sign in</button>

            <p style="margin-top:18px; text-align:center; color: var(--muted); font-size:14px;">
                New here? <a href="register.php" class="btn-link">Create an account</a>
            </p>
        </form>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
