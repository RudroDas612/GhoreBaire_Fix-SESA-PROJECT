<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') { header('Location: login.html'); exit; }
$userId = (int)$_SESSION['user_id'];
$bookingId = (int)($_GET['booking_id'] ?? 0);
$q = $conn->prepare("SELECT b.*,u.full_name customer_name,COALESCE(tu.full_name,b.technician_name) technician_name FROM bookings b JOIN users u ON u.id=b.user_id LEFT JOIN technicians t ON t.id=b.technician_id LEFT JOIN users tu ON tu.id=t.user_id WHERE b.id=? AND b.user_id=?");
$q->bind_param('ii', $bookingId, $userId); $q->execute(); $booking = $q->get_result()->fetch_assoc(); $q->close();
if (!$booking) { http_response_code(404); die('Booking not found.'); }
if ($booking['status'] !== 'Accepted' || (float)$booking['final_price'] <= 0) { die('Payment is available only after the technician accepts the booking and sets a service charge. <a href="dashboard.php">Return to dashboard</a>'); }
$p = $conn->prepare("SELECT id FROM payments WHERE booking_id=? AND payment_status='success' LIMIT 1"); $p->bind_param('i', $bookingId); $p->execute(); $paid = $p->get_result()->fetch_assoc(); $p->close();
if ($paid) { header('Location: voucher.php?payment_id='.(int)$paid['id']); exit; }
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Payment - Ghorebaire Fix</title><link rel="stylesheet" href="style.css"><style>.pay-wrap{max-width:680px;margin:55px auto;padding:25px}.pay-card{background:#fff;padding:30px;border-radius:14px;box-shadow:0 5px 24px #0001}.pay-row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #eee}.pay-card select{width:100%;padding:12px;margin:8px 0 20px;border:1px solid #ddd;border-radius:8px}.pay-note{background:#fff8e6;padding:12px;border-radius:8px;margin:16px 0}.pay-card button{width:100%;border:0;cursor:pointer}</style></head><body>
<nav class="navbar"><div class="container"><a href="index.html" class="logo">Ghorebaire<span>Fix</span></a><a href="dashboard.php" class="nav-dashboard">Dashboard</a></div></nav>
<main class="pay-wrap"><div class="pay-card"><h1>Complete Your Payment</h1><p>Review your booking details before payment.</p>
<div class="pay-row"><span>Booking ID</span><strong><?php echo h($booking['booking_code'] ?: '#'.$booking['id']); ?></strong></div>
<div class="pay-row"><span>Service</span><strong><?php echo h($booking['category'].' / '.$booking['subcategory']); ?></strong></div>
<div class="pay-row"><span>Technician</span><strong><?php echo h($booking['technician_name']); ?></strong></div>
<div class="pay-row"><span>Booking status</span><strong><?php echo h($booking['status']); ?></strong></div>
<div class="pay-row"><span>Total amount</span><strong>৳ <?php echo number_format((float)$booking['final_price'], 2); ?></strong></div>
<div class="pay-note"><strong>Sandbox mode:</strong> This is a simulated payment for project demonstration. No real money will be transferred.</div>
<form method="post" action="process_payment.php"><input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>"><label for="payment_method">Choose payment method</label><select id="payment_method" name="payment_method" required><option value="bkash">bKash (Sandbox)</option><option value="nagad">Nagad (Sandbox)</option><option value="card">Card (Sandbox)</option><option value="bank_transfer">Bank Transfer (Sandbox)</option></select><button class="btn btn-primary" type="submit">Pay ৳ <?php echo number_format((float)$booking['final_price'], 2); ?></button></form>
<p style="margin-top:18px"><a href="dashboard.php">Cancel and return to dashboard</a></p></div></main></body></html>
