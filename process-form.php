<?php
/**
 * Rental Application Processing Script
 * Handles input validation, sanitization, secure file uploads, PDO database storage,
 * and email notifications using PHPMailer via Hostinger SMTP.
 */

// Include config file
require_once 'config.php';

// Enable error reporting for debugging, but hide on production
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$errors = [];
$formData = [];

// ── 1. SANITIZE AND CAPTURE INPUTS ──────────────────────────
$fields = [
    'firstName' => FILTER_DEFAULT,
    'lastName' => FILTER_DEFAULT,
    'phone' => FILTER_DEFAULT,
    'email' => FILTER_SANITIZE_EMAIL,
    'address' => FILTER_DEFAULT,
    'city' => FILTER_DEFAULT,
    'state' => FILTER_DEFAULT,
    'zipCode' => FILTER_DEFAULT,
    'vehicle' => FILTER_DEFAULT,
    'duration' => FILTER_DEFAULT,
    'insuranceOption' => FILTER_DEFAULT
];

foreach ($fields as $field => $filter) {
    $val = $_POST[$field] ?? '';
    $val = trim($val);
    if ($filter === FILTER_SANITIZE_EMAIL) {
        $val = filter_var($val, FILTER_SANITIZE_EMAIL);
    } else {
        $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }
    $formData[$field] = $val;
}

// ── 2. VALIDATION ──────────────────────────────────────────
// Required fields validation
$requiredFields = ['firstName', 'lastName', 'phone', 'email', 'address', 'city', 'state', 'zipCode', 'vehicle', 'duration', 'insuranceOption'];
foreach ($requiredFields as $req) {
    if (empty($formData[$req])) {
        $errors[] = ucfirst(preg_replace('/(?<!\ )[A-Z]/', ' $0', $req)) . ' is required.';
    }
}

// Email format validation
if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address format.';
}

// Phone number validation
if (!empty($formData['phone']) && !preg_match('/^[0-9+\(\)#\.\-\s]{7,20}$/', $formData['phone'])) {
    $errors[] = 'Invalid phone number format.';
}

// ── 3. FILE UPLOADS HANDLING & VALIDATION ──────────────────
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Document upload slots definition
$uploadSlots = [
    'fileLicense' => ['required' => true, 'label' => 'Driver License'],
    'fileInsurance' => ['required' => true, 'label' => 'Insurance Card'],
    'fileAddress' => ['required' => true, 'label' => 'Proof of Address'],
    'fileSelfie' => ['required' => false, 'label' => 'Selfie with License']
];

$allowedMimeTypes = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'application/pdf'
];

$maxFileSize = 10 * 1024 * 1024; // 10MB
$filePaths = [
    'fileLicense' => '',
    'fileInsurance' => '',
    'fileAddress' => '',
    'fileSelfie' => null
];

foreach ($uploadSlots as $slot => $info) {
    $fileUploaded = isset($_FILES[$slot]) && $_FILES[$slot]['error'] !== UPLOAD_ERR_NO_FILE;
    
    if ($info['required'] && !$fileUploaded) {
        $errors[] = $info['label'] . ' document is required.';
        continue;
    }
    
    if ($fileUploaded) {
        $file = $_FILES[$slot];
        
        // Check standard upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed for ' . $info['label'] . '. Error Code: ' . $file['error'];
            continue;
        }
        
        // Validate file size
        if ($file['size'] > $maxFileSize) {
            $errors[] = $info['label'] . ' exceeds the maximum file size of 10MB.';
            continue;
        }
        
        // Validate MIME type securely using PHP's finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = $info['label'] . ' must be a PDF, JPEG, JPG, or PNG document.';
            continue;
        }
        
        // Generate unique name to prevent collisions and execution
        $origExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeExtension = strtolower($origExtension);
        // Double check extension mapping for security
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($safeExtension, $allowedExtensions)) {
            $errors[] = 'Unsupported file extension for ' . $info['label'] . '.';
            continue;
        }
        
        $uniqueFilename = bin2hex(random_bytes(16)) . '.' . $safeExtension;
        $destination = $uploadDir . $uniqueFilename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Save relative web path in database
            $filePaths[$slot] = 'uploads/' . $uniqueFilename;
        } else {
            $errors[] = 'Failed to save ' . $info['label'] . ' to storage.';
        }
    }
}

