<?php
include("dbconnection.php");

$nombre = "Ronny";
$apellido = "Rivera";
$correo = "ronny@dots.com";
$usuario = "oponix";
$contrasena_plana = "1234";
$contrasena_hash = password_hash($contrasena_plana, PASSWORD_DEFAULT);
$telefono = "77777777";

$sql = "INSERT INTO mepersonel (nombre, apellido, correo, usuario, contrasena, telefono)
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $con->prepare($sql);
$stmt->bind_param("ssssss", $nombre, $apellido, $correo, $usuario, $contrasena_hash, $telefono);

if ($stmt->execute()) {
    echo "✅ Usuario creado correctamente.<br>";
    echo "🔐 Contraseña usada: $contrasena_plana";
} else {
    echo "❌ Error al crear el usuario: " . $stmt->error;
}
?>
