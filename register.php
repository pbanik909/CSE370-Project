<?php
// =======================================================
// FEATURE 1 — User Registration
// Creates a row in `users` plus a row in the role-specific
// table (students / alumni / admins) plus an empty profile.
// =======================================================
session_start();
require 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 1. Collect & sanitize input ---
    $name     = trim($_POST['name'] ?? '');
    $gmail    = trim($_POST['gmail'] ?? '');
    $phone    = trim($_POST['phone_no'] ?? '');
    $dob      = $_POST['dob'] ?? null;
    $gender   = $_POST['gender'] ?? null;
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'Student';

    // --- 2. Basic validation ---
    if ($name === '' || $gmail === '' || $password === '') {
        $error = 'Name, email and password are required.';
    } elseif (!filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // --- 3. Check duplicate email ---
        $check = $conn->prepare("SELECT user_id FROM users WHERE gmail = ?");
        $check->bind_param("s", $gmail);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = 'An account with this email already exists.';
        }
        $check->close();
    }

    if ($error === '') {
        // --- 4. Insert into users ---
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (name, phone_no, gmail, dob, gender, password, role)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssssss", $name, $phone, $gmail, $dob, $gender, $hashed, $role);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;

            // --- 5. Insert into the role-specific table ---
            if ($role === 'Student') {
                $sem   = $_POST['semester'] ?? '';
                $cgpa  = $_POST['cgpa'] !== '' ? $_POST['cgpa'] : null;
                $dept  = $_POST['dept'] ?? '';
                $batch = $_POST['batch'] ?? '';
                $s = $conn->prepare(
                    "INSERT INTO students (student_id, semester, cgpa, dept, batch)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $s->bind_param("issss", $user_id, $sem, $cgpa, $dept, $batch);
                $s->execute();

            } elseif ($role === 'Alumni') {
                $gy  = $_POST['graduation_year'] !== '' ? $_POST['graduation_year'] : null;
                $des = $_POST['designation'] ?? '';
                $s = $conn->prepare(
                    "INSERT INTO alumni (alumni_id, graduation_year, designation)
                     VALUES (?, ?, ?)"
                );
                $s->bind_param("iis", $user_id, $gy, $des);
                $s->execute();

            } elseif ($role === 'Admin') {
                $arole = $_POST['admin_role'] ?? 'Moderator';
                $s = $conn->prepare(
                    "INSERT INTO admins (admin_id, role) VALUES (?, ?)"
                );
                $s->bind_param("is", $user_id, $arole);
                $s->execute();
            }

            // --- 6. Create empty profile row ---
            $p = $conn->prepare("INSERT INTO profile (user_id) VALUES (?)");
            $p->bind_param("i", $user_id);
            $p->execute();

            // --- 7. Redirect to login ---
            header("Location: login.php?registered=1");
            exit;
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}

$page_title = 'Sign up';
include 'includes/header.php';
?>

<main class="container container--narrow" style="padding: 64px 0;">

    <div class="card">
        <h2>Join the network</h2>
        <p class="sub">Tell us a little about yourself — you can edit any of this later from your profile.</p>

        <?php if ($error): ?>
            <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php" autocomplete="off">

            <!-- Role tabs -->
            <div class="role-tabs">
                <input type="radio" name="role" id="role-student" value="Student" checked>
                <label for="role-student">Student</label>

                <input type="radio" name="role" id="role-alumni" value="Alumni">
                <label for="role-alumni">Alumni</label>

                <input type="radio" name="role" id="role-admin" value="Admin">
                <label for="role-admin">Admin</label>
            </div>

            <!-- Common fields -->
            <div class="form-row">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-row two-col">
                <div>
                    <label for="gmail">Email</label>
                    <input type="email" id="gmail" name="gmail" required>
                </div>
                <div>
                    <label for="phone_no">Phone</label>
                    <input type="tel" id="phone_no" name="phone_no">
                </div>
            </div>

            <div class="form-row two-col">
                <div>
                    <label for="dob">Date of birth</label>
                    <input type="date" id="dob" name="dob">
                </div>
                <div>
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Prefer not to say</option>
                        <option>Male</option>
                        <option>Female</option>
                        <option>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>

            <!-- Role-specific blocks (toggle with JS) -->
            <div id="block-student" class="role-block">
                <div class="form-row two-col">
                    <div>
                        <label for="dept">Department</label>
                        <input type="text" id="dept" name="dept" placeholder="e.g. CSE">
                    </div>
                    <div>
                        <label for="batch">Batch</label>
                        <input type="text" id="batch" name="batch" placeholder="e.g. 2023">
                    </div>
                </div>
                <div class="form-row two-col">
                    <div>
                        <label for="semester">Semester</label>
                        <input type="text" id="semester" name="semester" placeholder="e.g. 5">
                    </div>
                    <div>
                        <label for="cgpa">CGPA</label>
                        <input type="number" id="cgpa" name="cgpa" step="0.01" min="0" max="4" placeholder="e.g. 3.75">
                    </div>
                </div>
            </div>

            <div id="block-alumni" class="role-block" style="display:none;">
                <div class="form-row two-col">
                    <div>
                        <label for="graduation_year">Graduation year</label>
                        <input type="number" id="graduation_year" name="graduation_year" min="1950" max="2099">
                    </div>
                    <div>
                        <label for="designation">Current role</label>
                        <input type="text" id="designation" name="designation" placeholder="e.g. Software Engineer">
                    </div>
                </div>
            </div>

            <div id="block-admin" class="role-block" style="display:none;">
                <div class="form-row">
                    <label for="admin_role">Admin role</label>
                    <select id="admin_role" name="admin_role">
                        <option>Moderator</option>
                        <option>Editor</option>
                        <option>Super-admin</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn--full">Create account</button>

            <p style="margin-top:18px; text-align:center; color: var(--muted); font-size:14px;">
                Already a member? <a href="login.php" class="btn-link">Sign in</a>
            </p>
        </form>
    </div>
</main>

<script>
// Toggle role-specific fields
const radios = document.querySelectorAll('input[name="role"]');
const blocks = {
    Student: document.getElementById('block-student'),
    Alumni:  document.getElementById('block-alumni'),
    Admin:   document.getElementById('block-admin')
};
radios.forEach(r => r.addEventListener('change', () => {
    Object.values(blocks).forEach(b => b.style.display = 'none');
    blocks[r.value].style.display = 'block';
}));
</script>

<?php include 'includes/footer.php'; ?>
