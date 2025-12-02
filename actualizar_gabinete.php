<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: login.php");
    exit();
}

include("dbconnection.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gabinete_id = isset($_POST['gabinete_id']) ? intval($_POST['gabinete_id']) : 0;
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    if ($gabinete_id == 0 || empty($accion)) {
        header("Location: dashboard.php?error=datos_invalidos");
        exit();
    }

    // Obtener estado actual
    $sql = "SELECT fan_estado, uv_estado FROM gabinete WHERE gabinete_id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $gabinete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $gabinete = $result->fetch_assoc();
    $stmt->close();

    if (!$gabinete) {
        header("Location: dashboard.php?error=gabinete_no_encontrado");
        exit();
    }

    // Toggle del estado
    if ($accion === 'fan') {
        $nuevo_estado = $gabinete['fan_estado'] == 1 ? 0 : 1;
        $sqlUpdate = "UPDATE gabinete SET fan_estado = ? WHERE gabinete_id = ?";
        $stmt = $con->prepare($sqlUpdate);
        $stmt->bind_param("ii", $nuevo_estado, $gabinete_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($accion === 'uv') {
        $nuevo_estado = $gabinete['uv_estado'] == 1 ? 0 : 1;
        $sqlUpdate = "UPDATE gabinete SET uv_estado = ? WHERE gabinete_id = ?";
        $stmt = $con->prepare($sqlUpdate);
        $stmt->bind_param("ii", $nuevo_estado, $gabinete_id);
        $stmt->execute();
        $stmt->close();
    }

    // Redirigir de vuelta al dashboard
    header("Location: dashboard.php");
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}
?>