<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['professional_id']) || !isset($_GET['service_id']) || !isset($_GET['date'])) {
    echo json_encode([]);
    exit;
}

$professional_id = $_GET['professional_id'];
$service_id = $_GET['service_id'];
$date = $_GET['date'];

try {
    // Get service duration
    $stmt = $pdo->prepare("SELECT duration FROM services WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    
    if (!$service) {
        echo json_encode([]);
        exit;
    }
    
    $duration = $service['duration'];
    
    // Get existing appointments for the day
    $stmt = $pdo->prepare("SELECT start_time, end_time FROM appointments 
                          WHERE professional_id = ? AND appointment_date = ? 
                          AND status NOT IN ('cancelled')");
    $stmt->execute([$professional_id, $date]);
    $existing_appointments = $stmt->fetchAll();
    
    // Generate available time slots (9 AM to 6 PM, 30-minute intervals)
    $available_times = [];
    $start_hour = 9;
    $end_hour = 18;
    
    for ($hour = $start_hour; $hour < $end_hour; $hour++) {
        for ($minute = 0; $minute < 60; $minute += 30) {
            $time_slot = sprintf('%02d:%02d:00', $hour, $minute);
            $end_time = date('H:i:s', strtotime($time_slot) + ($duration * 60));
            
            // Check if this time slot conflicts with existing appointments
            $is_available = true;
            foreach ($existing_appointments as $appointment) {
                $app_start = $appointment['start_time'];
                $app_end = $appointment['end_time'];
                
                // Check for overlap
                if (($time_slot >= $app_start && $time_slot < $app_end) ||
                    ($end_time > $app_start && $end_time <= $app_end) ||
                    ($time_slot <= $app_start && $end_time >= $app_end)) {
                    $is_available = false;
                    break;
                }
            }
            
            if ($is_available && strtotime($end_time) <= strtotime('18:00:00')) {
                $display_time = date('g:i A', strtotime($time_slot));
                $available_times[] = [
                    'time' => $time_slot,
                    'display' => $display_time
                ];
            }
        }
    }
    
    echo json_encode($available_times);
} catch (Exception $e) {
    echo json_encode([]);
}
?>

