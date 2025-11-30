<?php
include("dbconnection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['gabinete_id'];
    $accion = $_POST['accion'];

    // Cargar el estado actual
    $sql = "SELECT * FROM gabinete WHERE gabinete_id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    if ($accion === 'uv') {
        $nuevo_estado = $row['uv_estado'] ? 0 : 1;
        $update = "UPDATE gabinete SET uv_estado = ? WHERE gabinete_id = ?";
    } elseif ($accion === 'fan') {
        $nuevo_estado = $row['fan_estado'] ? 0 : 1;
        $update = "UPDATE gabinete SET fan_estado = ? WHERE gabinete_id = ?";
    } else {
        exit("Acción no válida.");
    }

    $stmt = $con->prepare($update);
    $stmt->bind_param("ii", $nuevo_estado, $id);
    $stmt->execute();

    header("Location: dashboard.php");
    exit();
}