// ── 4. REDIRECT IF VALIDATION FAILED ──────────────────────
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $formData;
    header('Location: applyfor.php');
    exit;
}

// ── 5. DATABASE INTEGRATION (PDO) ─────────────────────────
// Generate unique reference code
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$refCode = 'MYC-';
for ($i = 0; $i < 4; $i++) {
    $refCode .= $chars[rand(0, strlen($chars) - 1)];
}
$refCode .= '-AZ';

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$dbSuccess = false;
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    $sql = "INSERT INTO `rental_applications` (
                `reference_code`, `first_name`, `last_name`, `phone`, `email`,
                `address`, `city`, `state`, `zip_code`, `vehicle`, `duration`,
                `insurance_option`, `file_license_path`, `file_insurance_path`,
                `file_address_path`, `file_selfie_path`, `status`, `ip_address`, `user_agent`
            ) VALUES (
                :reference_code, :first_name, :last_name, :phone, :email,
                :address, :city, :state, :zip_code, :vehicle, :duration,
                :insurance_option, :file_license_path, :file_insurance_path,
                :file_address_path, :file_selfie_path, 'Pending', :ip_address, :user_agent
            )";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':reference_code'      => $refCode,
        ':first_name'          => $formData['firstName'],
        ':last_name'           => $formData['lastName'],
        ':phone'               => $formData['phone'],
        ':email'               => $formData['email'],
        ':address'             => $formData['address'],
        ':city'                => $formData['city'],
        ':state'               => $formData['state'],
        ':zip_code'            => $formData['zipCode'],
        ':vehicle'             => $formData['vehicle'],
        ':duration'            => $formData['duration'],
        ':insurance_option'    => $formData['insuranceOption'],
        ':file_license_path'   => $filePaths['fileLicense'],
        ':file_insurance_path' => $filePaths['fileInsurance'],
        ':file_address_path'   => $filePaths['fileAddress'],
        ':file_selfie_path'    => $filePaths['fileSelfie'],
        ':ip_address'          => $ipAddress,
        ':user_agent'          => $userAgent
    ]);
    
    $dbSuccess = true;
} catch (PDOException $e) {
    // If DB fails, log error, but proceed to send email notification so data is not lost!
    error_log('Database Insertion Error: ' . $e->getMessage());
}

// ── 6. EMAIL DISPATCH (PHPMailer SMTP) ────────────────────
require_once 'phpmailer/Exception.php';
require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Send Admin Notification Email
$mailAdmin = new PHPMailer(true);
$mailSent = false;

