<?php
/**
 * Verificar si una huella ya está asignada a otro paciente
 */

header('Content-Type: application/json');
include("dbconnection.php");

$huella_id = isset($_GET['huella_id']) ? intval($_GET['huella_id']) : 0;

if ($huella_id == 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Huella ID no proporcionado'
    ]);
    exit();
}

// Buscar si la huella existe
$sql = "SELECT paciente_id, nombre, apellido FROM patient WHERE huella_id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $huella_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $paciente = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'existe' => true,
        'paciente' => $paciente['nombre'] . ' ' . $paciente['apellido'],
        'paciente_id' => $paciente['paciente_id']
    ]);
} else {
    echo json_encode([
        'success' => true,
        'existe' => false,
        'huella_id' => $huella_id
    ]);
}

$stmt->close();
$con->close();
?>
