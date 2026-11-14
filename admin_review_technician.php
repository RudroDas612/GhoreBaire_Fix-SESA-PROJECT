<?php
session_start();require_once 'db.php';
if(!isset($_SESSION['user_id'])||$_SESSION['role']!=='admin'){http_response_code(403);die('Admin access only.');}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);die('Invalid request.');}
$techId=(int)($_POST['technician_id']??0);$decision=$_POST['decision']??'';$note=trim($_POST['admin_note']??'');
if($techId<1||!in_array($decision,['approved','rejected','resubmission_required'],true))die('Invalid review decision.');
$adminQ=$conn->prepare('SELECT id FROM admins WHERE user_id=?');$adminQ->bind_param('i',$_SESSION['user_id']);$adminQ->execute();$admin=$adminQ->get_result()->fetch_assoc();$adminQ->close();if(!$admin)die('Admin profile is missing.');
$conn->begin_transaction();try{
 $q=$conn->prepare('SELECT id FROM technicians WHERE id=? FOR UPDATE');$q->bind_param('i',$techId);$q->execute();if(!$q->get_result()->fetch_assoc())throw new Exception('Technician not found.');$q->close();
 $u=$conn->prepare("UPDATE technicians SET verification_status=?,available_status='unavailable' WHERE id=?");$u->bind_param('si',$decision,$techId);$u->execute();$u->close();
 $docStatus=$decision==='resubmission_required'?'resubmit':$decision;
 $d=$conn->prepare('UPDATE verification_documents SET review_status=?,admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE technician_id=? AND review_status IN (\'pending\',\'resubmit\')');$d->bind_param('ssii',$docStatus,$note,$admin['id'],$techId);$d->execute();$d->close();
 $userQ=$conn->prepare('SELECT user_id FROM technicians WHERE id=?');$userQ->bind_param('i',$techId);$userQ->execute();$techUser=(int)$userQ->get_result()->fetch_assoc()['user_id'];$userQ->close();
 $title='Technician verification update';$message='Your technician verification status is now '.str_replace('_',' ',$decision).'.'.($note?' Admin note: '.$note:'');$type='technician_verification';$n=$conn->prepare('INSERT INTO notifications (user_id,notification_type,title,message) VALUES (?,?,?,?)');$n->bind_param('isss',$techUser,$type,$title,$message);$n->execute();$n->close();
 $conn->commit();header('Location: dashboard.php?reviewed=1');exit;
}catch(Throwable $e){$conn->rollback();http_response_code(400);die('Could not save review: '.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
?>