<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') { header('Location: login.html'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die('Invalid request.'); }
$userId = (int)$_SESSION['user_id'];
$bookingId = (int)($_POST['booking_id'] ?? 0);
$method = $_POST['payment_method'] ?? '';
$allowed = ['bkash','nagad','card','bank_transfer'];
if (!in_array($method, $allowed, true)) die('Invalid payment method.');
try {
    $conn->begin_transaction();
    $q = $conn->prepare('SELECT id,booking_code,status,final_price FROM bookings WHERE id=? AND user_id=? FOR UPDATE');
    $q->bind_param('ii', $bookingId, $userId); $q->execute(); $booking = $q->get_result()->fetch_assoc(); $q->close();
    if (!$booking || $booking['status'] !== 'Accepted' || (float)$booking['final_price'] <= 0) throw new Exception('This booking is not ready for payment.');
    $check = $conn->prepare("SELECT id FROM payments WHERE booking_id=? AND payment_status='success' LIMIT 1");
    $check->bind_param('i', $bookingId); $check->execute(); $existing = $check->get_result()->fetch_assoc(); $check->close();
    if ($existing) {
        $conn->commit();
        header('Location: voucher.php?payment_id='.(int)$existing['id']); exit;
    }
    $amount = (float)$booking['final_price'];
    $reference = 'GBF-PAY-'.date('YmdHis').'-'.bin2hex(random_bytes(3));
    $gatewayReference = 'SANDBOX-'.strtoupper(bin2hex(random_bytes(5)));
    $status = 'success';
    $gateway = 'GhorebaireFix Sandbox';
    $response = 'Simulated sandbox payment approved. No real transaction was made.';
    $insert = $conn->prepare('INSERT INTO payments (booking_id,payment_reference,gateway_reference,amount,currency,payment_method,payment_status,gateway_name,payment_time,gateway_response) VALUES (?,?,?, ?,\'BDT\',?, ?,?,NOW(),?)');
    $insert->bind_param('issdssss', $bookingId, $reference, $gatewayReference, $amount, $method, $status, $gateway, $response);
    $insert->execute();
    $paymentId = $insert->insert_id;
    $insert->close();
    $conn->commit();
    header('Location: voucher.php?payment_id='.$paymentId); exit;
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').' <a href="dashboard.php">Return to dashboard</a>');
}
?>
