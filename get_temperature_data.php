<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['me_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include("dbconnection.php");

$gabinete_id = isset($_GET['gabinete_id']) ? (int)$_GET['gabinete_id'] : 0;
$interval = isset($_GET['interval']) ? $_GET['interval'] : '15min';

// ============================================
// CONFIGURAR INTERVALO DE TIEMPO
// ============================================
$intervalConfig = [
    '15min' => ['minutes' => 15, 'count' => 8, 'format' => 'H:i'],
    '30min' => ['minutes' => 30, 'count' => 8, 'format' => 'H:i'],
    '1hour' => ['minutes' => 60, 'count' => 12, 'format' => 'H:i'],
    '3hours' => ['minutes' => 180, 'count' => 12, 'format' => 'H:i'],
    '6hours' => ['minutes' => 360, 'count' => 24, 'format' => 'H:i']
];

$config = $intervalConfig[$interval] ?? $intervalConfig['15min'];

// ============================================
// CONSULTAR HISTORIAL DE TEMPERATURA
// ============================================
// Asume que tienes una tabla: historial_temperatura
// con campos: historial_id, gabinete_id, temperatura, fecha_hora
$sql = "SELECT temperatura, DATE_FORMAT(fecha_hora, '%H:%i') as hora 
        FROM historial_temperatura 
        WHERE gabinete_id = ? 
        AND fecha_hora >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ORDER BY fecha_hora ASC";

$stmt = $con->prepare($sql);
$totalMinutes = $config['minutes'] * $config['count'];
$stmt->bind_param("ii", $gabinete_id, $totalMinutes);
$stmt->execute();
$result = $stmt->get_result();

$labels = [];
$temperatures = [];

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['hora'];
    $temperatures[] = (float)$row['temperatura'];
}

// Si no hay datos, generar datos de ejemplo
if (empty($labels)) {
    for ($i = 0; $i < $config['count']; $i++) {
        $time = date($config['format'], strtotime("-" . ($config['count'] - $i) * $config['minutes'] . " minutes"));
        $labels[] = $time;
        $temperatures[] = rand(18, 26) + (rand(0, 99) / 100);
    }
}

echo json_encode([
    'success' => true,
    'labels' => $labels,
    'temperatures' => $temperatures,
    'interval' => $interval
]);
?>