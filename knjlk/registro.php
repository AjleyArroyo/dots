<?php 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $idhuella = $_POST['idhuella'] ?? '';

    if ($nombre == '' || $apellido == '' || $idhuella == '') {
        echo "❌ Faltan datos.";
        exit;
    }

    $conn = new mysqli("localhost", "root", "", "dots"); // <- BASE DE DATOS CORREGIDA

    if ($conn->connect_error) {
        die("❌ Error de conexión: " . $conn->connect_error);
    }

    $sql = "INSERT INTO usuarios (nombre, apellido, idhuella) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $nombre, $apellido, $idhuella);

    if ($stmt->execute()) {
        echo "✅ Usuario registrado correctamente.";
    } else {
        echo "❌ Error al registrar: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