try {
    // SMTP Settings
    $mailAdmin->isSMTP();
    $mailAdmin->Host       = SMTP_HOST;
    $mailAdmin->SMTPAuth   = true;
    $mailAdmin->Username   = SMTP_USER;
    $mailAdmin->Password   = SMTP_PASS;
    $mailAdmin->Port       = SMTP_PORT;
    $mailAdmin->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    
    // Sender / Receiver
    $mailAdmin->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mailAdmin->addAddress(ADMIN_EMAIL);
    
    // Attachments
    foreach ($filePaths as $slot => $path) {
        if (!empty($path) && file_exists(__DIR__ . '/' . $path)) {
            $mailAdmin->addAttachment(__DIR__ . '/' . $path, basename($path));
        }
    }
    
    // Content
    $mailAdmin->isHTML(true);
    $mailAdmin->Subject = "New Rental Application: " . $refCode . " - " . $formData['firstName'] . ' ' . $formData['lastName'];
    
    $bodyHtml = "
    <h2>New Rental Application Submitted</h2>
    <p>A new auto leasing application has been received via the online portal.</p>
    <table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%; max-width:600px; font-family:Arial, sans-serif;'>
        <tr style='background-color:#f2f2f2;'>
            <th colspan='2' align='left'>Submission Details</th>
        </tr>
        <tr><td><strong>Reference Code</strong></td><td style='color:#D4AF37; font-weight:bold;'>{$refCode}</td></tr>
        <tr><td><strong>Applicant Name</strong></td><td>{$formData['firstName']} {$formData['lastName']}</td></tr>
        <tr><td><strong>Phone Number</strong></td><td>{$formData['phone']}</td></tr>
        <tr><td><strong>Email Address</strong></td><td>{$formData['email']}</td></tr>
        <tr><td><strong>Street Address</strong></td><td>{$formData['address']}</td></tr>
        <tr><td><strong>City, State, ZIP</strong></td><td>{$formData['city']}, {$formData['state']} {$formData['zipCode']}</td></tr>
        <tr><td><strong>Selected Vehicle</strong></td><td>{$formData['vehicle']}</td></tr>
        <tr><td><strong>Rental Plan Duration</strong></td><td>{$formData['duration']} Plan</td></tr>
        <tr><td><strong>Insurance Selection</strong></td><td>{$formData['insuranceOption']}</td></tr>
        <tr><td><strong>IP Address</strong></td><td>{$ipAddress}</td></tr>
        <tr><td><strong>Submission Time</strong></td><td>" . date('Y-m-d H:i:s') . "</td></tr>
    </table>
    <p>Uploaded documents have been attached to this email and are saved securely in the database.</p>
    ";
    
    $mailAdmin->Body = $bodyHtml;
    $mailAdmin->send();
    $mailSent = true;
    
} catch (Exception $e) {
    error_log('Admin PHPMailer Error: ' . $mailAdmin->ErrorInfo);
}

// Send Applicant Confirmation Email
if ($formData['email']) {
    $mailApplicant = new PHPMailer(true);
    try {
        $mailApplicant->isSMTP();
        $mailApplicant->Host       = SMTP_HOST;
        $mailApplicant->SMTPAuth   = true;
        $mailApplicant->Username   = SMTP_USER;
        $mailApplicant->Password   = SMTP_PASS;
        $mailApplicant->Port       = SMTP_PORT;
        $mailApplicant->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        
        $mailApplicant->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mailApplicant->addAddress($formData['email']);
        
        $mailApplicant->isHTML(true);
        $mailApplicant->Subject = "Application Received - Reference Code: " . $refCode;
        
        $mailApplicant->Body = "
        <div style='font-family:Arial, sans-serif; max-width:600px; line-height:1.6;'>
            <h2 style='color:#D4AF37;'>Dear {$formData['firstName']},</h2>
            <p>Thank you for submitting your rental application to MyCarz USA!</p>
            <p>We have successfully received your request. Our verification desk is reviewing your documents and will contact you via phone or email shortly to confirm the setup details.</p>
            <div style='background-color:#f9f9f9; border-left:4px solid #D4AF37; padding:15px; margin:20px 0;'>
                <strong>Your Application Reference Code:</strong>
                <span style='font-size:1.2rem; font-weight:bold; color:#D4AF37; margin-left:10px;'>{$refCode}</span>
            </div>
            <p>Please keep this reference code for your records.</p>
            <hr style='border:0; border-top:1px solid #eee; margin:30px 0;'>
            <p style='font-size:0.9rem; color:#666;'>This is an automated confirmation email. Please do not reply directly to this message.</p>
        </div>
        ";
        
        $mailApplicant->send();
    } catch (Exception $e) {
        error_log('Applicant PHPMailer Error: ' . $mailApplicant->ErrorInfo);
    }
}

// ── 7. SUCCESS REDIRECTION ────────────────────────────────
$_SESSION['success_ref'] = $refCode;
header('Location: applyfor.php?success=1');
exit;
