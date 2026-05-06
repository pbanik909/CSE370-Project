# CampusConnect — University Student Mini-LinkedIn

A web application for students, alumni, and admins to connect, share posts, and build profiles. Built with **PHP + MySQL + HTML/CSS** to run on **XAMPP**.

---

## Project structure

```
campus_connect/
├── config/
│   └── db.php              # database connection
├── css/
│   └── style.css           # all styling
├── includes/
│   ├── header.php          # reusable header + nav
│   └── footer.php          # reusable footer
├── sql/
│   └── database.sql        # schema (import this once)
├── uploads/                # profile pics & post images go here
├── index.php               # landing page
├── register.php            # FEATURE 1 — Sign up
├── login.php               # FEATURE 2 — Login
├── logout.php              # session destroy
├── profile.php             # FEATURE 3 — Profile
└── feed.php                # FEATURE 4 — Feed + Create Post
```

---

## Setup — line by line

### Step 1 — Install XAMPP

1. Download from https://www.apachefriends.org/
2. Install with default settings.
3. Note the install path — usually `C:\xampp` on Windows or `/Applications/XAMPP` on Mac.

### Step 2 — Place the project in `htdocs`

1. Open the XAMPP folder.
2. Open the `htdocs` folder inside it.
3. Copy the entire `campus_connect` folder **into** `htdocs`.
   - Final path: `C:\xampp\htdocs\campus_connect\` (Windows) or `/Applications/XAMPP/htdocs/campus_connect/` (Mac)

### Step 3 — Start Apache and MySQL

1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Both should turn green.

### Step 4 — Create the database

1. Open your browser and go to: `http://localhost/phpmyadmin`
2. On the left sidebar, click **New** (or **Databases** at top).
3. Click the **Import** tab at the top.
4. Click **Choose File** and pick `campus_connect/sql/database.sql`.
5. Scroll down and click **Import**.
6. You should now see `campus_connect` in the left sidebar with 6 tables: `users`, `students`, `alumni`, `admins`, `profile`, `post_manage`.

### Step 5 — Open the project in VS Code

1. Open VS Code.
2. **File → Open Folder** → choose the `campus_connect` folder inside `htdocs`.
3. Recommended extensions: **PHP Intelephense**, **PHP Server**, **HTML CSS Support**.

### Step 6 — Open the site in your browser

1. Go to: `http://localhost/campus_connect/`
2. You should see the **CampusConnect** landing page.

### Step 7 — Try the 4 features

1. Click **Create an account**, fill out the form, submit.
2. Sign in with the email + password you used.
3. You'll land on the **Feed**. Try writing a post.
4. Click **Profile** — fill in your bio, headline, links, picture. Save.

If anything fails, see the **Troubleshooting** section below.

---

## Database credentials

The project uses XAMPP's default MySQL settings:

| Setting  | Value           |
| -------- | --------------- |
| Host     | `localhost`     |
| Username | `root`          |
| Password | *(empty)*       |
| Database | `campus_connect`|

If you've changed your MySQL password, edit `config/db.php` accordingly.

---

## What's done (4 of 12 features)

- ✅ **1. Sign up** — Student / Alumni / Admin, with role-specific fields
- ✅ **2. Login & logout** — session-based auth, hashed passwords
- ✅ **3. Profile** — view + edit, with picture upload
- ✅ **4. Feed + create post** — text + image, listed newest-first

## What's next (planned features 5–12)

5. Connection requests (send / accept / reject) — uses `request` table
6. Messaging — uses `message` table
7. Job posts — uses `job_post` table
8. Apply to jobs — uses `can_apply` table
9. Comments + reactions on posts
10. Repost / share — uses `repost` table
11. Search users + posts
12. Admin moderation — uses `manages` table

We'll wire these up one by one, each touching the schema you've already designed.

---

## Troubleshooting

**"Database connection failed"**
→ MySQL isn't running in XAMPP, or the password is wrong. Open XAMPP Control Panel → Start MySQL.

**Page shows raw PHP code**
→ Apache isn't running, or you're opening the file directly. Always go through `http://localhost/campus_connect/...`, never `file://`.

**Profile picture / post image won't upload**
→ The `uploads/` folder needs write permission. On Linux/Mac: `chmod 755 uploads`. On Windows it works by default.

**"Access denied for user 'root'"**
→ You set a MySQL password. Open `config/db.php` and put it in the `$pass` variable.

**Port 80 already in use** (Apache won't start)
→ Skype or IIS is using it. In XAMPP, click **Config** next to Apache → `httpd.conf` → change `Listen 80` to `Listen 8080`. Then visit `http://localhost:8080/campus_connect/`.

---

## Security notes (for later)

This starter uses prepared statements and `password_hash()`, which is the right baseline. Before going to production:
- Add CSRF tokens to forms
- Validate file uploads more strictly (MIME, size)
- Use HTTPS
- Move database credentials to environment variables

---
