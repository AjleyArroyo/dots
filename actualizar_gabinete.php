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
        
        // ✅ NUEVO: Notificar al ESP32 que actualice el relay
        notificarESP32Relay($gabinete_id);
    }

    // Redirigir de vuelta al dashboard
    header("Location: dashboard.php");
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}

/**
 * ✅ FUNCIÓN NUEVA: Notificar al ESP32 para que consulte el relay
 * 
 * Esta función hace una petición HTTP al ESP32 cuando se cambia el estado
 * del relay UV en el dashboard. El ESP32 entonces consulta api_get_relay.php
 * para obtener el nuevo estado y actualiza el GPIO correspondiente.
 * 
 * @param int $gabinete_id ID del gabinete cuyo relay cambió
 * @return bool True si la notificación fue exitosa, false en caso contrario
 */
function notificarESP32Relay($gabinete_id) {
    // ⚠️ IMPORTANTE: Ajustar esta IP según tu configuración
    $ESP32_IP = '192.168.0.24';
    $url = "http://{$ESP32_IP}/check_relay?gabinete_id={$gabinete_id}";
    
    // Usar cURL para hacer la petición al ESP32
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout de 5 segundos
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Timeout de conexión de 3 segundos
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log para debug (opcional, comentar en producción si no es necesario)
    $logMsg = date('Y-m-d H:i:s') . " - Notificación ESP32 - Gabinete {$gabinete_id} - HTTP {$httpCode}";
    if ($error) {
        $logMsg .= " - Error: {$error}";
    }
    error_log($logMsg);
    
    return $httpCode == 200;
}
?>