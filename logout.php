<?php
// =======================================================
// Logout — destroy the session and go home
// =======================================================
session_start();
session_unset();
session_destroy();
header("Location: index.php");
exit;
?>
