<?php
session_start();require_once 'db.php';
if(!isset($_SESSION['user_id'])||$_SESSION['role']!=='technician'){http_response_code(403);die('Access denied.');}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);die('Invalid request.');}
$status=$_POST['availability']??'';if(!in_array($status,['available','unavailable'],true))die('Invalid availability.');
$userId=(int)$_SESSION['user_id'];$q=$conn->prepare("SELECT id,verification_status FROM technicians WHERE user_id=?");$q->bind_param('i',$userId);$q->execute();$t=$q->get_result()->fetch_assoc();$q->close();
if(!$t||$t['verification_status']!=='approved'){die('Only approved technicians can change availability. <a href="dashboard.php">Return</a>');}
$busy=$conn->prepare("SELECT COUNT(*) c FROM bookings WHERE technician_id=? AND status IN ('Accepted','On the Way','In Progress')");$busy->bind_param('i',$t['id']);$busy->execute();$hasBusy=(int)$busy->get_result()->fetch_assoc()['c'];$busy->close();
if($hasBusy && $status==='available')die('You have an active job. Availability will return to Available after completion. <a href="dashboard.php">Return</a>');
$u=$conn->prepare('UPDATE technicians SET available_status=? WHERE id=?');$u->bind_param('si',$status,$t['id']);$u->execute();$u->close();header('Location: dashboard.php');exit;
?>