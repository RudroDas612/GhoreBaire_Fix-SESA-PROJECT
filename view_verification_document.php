<?php
session_start();require_once 'db.php';
if(!isset($_SESSION['user_id'])||$_SESSION['role']!=='admin'){http_response_code(403);exit('Forbidden');}
$id=(int)($_GET['id']??0);$q=$conn->prepare('SELECT file_path,document_type FROM verification_documents WHERE id=?');$q->bind_param('i',$id);$q->execute();$r=$q->get_result()->fetch_assoc();$q->close();
if(!$r){http_response_code(404);exit('Document not found.');}$path=realpath(__DIR__.'/'.$r['file_path']);$root=realpath(__DIR__.'/uploads/verification');
if(!$path||!$root||strpos($path,$root.DIRECTORY_SEPARATOR)!==0||!is_file($path)){http_response_code(404);exit('Document unavailable.');}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true)){http_response_code(415);exit('Unsupported document type.');}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline; filename="verification-'.$id.'"');readfile($path);
?>