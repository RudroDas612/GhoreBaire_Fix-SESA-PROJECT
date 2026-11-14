<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(403);
    exit('Only customers can submit reviews. <a href="login.html">Sign in</a>');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request.');
}

$customerId = (int)$_SESSION['user_id'];
$bookingId = (int)($_POST['booking_id'] ?? 0);
$stars = (int)($_POST['stars'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($bookingId < 1 || $stars < 1 || $stars > 5 || strlen($comment) > 1000) {
    exit('Please provide a valid star rating and a comment under 1000 characters. <a href="dashboard.php">Return</a>');
}

try {
    $conn->begin_transaction();

    $q = $conn->prepare("SELECT id, technician_id, status FROM bookings WHERE id=? AND user_id=? FOR UPDATE");
    $q->bind_param('ii', $bookingId, $customerId);
    $q->execute();
    $booking = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$booking || $booking['status'] !== 'Completed' || empty($booking['technician_id'])) {
        throw new Exception('You can review a technician only after the assigned service is completed.');
    }

    $check = $conn->prepare('SELECT id FROM ratings_reviews WHERE booking_id=? LIMIT 1');
    $check->bind_param('i', $bookingId);
    $check->execute();
    $alreadyReviewed = $check->get_result()->fetch_assoc();
    $check->close();

    if ($alreadyReviewed) {
        throw new Exception('You have already submitted a review for this booking.');
    }

    $technicianId = (int)$booking['technician_id'];
    $insert = $conn->prepare("INSERT INTO ratings_reviews (booking_id,customer_id,technician_id,stars,comment,review_status) VALUES (?,?,?,?,?,'visible')");
    $insert->bind_param('iiiis', $bookingId, $customerId, $technicianId, $stars, $comment);
    $insert->execute();
    $insert->close();

    $update = $conn->prepare("UPDATE technicians SET average_rating=(SELECT AVG(stars) FROM ratings_reviews WHERE technician_id=? AND review_status='visible'), total_reviews=(SELECT COUNT(*) FROM ratings_reviews WHERE technician_id=? AND review_status='visible') WHERE id=?");
    $update->bind_param('iii', $technicianId, $technicianId, $technicianId);
    $update->execute();
    $update->close();

    $conn->commit();
    header('Location: dashboard.php?reviewed=1');
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(400);
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . ' <a href="dashboard.php">Return to dashboard</a>';
}
?>
