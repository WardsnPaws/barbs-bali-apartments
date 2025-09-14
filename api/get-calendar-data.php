<?php
header('Content-Type: application/json');
require_once __DIR__ . '/core.php';

try {
    $pdo = getPDO();

    // Get confirmed bookings
    $stmt = $pdo->query("
    SELECT reservation_number, guest_first_name, guest_last_name, apartment_id, checkin_date, checkout_date
    FROM bookings
    WHERE status = 'confirmed'
    ORDER BY checkin_date
    ");

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare events for FullCalendar
    $events = [];
    foreach ($bookings as $booking) {
        $apt = ($booking['apartment_id'] == 1) ? '6205' : (($booking['apartment_id'] == 2) ? '6207' : 'Both');

        // Determine color based on apartment
        $color = '#2e8b57'; // Default green for apartment 1
        if ($booking['apartment_id'] == 2) {
            $color = '#4682b4'; // Blue for apartment 2
        } elseif ($booking['apartment_id'] == 3) {
            $color = '#8a2be2'; // Purple for both apartments
        }

        $events[] = [
            'title' => ($apt == 'Both') ? 'Both Apartments' : "Apartment " . $apt,
            'start' => $booking['checkin_date'],
            'end' => $booking['checkout_date'], // FullCalendar treats end as exclusive
            'color' => $color,
            'apartment_id' => $booking['apartment_id'],
            'reservation_number' => $booking['reservation_number']
        ];
    }

    echo json_encode([
        'success' => true,
        'events' => $events
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading calendar data: ' . $e->getMessage(),
        'events' => []
    ]);
}
?>