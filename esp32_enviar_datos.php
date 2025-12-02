<?php
/**
 * Archivo para recibir datos del ESP32
 * El ESP32 envía datos cada X segundos mediante POST
 * URL: http://tu-servidor/dots/esp32_enviar_datos.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include("dbconnection.php");

// Log de debug (opcional)
$log_file = __DIR__ . '/esp32_log.txt';
function escribir_log($mensaje) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $mensaje\n", FILE_APPEND);
}

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Recibir datos del ESP32
$metodo = $_SERVER['REQUEST_METHOD'];
escribir_log("Método: $metodo");

// Leer datos según el método
if ($metodo === 'POST') {
    $datos_raw = file_get_contents('php://input');
    escribir_log("Datos recibidos (raw): $datos_raw");
    
    $datos = json_decode($datos_raw, true);
    
    // Si no es JSON, intentar como POST normal
    if (!$datos) {
        $datos = $_POST;
    }
} else {
    $datos = $_GET;
}

escribir_log("Datos procesados: " . json_encode($datos));

// Validar que tengamos los datos mínimos
if (empty($datos)) {
    escribir_log("ERROR: No se recibieron datos");
    echo json_encode([
        'success' => false,
        'error' => 'No se recibieron datos',
        'metodo' => $metodo
    ]);
    exit();
}

// ============================================
// OPCIÓN 1: GUARDAR DATOS DE SENSORES (temperatura, humedad)
// ============================================
if (isset($datos['gabinete_id']) && isset($datos['temperatura']) && isset($datos['humedad'])) {
    $gabinete_id = intval($datos['gabinete_id']);
    $temperatura = floatval($datos['temperatura']);
    $humedad = floatval($datos['humedad']);
    
    escribir_log("Guardando sensores - Gabinete: $gabinete_id, Temp: $temperatura, Hum: $humedad");
    
    // Actualizar tabla gabinete
    $sqlUpdate = "UPDATE gabinete 
                  SET sensor_temp_id = ?, sensor_hum_id = ? 
                  WHERE gabinete_id = ?";
    $stmt = $con->prepare($sqlUpdate);
    $temp_str = strval($temperatura);
    $hum_str = strval($humedad);
    $stmt->bind_param("ssi", $temp_str, $hum_str, $gabinete_id);
    $stmt->execute();
    $stmt->close();
    
    // Guardar en log de ambiente
    $sqlLog = "INSERT INTO ambientlog (gabinete_id, temperatura, humedad) 
               VALUES (?, ?, ?)";
    $stmt = $con->prepare($sqlLog);
    $stmt->bind_param("idd", $gabinete_id, $temperatura, $humedad);
    $stmt->execute();
    $stmt->close();
    
    escribir_log("✅ Datos de sensores guardados correctamente");
    
    echo json_encode([
        'success' => true,
        'message' => 'Datos de sensores guardados',
        'gabinete_id' => $gabinete_id,
        'temperatura' => $temperatura,
        'humedad' => $humedad
    ]);
    exit();
}

// ============================================
// OPCIÓN 2: GUARDAR HUELLA LEÍDA (temporal)
// ============================================
if (isset($datos['huella_id'])) {
    $huella_id = intval($datos['huella_id']);
    
    escribir_log("Guardando huella temporal - ID: $huella_id");
    
    // Crear tabla temporal si no existe
    $sqlCreate = "CREATE TABLE IF NOT EXISTS esp32_temp_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        valor VARCHAR(255) NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tipo (tipo),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $con->query($sqlCreate);
    
    // Limpiar datos antiguos (más de 5 minutos)
    $con->query("DELETE FROM esp32_temp_data WHERE timestamp < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    
    // Guardar huella
    $sqlInsert = "INSERT INTO esp32_temp_data (tipo, valor) VALUES ('huella', ?)";
    $stmt = $con->prepare($sqlInsert);
    $stmt->bind_param("i", $huella_id);
    $stmt->execute();
    $stmt->close();
    
    escribir_log("✅ Huella guardada en tabla temporal");
    
    echo json_encode([
        'success' => true,
        'message' => 'Huella registrada',
        'huella_id' => $huella_id
    ]);
    exit();
}

// ============================================
// OPCIÓN 3: GUARDAR PESO LEÍDO (temporal)
// ============================================
if (isset($datos['peso'])) {
    $peso = floatval($datos['peso']);
    
    escribir_log("Guardando peso temporal - Peso: $peso");
    
    // Crear tabla si no existe
    $sqlCreate = "CREATE TABLE IF NOT EXISTS esp32_temp_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        valor VARCHAR(255) NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tipo (tipo),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $con->query($sqlCreate);
    
    // Limpiar datos antiguos
    $con->query("DELETE FROM esp32_temp_data WHERE timestamp < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    
    // Guardar peso
    $sqlInsert = "INSERT INTO esp32_temp_data (tipo, valor) VALUES ('peso', ?)";
    $stmt = $con->prepare($sqlInsert);
    $stmt->bind_param("d", $peso);
    $stmt->execute();
    $stmt->close();
    
    escribir_log("✅ Peso guardado en tabla temporal");
    
    echo json_encode([
        'success' => true,
        'message' => 'Peso registrado',
        'peso' => $peso
    ]);
    exit();
}

// ============================================
// OPCIÓN 4: GUARDAR SATURACIÓN LEÍDA (temporal)
// ============================================
if (isset($datos['saturacion'])) {
    $saturacion = intval($datos['saturacion']);
    
    escribir_log("Guardando saturación temporal - Saturación: $saturacion");
    
    // Crear tabla si no existe
    $sqlCreate = "CREATE TABLE IF NOT EXISTS esp32_temp_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        valor VARCHAR(255) NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tipo (tipo),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $con->query($sqlCreate);
    
    // Limpiar datos antiguos
    $con->query("DELETE FROM esp32_temp_data WHERE timestamp < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    
    // Guardar saturación
    $sqlInsert = "INSERT INTO esp32_temp_data (tipo, valor) VALUES ('saturacion', ?)";
    $stmt = $con->prepare($sqlInsert);
    $stmt->bind_param("i", $saturacion);
    $stmt->execute();
    $stmt->close();
    
    escribir_log("✅ Saturación guardada en tabla temporal");
    
    echo json_encode([
        'success' => true,
        'message' => 'Saturación registrada',
        'saturacion' => $saturacion
    ]);
    exit();
}

// Si llegamos aquí, los datos no coinciden con ningún formato esperado
escribir_log("⚠️ Datos recibidos pero no procesados");
echo json_encode([
    'success' => false,
    'error' => 'Formato de datos no reconocido',
    'datos_recibidos' => $datos
]);

$con->close();
?>