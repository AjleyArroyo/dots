<?php
// CONEXIÓN
$conexion = new mysqli("localhost", "root", "", "dots");

if ($conexion->connect_error) {
    die("❌ Error de conexión: " . $conexion->connect_error);
}

// LEER SPO2 DESDE POST
$spo2 = isset($_POST['spo2']) ? floatval($_POST['spo2']) : 0;

// VALIDACIÓN
if ($spo2 <= 0 || $spo2 > 85) {
    echo "❌ Valor SpO₂ inválido.";
    exit;
}

// GUARDAR EN LA BASE DE DATOS
$sql = "INSERT INTO spo2 (valorspo2) VALUES (?)";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("d", $spo2);

if ($stmt->execute()) {
    echo "✅ SpO₂ registrado correctamente: $spo2%";
} else {
    echo "❌ Error al guardar: " . $stmt->error;
}

$stmt->close();
$conexion->close();
?>
