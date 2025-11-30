<?php
// Activar reporte de errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

// Log de debug (comentar en producción)
file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Solicitud recibida\n", FILE_APPEND);

if (!isset($_SESSION['me_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado', 'session' => $_SESSION]);
    exit();
}

include("dbconnection.php");

// Verificar conexión
if (!$con) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

// ============================================
// OBTENER DATOS ACTUALES DE TODOS LOS GABINETES
// ============================================
$sql = "SELECT gabinete_id, nombre, sensor_temp_id as temperatura, 
        sensor_hum_id as humedad, fan_estado, uv_estado 
        FROM gabinete ORDER BY gabinete_id ASC LIMIT 6";

$result = $con->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Error en query: ' . $con->error]);
    exit();
}

$gabinetes = [];

while ($row = $result->fetch_assoc()) {
    $gabinetes[] = [
        'gabinete_id' => (int)$row['gabinete_id'],
        'nombre' => $row['nombre'],
        'temperatura' => (float)$row['temperatura'],
        'humedad' => (float)$row['humedad'],
        'fan_estado' => (int)$row['fan_estado'] == 1,
        'uv_estado' => (int)$row['uv_estado'] == 1
    ];
}

// Log de debug
file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Gabinetes encontrados: " . count($gabinetes) . "\n", FILE_APPEND);

echo json_encode([
    'success' => true,
    'gabinetes' => $gabinetes,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>