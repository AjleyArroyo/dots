<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include("dbconnection.php");

$gabinete_id = isset($_GET['gabinete_id']) ? intval($_GET['gabinete_id']) : 0;

if ($gabinete_id == 0) {
    echo json_encode([
        'success' => false,
        'error' => 'gabinete_id requerido'
    ]);
    exit();
}

$sql = "SELECT uv_estado as relay FROM gabinete WHERE gabinete_id = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $gabinete_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'gabinete_id' => $gabinete_id,
        'relay' => (int)$row['relay']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Gabinete no encontrado'
    ]);
}

$stmt->close();
$con->close();
?>