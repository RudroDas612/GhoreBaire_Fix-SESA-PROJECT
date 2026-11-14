<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    http_response_code(403);
    die('Access denied. Sign in with a technician account.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Invalid request.');
}

$userId = (int)$_SESSION['user_id'];
$bookingId = (int)($_POST['booking_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$next = trim($_POST['status'] ?? '');
$price = (float)($_POST['final_price'] ?? 0);

$tq = $conn->prepare('SELECT id FROM technicians WHERE user_id=?');
$tq->bind_param('i', $userId);
$tq->execute();
$tr = $tq->get_result()->fetch_assoc();
$tq->close();
if (!$tr) die('Technician profile not found.');
$techId = (int)$tr['id'];
if ($bookingId < 1) die('Invalid booking.');

try {
    $conn->begin_transaction();
    $q = $conn->prepare('SELECT id,user_id,booking_code,status,final_price FROM bookings WHERE id=? AND technician_id=? FOR UPDATE');
    $q->bind_param('ii', $bookingId, $techId);
    $q->execute();
    $b = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$b) throw new Exception('This job is not assigned to your account.');

    $old = $b['status'];
    if ($action === 'accept' && $old === 'Requested') {
        if ($price <= 0 || $price > 1000000) throw new Exception('Enter a valid service charge greater than zero.');
        $profile = $conn->prepare('SELECT verification_status,available_status FROM technicians WHERE id=? FOR UPDATE');
        $profile->bind_param('i', $techId);
        $profile->execute();
        $profileRow = $profile->get_result()->fetch_assoc();
        $profile->close();
        if (!$profileRow || $profileRow['verification_status'] !== 'approved') throw new Exception('Your technician account is not approved.');
        if ($profileRow['available_status'] !== 'available') throw new Exception('Set your availability to Available before accepting a job.');
        $active = $conn->prepare("SELECT COUNT(*) AS total FROM bookings WHERE technician_id=? AND id<>? AND status IN ('Accepted','On the Way','In Progress')");
        $active->bind_param('ii', $techId, $bookingId);
        $active->execute();
        $activeCount = (int)$active->get_result()->fetch_assoc()['total'];
        $active->close();
        if ($activeCount > 0) throw new Exception('You already have an active job. Complete it before accepting another.');
        $next = 'Accepted';
    } elseif ($action === 'reject' && $old === 'Requested') {
        $next = 'Rejected';
    } elseif ($action === 'progress' && in_array($old, ['Accepted','On the Way','In Progress'], true) && in_array($next, ['On the Way','In Progress','Completed'], true)) {
        $valid = ['Accepted'=>['On the Way'], 'On the Way'=>['In Progress'], 'In Progress'=>['Completed']];
        if (!in_array($next, $valid[$old] ?? [], true)) throw new Exception('Invalid status transition.');
    } else {
        throw new Exception('This action is not allowed for the current booking status.');
    }

    if ($action === 'accept') {
        $up = $conn->prepare("UPDATE bookings SET status='Accepted',final_price=?,accepted_at=NOW() WHERE id=? AND technician_id=?");
        $up->bind_param('dii', $price, $bookingId, $techId);
    } else {
        $up = $conn->prepare('UPDATE bookings SET status=?,started_at=IF(?=\'In Progress\',NOW(),started_at),completed_at=IF(?=\'Completed\',NOW(),completed_at) WHERE id=? AND technician_id=?');
        $up->bind_param('sssii', $next, $next, $next, $bookingId, $techId);
    }
    $up->execute();
    $up->close();

    if ($next === 'Accepted') {
        $av = 'busy';
        $a = $conn->prepare('UPDATE technicians SET available_status=? WHERE id=?');
        $a->bind_param('si', $av, $techId); $a->execute(); $a->close();
    }
    if (in_array($next, ['Rejected','Completed'], true)) {
        $av = 'available';
        $a = $conn->prepare('UPDATE technicians SET available_status=? WHERE id=?');
        $a->bind_param('si', $av, $techId); $a->execute(); $a->close();
    }
    if ($next === 'Completed') {
        $a = $conn->prepare('UPDATE technicians SET completed_jobs=completed_jobs+1 WHERE id=?');
        $a->bind_param('i', $techId); $a->execute(); $a->close();
    }

    $title = 'Booking status updated';
    $message = 'Your booking '.($b['booking_code'] ?: ('#'.$bookingId)).' is now '.$next.'.';
    if ($next === 'Accepted') $message .= ' The technician set the service charge to BDT '.number_format($price, 2).'. Please complete payment from your dashboard.';
    $type = 'booking_status';
    $n = $conn->prepare('INSERT INTO notifications (user_id,notification_type,title,message,related_booking_id) VALUES (?,?,?,?,?)');
    $n->bind_param('isssi', $b['user_id'], $type, $title, $message, $bookingId);
    $n->execute(); $n->close();

    $conn->commit();
    header('Location: dashboard.php?updated=1');
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').' <a href="dashboard.php">Return to dashboard</a>');
}
?>
