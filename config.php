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
define('SMTP_HOST', 'smtp.hostinger.com');   // Hostinger Outgoing SMTP Server
define('SMTP_PORT', 465);                    // Port 465 (SSL/TLS secure connection)
define('SMTP_USER', 'booking@mycarzrentalsusa.com'); // Hostinger SMTP username
define('SMTP_PASS', 'Adminss@123456');               // Hostinger SMTP password
define('SMTP_SECURE', 'ssl');                // Encryption mode 'ssl' for port 465
define('SMTP_FROM_EMAIL', 'booking@mycarzrentalsusa.com'); // Sender email
define('SMTP_FROM_NAME', 'MyCarz USA');

// ── ADMIN CONTACT EMAIL ───────────────────────────────────
define('ADMIN_EMAIL', 'booking@mycarzrentalsusa.com');  // Target email where notification alerts are sent
