<?php
// Silencer & Redirect
// Prevent directory listing by redirecting to the main admin dashboard
header("Location: ../?p=admin_dashboard");
exit;
?>