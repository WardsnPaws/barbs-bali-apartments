<?php
require_once __DIR__ . '/../../includes/core.php';
$pdo = getPDO();

// Get bookings
$bookings = $pdo->query("
    SELECT reservation_number, guest_first_name, guest_last_name, apartment_id, checkin_date, checkout_date
    FROM bookings
    WHERE status = 'confirmed'
")->fetchAll(PDO::FETCH_ASSOC);

// Prepare events for JavaScript
$events = [];
foreach ($bookings as $b) {
    $guest = $b['guest_first_name'] . ' ' . $b['guest_last_name'];
    $apt = ($b['apartment_id'] == 1) ? '6205' : (($b['apartment_id'] == 2) ? '6207' : 'Both');
    $events[] = [
        'title' => $guest . " – Apt " . $apt,
        'start' => $b['checkin_date'],
        'end' => $b['checkout_date'], // FullCalendar treats end as exclusive
        'color' => ($b['apartment_id'] == 1) ? '#2e8b57' : (($b['apartment_id'] == 2) ? '#4682b4' : '#8a2be2')
    ];
}
?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.9/index.global.min.js"></script>

<h2>📅 Booking Calendar</h2>
<div id="calendar"></div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      height: 'auto',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,listMonth'
      },
      events: <?= json_encode($events) ?>
    });
    calendar.render();
  });
</script>

<style>
  #calendar {
    max-width: 100%;
    margin-top: 20px;
  }
</style>
