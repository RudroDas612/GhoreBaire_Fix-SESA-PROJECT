<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); die('Administrator access only.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die('Invalid request.'); }
$type = $_POST['type'] ?? '';
if ($type === 'user') {
    $userId = (int)($_POST['user_id'] ?? 0); $status = $_POST['account_status'] ?? '';
    if ($userId < 1 || $userId === (int)$_SESSION['user_id'] || !in_array($status, ['active','inactive','blocked'], true)) die('Invalid user update.');
    $stmt = $conn->prepare('UPDATE users SET account_status=? WHERE id=?');
    $stmt->bind_param('si', $status, $userId); $stmt->execute(); $stmt->close();
} elseif ($type === 'booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0); $status = $_POST['booking_status'] ?? '';
    $allowed = ['Pending','Requested','Accepted','On the Way','In Progress','Completed','Rejected','Cancelled'];
    if ($bookingId < 1 || !in_array($status, $allowed, true)) die('Invalid booking update.');
    $stmt = $conn->prepare('UPDATE bookings SET status=? WHERE id=?');
    $stmt->bind_param('si', $status, $bookingId); $stmt->execute(); $stmt->close();
} else { die('Invalid management action.'); }
header('Location: dashboard.php?managed=1'); exit;
?>
