<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include("dbconnection.php");

// Obtener parámetros
$gabinete_id = isset($_GET['gabinete_id']) ? (int)$_GET['gabinete_id'] : 0;
$interval = isset($_GET['interval']) ? $_GET['interval'] : '15min';

if ($gabinete_id == 0) {
    echo json_encode(['success' => false, 'error' => 'gabinete_id requerido']);
    exit();
}

// Configurar intervalos
$intervalConfig = [
    '1min'   => ['minutes' => 10,   'points' => 10],  // últimas 10 min, 10 puntos
    '5min'   => ['minutes' => 50,   'points' => 10],  // últimas 50 min, 10 puntos
    '15min'  => ['minutes' => 180,  'points' => 12],  // últimas 3 horas, 12 puntos
    '30min'  => ['minutes' => 360,  'points' => 12],  // últimas 6 horas, 12 puntos
    '1hour'  => ['minutes' => 720,  'points' => 12],  // últimas 12 horas, 12 puntos
    '3hours' => ['minutes' => 2160, 'points' => 12],  // últimos 36 horas, 12 puntos
];

$config = $intervalConfig[$interval] ?? $intervalConfig['15min'];

// Calcular el tamaño del bucket (agrupación de datos)
$bucketMinutes = $config['minutes'] / $config['points'];

// Consulta SQL con agrupación por tiempo
$sql = "SELECT 
    DATE_FORMAT(timestamp, '%H:%i') as hora,
    AVG(temperatura) as temperatura,
    AVG(humedad) as humedad,
    timestamp
FROM ambientlog 
WHERE gabinete_id = ? 
    AND timestamp >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
GROUP BY FLOOR(UNIX_TIMESTAMP(timestamp) / (" . ($bucketMinutes * 60) . "))
ORDER BY timestamp ASC
LIMIT ?";

$stmt = $con->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => $con->error, 'sql' => $sql]);
    exit();
}

$stmt->bind_param("iii", $gabinete_id, $config['minutes'], $config['points']);
$stmt->execute();
$result = $stmt->get_result();

$labels = [];
$temperatures = [];
$humidities = [];
$rawData = []; // Para debug: datos crudos

while ($row = $result->fetch_assoc()) {
    $labels[] = $row['hora'];
    $temperatures[] = round((float)$row['temperatura'], 1);
    $humidities[] = round((float)$row['humedad'], 1);
    $rawData[] = $row;
}

// Si no hay datos, generar datos de ejemplo (para testing)
if (empty($labels)) {
    for ($i = 0; $i < $config['points']; $i++) {
        $time = date('H:i', strtotime("-" . ($config['points'] - $i) * $bucketMinutes . " minutes"));
        $labels[] = $time;
        $temperatures[] = round(20 + (rand(0, 50) / 10), 1);
        $humidities[] = round(50 + (rand(0, 200) / 10), 1);
    }
}

echo json_encode([
    'success' => true,
    'labels' => $labels,
    'temperatures' => $temperatures,
    'humidities' => $humidities,
    'interval' => $interval,
    'gabinete_id' => $gabinete_id,
    'raw_data' => $rawData,   // Mostrar datos crudos para verificar
    'bucket_minutes' => $bucketMinutes
]);

$stmt->close();
$con->close();
?>
