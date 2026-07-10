<?php
/**
 * Rental Application, Detailing Quote, and Vehicle Inquiry Processing Script
 * Handles input validation, sanitization, secure file uploads, database storage,
 * and email notifications via PHPMailer.
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

$formType = $_POST['formType'] ?? 'apply';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Load PHPMailer
require_once 'phpmailer/Exception.php';
require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper to generate reference codes
function generateRefCode($prefix) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = $prefix;
    for ($i = 0; $i < 4; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code . '-AZ';
}

// Helper to send emails
function sendSMTPEmails($to, $subject, $body, $attachments = []) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        foreach ($attachments as $path) {
            if (!empty($path) && file_exists(__DIR__ . '/' . $path)) {
                $mail->addAttachment(__DIR__ . '/' . $path, basename($path));
            }
        }
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer error targeting {$to}: " . $mail->ErrorInfo);
        return false;
    }
}

// ── PROCESS BASED ON FORM TYPE ────────────────────────────

if ($formType === 'apply') {
    // ── 1. APPLY ONLINE FORM PROCESSING ──
    $errors = [];
    $formData = [];
    
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
        $val = trim($_POST[$field] ?? '');
        if ($filter === FILTER_SANITIZE_EMAIL) {
            $val = filter_var($val, FILTER_SANITIZE_EMAIL);
        } else {
            $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        }
        $formData[$field] = $val;
    }

    // Required fields validation
    $requiredFields = ['firstName', 'lastName', 'phone', 'email', 'address', 'city', 'state', 'zipCode', 'vehicle', 'duration', 'insuranceOption'];
    foreach ($requiredFields as $req) {
        if (empty($formData[$req])) {
            $errors[] = ucfirst(preg_replace('/(?<!\ )[A-Z]/', ' $0', $req)) . ' is required.';
        }
    }

    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address format.';
    }

    if (!empty($formData['phone']) && !preg_match('/^[0-9+\(\)#\.\-\s]{7,20}$/', $formData['phone'])) {
        $errors[] = 'Invalid phone number format.';
    }

    // Secure File Uploads
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uploadSlots = [
        'fileLicense' => ['required' => true, 'label' => 'Driver License'],
        'fileInsurance' => ['required' => true, 'label' => 'Insurance Card'],
        'fileAddress' => ['required' => true, 'label' => 'Proof of Address'],
        'fileSelfie' => ['required' => false, 'label' => 'Selfie with License']
    ];

    $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    $filePaths = ['fileLicense' => '', 'fileInsurance' => '', 'fileAddress' => '', 'fileSelfie' => null];

    foreach ($uploadSlots as $slot => $info) {
        $fileUploaded = isset($_FILES[$slot]) && $_FILES[$slot]['error'] !== UPLOAD_ERR_NO_FILE;
        
        if ($info['required'] && !$fileUploaded) {
            $errors[] = $info['label'] . ' document is required.';
            continue;
        }
        
        if ($fileUploaded) {
            $file = $_FILES[$slot];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload failed for ' . $info['label'] . '. Error Code: ' . $file['error'];
                continue;
            }
            if ($file['size'] > $maxFileSize) {
                $errors[] = $info['label'] . ' exceeds the maximum file size of 10MB.';
                continue;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                $errors[] = $info['label'] . ' must be a PDF, JPEG, JPG, or PNG document.';
                continue;
            }
            
            $safeExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($safeExtension, ['pdf', 'jpg', 'jpeg', 'png'])) {
                $errors[] = 'Unsupported file extension for ' . $info['label'] . '.';
                continue;
            }
            
            $uniqueFilename = bin2hex(random_bytes(16)) . '.' . $safeExtension;
            $destination = $uploadDir . $uniqueFilename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $filePaths[$slot] = 'uploads/' . $uniqueFilename;
            } else {
                $errors[] = 'Failed to save ' . $info['label'] . ' to storage.';
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_data'] = $formData;
        header('Location: applyfor.php');
        exit;
    }

    $refCode = generateRefCode('MYC-');

    // Database Insertion (PDO)
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
    } catch (PDOException $e) {
        error_log('Database Insertion Error: ' . $e->getMessage());
    }

    // HTML Emails Bodies
    $adminSubject = "New Rental Application: {$refCode} - {$formData['firstName']} {$formData['lastName']}";
    $adminBodyHtml = "
    <h2>New Rental Application Submitted</h2>
    <table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%; max-width:600px; font-family:Arial, sans-serif;'>
        <tr style='background-color:#f2f2f2;'><th colspan='2' align='left'>Submission Details</th></tr>
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
    </table>
    <p>Uploaded documents have been attached to this email.</p>
    ";

    $applicantSubject = "Application Received - Reference Code: " . $refCode;
    $applicantBodyHtml = "
    <div style='font-family:Arial, sans-serif; max-width:600px; line-height:1.6;'>
        <h2 style='color:#D4AF37;'>Dear {$formData['firstName']},</h2>
        <p>Thank you for submitting your rental application to MyCarz USA!</p>
        <p>We have successfully received your request. Our verification desk is reviewing your documents and will contact you via phone or email shortly.</p>
        <div style='background-color:#f9f9f9; border-left:4px solid #D4AF37; padding:15px; margin:20px 0;'>
            <strong>Your Application Reference Code:</strong>
            <span style='font-size:1.2rem; font-weight:bold; color:#D4AF37; margin-left:10px;'>{$refCode}</span>
        </div>
        <p>Please keep this reference code for your records.</p>
    </div>
    ";

    // Send emails
    sendSMTPEmails(ADMIN_EMAIL, $adminSubject, $adminBodyHtml, array_filter($filePaths));
    if ($formData['email']) {
        sendSMTPEmails($formData['email'], $applicantSubject, $applicantBodyHtml);
    }

    $_SESSION['success_ref'] = $refCode;
    header('Location: applyfor.php?success=1');
    exit;

} elseif ($formType === 'detailing') {
    // ── 2. CAR DETAILING QUOTE PROCESSING ──
    $fullName = htmlspecialchars(trim($_POST['fullName'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phone    = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $make     = htmlspecialchars(trim($_POST['vehicleMake'] ?? ''), ENT_QUOTES, 'UTF-8');
    $model    = htmlspecialchars(trim($_POST['vehicleModel'] ?? ''), ENT_QUOTES, 'UTF-8');
    $service  = htmlspecialchars(trim($_POST['service'] ?? ''), ENT_QUOTES, 'UTF-8');
    $date     = htmlspecialchars(trim($_POST['appointmentDate'] ?? ''), ENT_QUOTES, 'UTF-8');
    $message  = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($fullName) || empty($phone) || empty($email) || empty($make) || empty($model) || empty($service)) {
        header('Location: detailing.html?error=1');
        exit;
    }

    $refCode = generateRefCode('MYC-DTL-');

    // Database Insertion
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        $sql = "INSERT INTO `detailing_quotes` (
                    `reference_code`, `full_name`, `phone`, `email`, `vehicle_make`,
                    `vehicle_model`, `service`, `appointment_date`, `message`,
                    `status`, `ip_address`, `user_agent`
                ) VALUES (
                    :reference_code, :full_name, :phone, :email, :vehicle_make,
                    :vehicle_model, :service, :appointment_date, :message,
                    'Pending', :ip_address, :user_agent
                )";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':reference_code'    => $refCode,
            ':full_name'         => $fullName,
            ':phone'             => $phone,
            ':email'             => $email,
            ':vehicle_make'      => $make,
            ':vehicle_model'     => $model,
            ':service'           => $service,
            ':appointment_date'  => empty($date) ? null : $date,
            ':message'           => $message,
            ':ip_address'        => $ipAddress,
            ':user_agent'        => $userAgent
        ]);
    } catch (PDOException $e) {
        error_log('Database Detailing Error: ' . $e->getMessage());
    }

    // HTML Emails
    $adminSubject = "New Detailing Quote Request: {$refCode} - {$fullName}";
    $adminBodyHtml = "
    <h2>New Detailing Quote Request Received</h2>
    <table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%; max-width:600px; font-family:Arial, sans-serif;'>
        <tr style='background-color:#f2f2f2;'><th colspan='2' align='left'>Quote Parameters</th></tr>
        <tr><td><strong>Reference Code</strong></td><td style='color:#D4AF37; font-weight:bold;'>{$refCode}</td></tr>
        <tr><td><strong>Client Name</strong></td><td>{$fullName}</td></tr>
        <tr><td><strong>Phone Number</strong></td><td>{$phone}</td></tr>
        <tr><td><strong>Email Address</strong></td><td>{$email}</td></tr>
        <tr><td><strong>Vehicle Make/Model</strong></td><td>{$make} {$model}</td></tr>
        <tr><td><strong>Service Needed</strong></td><td>{$service}</td></tr>
        <tr><td><strong>Preferred Date</strong></td><td>" . (empty($date) ? 'N/A' : $date) . "</td></tr>
        <tr><td><strong>Additional Message</strong></td><td>{$message}</td></tr>
        <tr><td><strong>Client IP</strong></td><td>{$ipAddress}</td></tr>
    </table>
    ";

    $applicantSubject = "Detailing Quote Confirmed - Reference: " . $refCode;
    $applicantBodyHtml = "
    <div style='font-family:Arial, sans-serif; max-width:600px; line-height:1.6;'>
        <h2 style='color:#D4AF37;'>Dear {$fullName},</h2>
        <p>Thank you for requesting a detailing quote from MyCarz USA!</p>
        <p>Our detailing representatives will calculate the quote and contact you shortly with scheduling details.</p>
        <div style='background-color:#f9f9f9; border-left:4px solid #D4AF37; padding:15px; margin:20px 0;'>
            <strong>Your Detail Request ID:</strong>
            <span style='font-size:1.2rem; font-weight:bold; color:#D4AF37; margin-left:10px;'>{$refCode}</span>
        </div>
    </div>
    ";

    sendSMTPEmails(ADMIN_EMAIL, $adminSubject, $adminBodyHtml);
    if ($email) {
        sendSMTPEmails($email, $applicantSubject, $applicantBodyHtml);
    }

    header("Location: detailing.html?success=1&ref=" . urlencode($refCode));
    exit;

} elseif ($formType === 'inquiry') {
    // ── 3. VEHICLE INQUIRY FORM PROCESSING ──
    $fullName = htmlspecialchars(trim($_POST['fullName'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phone    = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $type     = htmlspecialchars(trim($_POST['vehicleType'] ?? ''), ENT_QUOTES, 'UTF-8');
    $brand    = htmlspecialchars(trim($_POST['preferredBrand'] ?? ''), ENT_QUOTES, 'UTF-8');
    $minB     = htmlspecialchars(trim($_POST['minBudget'] ?? ''), ENT_QUOTES, 'UTF-8');
    $maxB     = htmlspecialchars(trim($_POST['maxBudget'] ?? ''), ENT_QUOTES, 'UTF-8');
    $reqs     = htmlspecialchars(trim($_POST['requirements'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($fullName) || empty($phone) || empty($email) || empty($type) || empty($brand) || empty($minB) || empty($maxB)) {
        header('Location: usedcars.html?error=1');
        exit;
    }

    $refCode = generateRefCode('MYC-REQ-');

    // Database Insertion
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        $sql = "INSERT INTO `vehicle_inquiries` (
                    `reference_code`, `full_name`, `phone`, `email`, `vehicle_type`,
                    `preferred_brand`, `min_budget`, `max_budget`, `requirements`,
                    `status`, `ip_address`, `user_agent`
                ) VALUES (
                    :reference_code, :full_name, :phone, :email, :vehicle_type,
                    :preferred_brand, :min_budget, :max_budget, :requirements,
                    'Pending', :ip_address, :user_agent
                )";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':reference_code'    => $refCode,
            ':full_name'         => $fullName,
            ':phone'             => $phone,
            ':email'             => $email,
            ':vehicle_type'      => $type,
            ':preferred_brand'   => $brand,
            ':min_budget'        => $minB,
            ':max_budget'        => $maxB,
            ':requirements'      => $reqs,
            ':ip_address'        => $ipAddress,
            ':user_agent'        => $userAgent
        ]);
    } catch (PDOException $e) {
        error_log('Database Inquiry Error: ' . $e->getMessage());
    }

    // HTML Emails
    $adminSubject = "New Vehicle Inquiry Request: {$refCode} - {$fullName}";
    $adminBodyHtml = "
    <h2>New Vehicle Search Inquiry Received</h2>
    <table border='1' cellpadding='8' cellspacing='0' style='border-collapse:collapse; width:100%; max-width:600px; font-family:Arial, sans-serif;'>
        <tr style='background-color:#f2f2f2;'><th colspan='2' align='left'>Inquiry Details</th></tr>
        <tr><td><strong>Inquiry Code</strong></td><td style='color:#D4AF37; font-weight:bold;'>{$refCode}</td></tr>
        <tr><td><strong>Client Name</strong></td><td>{$fullName}</td></tr>
        <tr><td><strong>Phone Number</strong></td><td>{$phone}</td></tr>
        <tr><td><strong>Email Address</strong></td><td>{$email}</td></tr>
        <tr><td><strong>Vehicle Type Wanted</strong></td><td>{$type}</td></tr>
        <tr><td><strong>Preferred Brand</strong></td><td>{$brand}</td></tr>
        <tr><td><strong>Budget Range</strong></td><td>{$minB} to {$maxB}</td></tr>
        <tr><td><strong>Client Specifications</strong></td><td>{$reqs}</td></tr>
        <tr><td><strong>Client IP</strong></td><td>{$ipAddress}</td></tr>
    </table>
    ";

    $applicantSubject = "Vehicle Inquiry Submitted - ID: " . $refCode;
    $applicantBodyHtml = "
    <div style='font-family:Arial, sans-serif; max-width:600px; line-height:1.6;'>
        <h2 style='color:#D4AF37;'>Dear {$fullName},</h2>
        <p>We have successfully received your vehicle search inquiry!</p>
        <p>Our sales agents will verify our active inventories and partner networks to find models fitting your preferences and budget, and contact you shortly.</p>
        <div style='background-color:#f9f9f9; border-left:4px solid #D4AF37; padding:15px; margin:20px 0;'>
            <strong>Your Search Inquiry ID:</strong>
            <span style='font-size:1.2rem; font-weight:bold; color:#D4AF37; margin-left:10px;'>{$refCode}</span>
        </div>
    </div>
    ";

    sendSMTPEmails(ADMIN_EMAIL, $adminSubject, $adminBodyHtml);
    if ($email) {
        sendSMTPEmails($email, $applicantSubject, $applicantBodyHtml);
    }

    header("Location: usedcars.html?success=1&ref=" . urlencode($refCode));
    exit;
}
