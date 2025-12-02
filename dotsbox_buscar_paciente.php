<?php
session_start();
header('Content-Type: application/json');

// Verificar que sea usuario DotsBox
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'dotsbox') {
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
    exit();
}

include("dbconnection.php");

$huella_id = isset($_GET['huella_id']) ? intval($_GET['huella_id']) : 0;

if ($huella_id == 0) {
    echo json_encode(['success' => false, 'error' => 'ID de huella no proporcionado']);
    exit();
}

// Buscar paciente por huella
$sql = "SELECT 
    p.paciente_id,
    p.nombre,
    p.apellido,
    p.huella_id,
    p.gabinete_id,
    p.peso_actual,
    p.saturacion_actual,
    p.estado_salud,
    (SELECT COUNT(*) FROM consultas WHERE paciente_id = p.paciente_id AND es_dotsbox = 1) as dispensaciones,
    (SELECT COUNT(*) FROM recetas WHERE paciente_id = p.paciente_id AND tipo = 'Laboratorio' AND estado = 'Pendiente') as labs_pendientes
    FROM patient p
    WHERE p.huella_id = ?
    LIMIT 1";

$stmt = $con->prepare($sql);
$stmt->bind_param("i", $huella_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $paciente = $result->fetch_assoc();
    
    // Convertir a tipos correctos
    $paciente['paciente_id'] = (int)$paciente['paciente_id'];
    $paciente['huella_id'] = (int)$paciente['huella_id'];
    $paciente['gabinete_id'] = (int)$paciente['gabinete_id'];
    $paciente['peso_actual'] = $paciente['peso_actual'] ? (float)$paciente['peso_actual'] : null;
    $paciente['saturacion_actual'] = $paciente['saturacion_actual'] ? (int)$paciente['saturacion_actual'] : null;
    $paciente['dispensaciones'] = (int)$paciente['dispensaciones'];
    $paciente['labs_pendientes'] = (int)$paciente['labs_pendientes'];
    
    echo json_encode([
        'success' => true,
        'paciente' => $paciente,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Paciente no encontrado con la huella proporcionada',
        'huella_id' => $huella_id
    ]);
}

$stmt->close();
$con->close();
?>
