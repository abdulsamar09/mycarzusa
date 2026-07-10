<?php
/**
 * Configuration file for MyCarz USA rental application portal.
 * Edit database and SMTP credentials below to match your Hostinger hosting setup.
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not permitted');
}

// Session security configurations
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    // If running on HTTPS, uncomment the line below:
    // ini_set('session.cookie_secure', 1);
}

// ── DATABASE SETTINGS ──────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'your_database_user');     // Replace with Hostinger Database User
define('DB_PASS', 'your_database_password'); // Replace with Hostinger Database Password
define('DB_NAME', 'your_database_name');     // Replace with Hostinger Database Name

// ── SMTP EMAIL SETTINGS ────────────────────────────────────
define('SMTP_HOST', 'smtp.hostinger.com');   // Hostinger SMTP host
define('SMTP_PORT', 465);                    // Standard SSL port. Use 587 if TLS is used
define('SMTP_USER', 'your_smtp_user');       // SMTP email login (e.g. info@yourdomain.com)
define('SMTP_PASS', 'your_smtp_password');   // SMTP email password
define('SMTP_SECURE', 'ssl');                // ssl (port 465) or tls (port 587)
define('SMTP_FROM_EMAIL', 'your_smtp_user'); // Must match SMTP user on Hostinger to avoid SMTP errors
define('SMTP_FROM_NAME', 'MyCarz USA');

// ── ADMIN CONTACT EMAIL ───────────────────────────────────
define('ADMIN_EMAIL', 'admin@mycarzusa.com');  // Email address to receive notification reports
