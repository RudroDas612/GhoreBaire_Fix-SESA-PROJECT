<?php
session_start(); header('Content-Type: application/json'); require_once 'db.php';
function out($ok,$message,$code=200){http_response_code($code);echo json_encode(['success'=>$ok,'message'=>$message]);exit;}
if(!isset($_SESSION['user_id'])||$_SESSION['role']!=='customer') out(false,'Please sign in with a customer account before booking.',401);
if($_SERVER['REQUEST_METHOD']!=='POST') out(false,'Invalid request.',405);
$userId=(int)$_SESSION['user_id']; $category=strtolower(trim($_POST['category']??'')); $subcategory=trim($_POST['subcategory']??''); $description=trim($_POST['description']??''); $location=trim($_POST['location']??''); $technicianId=(int)($_POST['technician_id']??0); $date=trim($_POST['booking_date']??''); $time=trim($_POST['booking_time']??'');
$missing=[];
if(!$technicianId) $missing[]='selected technician';
if(!$category) $missing[]='service category';
if(!$description) $missing[]='issue description';
if(!$location) $missing[]='service location';
if(!$date) $missing[]='preferred date';
if(!$time) $missing[]='preferred time';
if($missing) out(false,'Booking details missing from the request: '.implode(', ',$missing).'. Please go back, check the fields, and submit again.',422);
$dt=DateTime::createFromFormat('Y-m-d',$date); $tm=DateTime::createFromFormat('H:i',$time);
if(!$dt||$dt->format('Y-m-d')!==$date||!$tm) out(false,'Please choose a valid date and time.');
$categoryNames=['appliance'=>'Appliances','electronics'=>'Electronics','vehicle'=>'Vehicles','other'=>'Other'];
if(!isset($categoryNames[$category])) out(false,'Invalid service category.'); $categoryName=$categoryNames[$category];
try{
 $conn->begin_transaction();
 $tech=$conn->prepare("SELECT t.id,u.full_name,t.professional_title,t.service_areas FROM technicians t JOIN users u ON u.id=t.user_id WHERE t.id=? AND t.verification_status='approved' AND t.available_status='available' AND u.account_status='active' FOR UPDATE");
 $tech->bind_param('i',$technicianId);$tech->execute();$technician=$tech->get_result()->fetch_assoc();$tech->close();
 if(!$technician) throw new Exception('This technician is no longer available. Please choose another technician.');
 $specialty=strtolower((string)$technician['professional_title']);$areaText=strtolower((string)$technician['service_areas']);
 $categoryTerms=['appliance'=>['appliance','refrigerator','fridge','washing','air conditioner','ac repair','microwave'],'electronics'=>['electronics','electronic','laptop','mobile','phone','computer','printer','television','tv','tech'],'vehicle'=>['vehicle','car','motorcycle','bike','mechanic','automotive'],'other'=>['repair','maintenance','technician']];
 $categoryMatch=false;foreach($categoryTerms[$category] as $term){if(str_contains($specialty,$term)){$categoryMatch=true;break;}}
 $locationMatch=false;foreach(preg_split('/[^a-z0-9]+/i',strtolower($location)) as $token){if(strlen($token)>=3 && str_contains($areaText,$token)){$locationMatch=true;break;}}
 if(!$categoryMatch || !$locationMatch) throw new Exception('The selected technician does not match this service category or location. Please select a matching technician.');
 $catId=null;$cq=$conn->prepare('SELECT id FROM service_categories WHERE category_name=? LIMIT 1');$cq->bind_param('s',$categoryName);$cq->execute();$cr=$cq->get_result()->fetch_assoc();if($cr)$catId=(int)$cr['id'];$cq->close();
 $title=$subcategory!==''?$subcategory:$categoryName.' Service';$reqStatus='booked';
 $rq=$conn->prepare('INSERT INTO service_requests (customer_id,category_id,category_name,subcategory_name,title,description,address_line,preferred_date,preferred_time,request_status) VALUES (?,?,?,?,?,?,?,?,?,?)');
 $rq->bind_param('iissssssss',$userId,$catId,$categoryName,$subcategory,$title,$description,$location,$date,$time,$reqStatus);$rq->execute();$requestId=(int)$conn->insert_id;$rq->close();
 $status='Requested';$techName=$technician['full_name'];
 $b=$conn->prepare('INSERT INTO bookings (request_id,user_id,customer_id,technician_id,category,subcategory,description,location,technician_name,booking_date,booking_time,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
 $b->bind_param('iiiissssssss',$requestId,$userId,$userId,$technicianId,$categoryName,$subcategory,$description,$location,$techName,$date,$time,$status);$b->execute();$bookingId=(int)$conn->insert_id;$b->close();
 $code='GBF-'.str_pad((string)$bookingId,6,'0',STR_PAD_LEFT);$u=$conn->prepare('UPDATE bookings SET booking_code=? WHERE id=?');$u->bind_param('si',$code,$bookingId);$u->execute();$u->close();
 $noticeTitle='New job request';$noticeMessage='You have received booking '.$code.'. Please review and accept or reject it.';$type='new_job_request';
 $n=$conn->prepare('INSERT INTO notifications (user_id,notification_type,title,message,related_booking_id) VALUES ((SELECT user_id FROM technicians WHERE id=?),?,?,?,?)');$n->bind_param('isssi',$technicianId,$type,$noticeTitle,$noticeMessage,$bookingId);$n->execute();$n->close();
 $conn->commit();out(true,'Your booking request '.$code.' has been sent to '.$techName.'. Track its status from My Bookings.');
}catch(Throwable $e){$conn->rollback();out(false,in_array($e->getMessage(),['This technician is no longer available. Please choose another technician.','The selected technician does not match this service category or location. Please select a matching technician.'],true)?$e->getMessage():'Could not save your booking. Please check the database setup and try again.',400);}
?>