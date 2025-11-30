<?php
// CONFIGURACIÓN DE CONEXIÓN
$servername = "localhost";
$username = "root";
$password = "";
$database = "dots";

// CREAR CONEXIÓN
$conn = new mysqli($servername, $username, $password, $database);

// VERIFICAR CONEXIÓN
if ($conn->connect_error) {
    die("❌ Conexión fallida: " . $conn->connect_error);
}

// LEER DATOS DESDE POST
$temperatura = isset($_POST['temperatura']) ? floatval($_POST['temperatura']) : 0;  // Default value is 0
$humedad     = isset($_POST['humedad']) ? floatval($_POST['humedad']) : 0;        // Default value is 60
$idhuella    = isset($_POST['idhuella']) ? intval($_POST['idhuella']) : 0;          // Default value is 0
$ngabinete   = isset($_POST['ngabinete']) ? intval($_POST['ngabinete']) : 0;        // Default value is 0

// CONSULTA SQL
$sql = "INSERT INTO registro (temperatura, humedad, idhuella, ngabinete) 
        VALUES ($temperatura, $humedad, $idhuella, $ngabinete)";

if ($conn->query($sql) === TRUE) {
    echo "✅ Registro exitoso: Temp=$temperatura °C | Hum=$humedad % | ID=$idhuella | Gabinete=$ngabinete";
} else {
    echo "❌ Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>
