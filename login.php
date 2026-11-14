<?php
session_start(); header('Content-Type: application/json'); require_once 'db.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }
$identity=trim($_POST['email']??''); $password=$_POST['password']??'';
if ($identity==='' || $password==='') { echo json_encode(['success'=>false,'message'=>'Enter your email/phone and password.']); exit; }
$stmt=$conn->prepare('SELECT id,full_name,password,role,account_status FROM users WHERE email=? OR phone=? LIMIT 1');
$stmt->bind_param('ss',$identity,$identity); $stmt->execute(); $user=$stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$user || !password_verify($password,$user['password'])) { echo json_encode(['success'=>false,'message'=>'Incorrect email/phone or password.']); exit; }
if ($user['account_status']!=='active') { echo json_encode(['success'=>false,'message'=>'This account is inactive or blocked. Please contact support.']); exit; }
session_regenerate_id(true); $_SESSION['user_id']=(int)$user['id']; $_SESSION['full_name']=$user['full_name']; $_SESSION['role']=$user['role'];
$u=$conn->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?'); $u->bind_param('i',$user['id']); $u->execute(); $u->close();
echo json_encode(['success'=>true,'message'=>'Login successful.','role'=>$user['role']]);
?>