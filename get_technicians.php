<?php
session_start(); header('Content-Type: application/json'); require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role']!=='customer') { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Please sign in with a customer account.']); exit; }
$category=strtolower(trim($_GET['category']??'')); $location=strtolower(trim($_GET['location']??''));
$groups=[
 'appliance'=>['appliance','home appliance','refrigerator','fridge','washing','air conditioner','ac repair','microwave'],
 'electronics'=>['electronics','electronic','laptop','mobile','phone','computer','printer','television','tv','tech'],
 'vehicle'=>['vehicle','car','motorcycle','bike','mechanic','automotive','mobile vehicle repair lab'],
 'other'=>['repair','maintenance','technician']
];
$terms=$groups[$category]??[];
$sql="SELECT t.id,t.professional_title,t.experience_years,t.service_areas,t.average_rating,t.total_reviews,u.full_name
      FROM technicians t JOIN users u ON u.id=t.user_id
      WHERE t.verification_status='approved' AND t.available_status='available' AND u.account_status='active'
      ORDER BY t.average_rating DESC,t.total_reviews DESC";
$res=$conn->query($sql); $items=[];
while($r=$res->fetch_assoc()) {
  $specialty=strtolower((string)$r['professional_title']); $area=strtolower((string)$r['service_areas']);
  $categoryMatch=!$terms; foreach($terms as $term){ if(str_contains($specialty,$term)){ $categoryMatch=true; break; } }
  // If the customer supplies a location, require at least one meaningful location token to match the technician's coverage.
  $tokens=preg_split('/[^a-z0-9]+/i',$location); $locationMatch=($location==='');
  foreach($tokens as $token){ if(strlen($token)>=3 && str_contains($area,$token)){ $locationMatch=true; break; } }
  if($categoryMatch && $locationMatch) $items[]=['id'=>(int)$r['id'],'name'=>$r['full_name'],'specialty'=>$r['professional_title'],'experience_years'=>(int)$r['experience_years'],'service_areas'=>$r['service_areas'],'rating'=>(float)$r['average_rating'],'reviews'=>(int)$r['total_reviews']];
}
echo json_encode(['success'=>true,'technicians'=>$items]);
?>