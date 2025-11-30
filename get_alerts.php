<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['me_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

include("dbconnection.php");

// ============================================
// CONSULTAR ALERTAS RECIENTES
// ============================================
// Asume tabla: alertas_notificaciones
// con campos: alerta_id, fecha_hora, mensaje, tipo (info/warning/danger/success), leida
$sql = "SELECT alerta_id, 
        DATE_FORMAT(fecha_hora, '%d %b, %H:%i') as fecha_hora,
        mensaje, tipo, leida 
        FROM alertas_notificaciones 
        ORDER BY fecha_hora DESC 
        LIMIT 20";

$result = $con->query($sql);
$alerts = [];

while ($row = $result->fetch_assoc()) {
    $alerts[] = [
        'id' => (int)$row['alerta_id'],
        'fecha_hora' => $row['fecha_hora'],
        'mensaje' => $row['mensaje'],
        'tipo' => $row['tipo'],
        'leida' => (bool)$row['leida']
    ];
}

// Contar alertas no leídas
$sqlCount = "SELECT COUNT(*) as count FROM alertas_notificaciones WHERE leida = 0";
$resultCount = $con->query($sqlCount);
$countRow = $resultCount->fetch_assoc();

echo json_encode([
    'success' => true,
    'alerts' => $alerts,
    'count' => (int)$countRow['count']
]);
?>