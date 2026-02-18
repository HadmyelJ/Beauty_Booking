<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['professional_id']) || empty($_GET['professional_id'])) {
    echo json_encode([]);
    exit;
}

$professional_id = $_GET['professional_id'];

try {
    $stmt = $pdo->prepare("SELECT service_id, name, description, duration, price FROM services WHERE professional_id = ? ORDER BY name");
    $stmt->execute([$professional_id]);
    $services = $stmt->fetchAll();
    
    echo json_encode($services);
} catch (Exception $e) {
    echo json_encode([]);
}
?>

