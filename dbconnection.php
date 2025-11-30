<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "dots";  // Nombre actualizado de la base de datos

$con = new mysqli($host, $user, $pass, $db);
if ($con->connect_error) {
    die("Conexión fallida: " . $con->connect_error);
}
?>
