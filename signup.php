<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';
function reply($ok, $message, $code=200) { http_response_code($code); echo json_encode(['success'=>$ok,'message'=>$message]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(false, 'Invalid request.', 405);
$name=trim($_POST['full_name']??''); $email=strtolower(trim($_POST['email']??'')); $phone=preg_replace('/\s+/', '', trim($_POST['phone']??''));
$password=$_POST['password']??''; $confirm=$_POST['confirm_password']??''; $role=$_POST['role']??'customer'; $adminCode=$_POST['admin_code']??'';
if ($name==='' || $email==='' || $phone==='' || $password==='') reply(false,'Please fill in all required fields.');
if (!filter_var($email,FILTER_VALIDATE_EMAIL)) reply(false,'Please enter a valid email address.');
if (strlen($password)<8) reply(false,'Password must be at least 8 characters.');
if ($password!==$confirm) reply(false,'Password and confirmation do not match.');
if (!preg_match('/^(?:\+?8801|01)[3-9]\d{8}$/',$phone)) reply(false,'Enter a valid Bangladeshi mobile number.');
if (!in_array($role,['customer','technician','admin'],true)) reply(false,'Please select a valid account type.');
if ($role==='admin') {
    require_once 'admin_config.php';
    if (ADMIN_SIGNUP_CODE==='CHANGE-THIS-ADMIN-SETUP-CODE' || !hash_equals(ADMIN_SIGNUP_CODE, $adminCode)) {
        reply(false,'Invalid administrator registration code. Ask the website owner to configure it.');
    }
}
$title=trim($_POST['professional_title']??''); $experience=(int)($_POST['experience_years']??0); $areas=trim($_POST['service_areas']??'');
$documentType=$_POST['document_type']??'national_id';
if ($role==='technician') {
    if ($title==='' || $areas==='') reply(false,'Technicians must provide their specialty and service area.');
    if ($experience<0 || $experience>60) reply(false,'Enter a valid number of experience years.');
    if (empty($_FILES['verification_document']) || $_FILES['verification_document']['error']!==UPLOAD_ERR_OK) reply(false,'Please upload your NID, trade license or certificate.');
    if ($_FILES['verification_document']['size']>5*1024*1024) reply(false,'The verification document must be 5 MB or smaller.');
    $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($_FILES['verification_document']['tmp_name']);
    if (!isset($allowed[$mime])) reply(false,'Upload a PDF, JPG or PNG document only.');
    if (!in_array($documentType,['national_id','trade_license','certificate','experience_proof','other'],true)) $documentType='other';
}
$check=$conn->prepare('SELECT id FROM users WHERE email=? OR phone=? LIMIT 1'); $check->bind_param('ss',$email,$phone); $check->execute();
if ($check->get_result()->num_rows) { $check->close(); reply(false,'This email or phone number is already registered.'); } $check->close();
$storedPath=null;
if ($role==='technician') {
    $dir=__DIR__.'/uploads/verification'; if (!is_dir($dir) && !mkdir($dir,0750,true)) reply(false,'Could not prepare secure document storage.',500);
    $filename=bin2hex(random_bytes(18)).'.'.$allowed[$mime];
    if (!move_uploaded_file($_FILES['verification_document']['tmp_name'],$dir.'/'.$filename)) reply(false,'Could not save the verification document.',500);
    $storedPath='uploads/verification/'.$filename;
}
try {
    $conn->begin_transaction();
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $stmt=$conn->prepare('INSERT INTO users (full_name,email,phone,password,role) VALUES (?,?,?,?,?)');
    $stmt->bind_param('sssss',$name,$email,$phone,$hash,$role); $stmt->execute(); $userId=(int)$conn->insert_id; $stmt->close();
    if ($role==='technician') {
        $profile=$conn->prepare("UPDATE technicians SET professional_title=?, experience_years=?, service_areas=?, verification_status='pending', available_status='unavailable' WHERE user_id=?");
        $profile->bind_param('sisi',$title,$experience,$areas,$userId); $profile->execute(); $profile->close();
        $techQ=$conn->prepare('SELECT id FROM technicians WHERE user_id=?'); $techQ->bind_param('i',$userId); $techQ->execute(); $techId=(int)$techQ->get_result()->fetch_assoc()['id']; $techQ->close();
        $doc=$conn->prepare("INSERT INTO verification_documents (technician_id,document_type,file_path,review_status) VALUES (?,?,?,'pending')");
        $doc->bind_param('iss',$techId,$documentType,$storedPath); $doc->execute(); $doc->close();
    }
    $conn->commit();
    $successMessage = $role==='technician'
        ? 'Technician account created. Your verification status is Pending. You can log in, but you cannot receive jobs until an admin approves your documents.'
        : ($role==='admin' ? 'Administrator account created successfully. Please sign in.' : 'Customer account created successfully. Please sign in.');
    reply(true,$successMessage);
} catch (Throwable $e) {
    $conn->rollback(); if ($storedPath && is_file(__DIR__.'/'.$storedPath)) @unlink(__DIR__.'/'.$storedPath);
    reply(false,'Could not create the account. Please check the database setup and try again.',500);
}
?>