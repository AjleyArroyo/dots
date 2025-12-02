<?php
/**
 * Archivo para leer datos temporales guardados por el ESP32
 * Usado cuando NO podemos consultar directamente al ESP32
 * URL: http://tu-servidor/dots/esp32_leer_datos.php?tipo=huella
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include("dbconnection.php");

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

if (empty($tipo)) {
    echo json_encode([
        'success' => false,
        'error' => 'Tipo no especificado. Valores válidos: huella, peso, saturacion'
    ]);
    exit();
}

// Leer el dato más reciente de ese tipo
$sql = "SELECT valor, timestamp 
        FROM esp32_temp_data 
        WHERE tipo = ? 
        AND timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY timestamp DESC 
        LIMIT 1";

$stmt = $con->prepare($sql);
$stmt->bind_param("s", $tipo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Formatear respuesta según el tipo
    $respuesta = [
        'success' => true,
        'tipo' => $tipo,
        'timestamp' => $row['timestamp']
    ];
    
    switch ($tipo) {
        case 'huella':
            $respuesta['huella_id'] = intval($row['valor']);
            break;
        case 'peso':
            $respuesta['peso'] = floatval($row['valor']);
            break;
        case 'saturacion':
            $respuesta['saturacion'] = intval($row['valor']);
            break;
        default:
            $respuesta['valor'] = $row['valor'];
    }
    
    echo json_encode($respuesta);
} else {
    echo json_encode([
        'success' => false,
        'error' => "No hay datos recientes de tipo '$tipo' (últimos 5 minutos)",
        'tipo' => $tipo
    ]);
}

$stmt->close();
$con->close();
?>