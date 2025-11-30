<?php
// api_guardar_dht.php
// Actualiza temperatura y humedad de un gabinete desde el ESP32

include("dbconnection.php");

// Opcional mientras depuras:
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// Solo aceptamos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'ok'    => false,
        'error' => 'Método no permitido. Usa POST.'
    ]);
    exit();
}

// Leer datos del ESP32
$gabinete_id = isset($_POST['gabinete_id']) ? intval($_POST['gabinete_id']) : 0;
$temperatura = isset($_POST['temp'])        ? floatval($_POST['temp'])        : null;
$humedad     = isset($_POST['hum'])         ? floatval($_POST['hum'])         : null;

// Validación básica
if ($gabinete_id <= 0 || $temperatura === null || $humedad === null) {
    echo json_encode([
        'ok'    => false,
        'error' => 'Datos incompletos. Envia gabinete_id, temp y hum.'
    ]);
    exit();
}

// Actualizar tabla gabinete
// Asegúrate de que los campos se llamen así: sensor_temp_id, sensor_hum_id
$sql = "UPDATE gabinete 
        SET sensor_temp_id = ?, sensor_hum_id = ?
        WHERE gabinete_id = ?";

$stmt = $con->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'ok'    => false,
        'error' => 'Error al preparar consulta: ' . $con->error
    ]);
    exit();
}

$stmt->bind_param("ddi", $temperatura, $humedad, $gabinete_id);

if ($stmt->execute()) {
    echo json_encode([
        'ok'          => true,
        'gabinete_id' => $gabinete_id,
        'temp'        => $temperatura,
        'hum'         => $humedad
    ]);
} else {
    echo json_encode([
        'ok'    => false,
        'error' => 'Error al actualizar gabinete: ' . $stmt->error
    ]);
}

$stmt->close();
