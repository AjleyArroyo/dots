<?php
session_start();
header('Content-Type: application/json');

// Verificar que sea usuario DotsBox
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'dotsbox') {
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
    exit();
}

include("dbconnection.php");

// Obtener datos del POST
$paciente_id = isset($_POST['paciente_id']) ? intval($_POST['paciente_id']) : 0;
$peso = isset($_POST['peso']) ? floatval($_POST['peso']) : null;
$saturacion = isset($_POST['saturacion']) ? intval($_POST['saturacion']) : null;
$huella_id = isset($_POST['huella_id']) ? intval($_POST['huella_id']) : 0;

// Validar datos
if ($paciente_id == 0 || $peso === null || $saturacion === null) {
    echo json_encode([
        'success' => false, 
        'error' => 'Datos incompletos',
        'received' => [
            'paciente_id' => $paciente_id,
            'peso' => $peso,
            'saturacion' => $saturacion
        ]
    ]);
    exit();
}

// Obtener ID del usuario DotsBox
$me_id = $_SESSION['me_id'];

$con->begin_transaction();

try {
    // 1. Crear consulta automática
    $sqlConsulta = "INSERT INTO consultas 
        (paciente_id, me_personel_id, fecha_consulta, peso, saturacion, 
         notas, diagnostico, estado, es_dotsbox, dispensacion_exitosa) 
        VALUES (?, ?, NOW(), ?, ?, 'Consulta automática DotsBox', 
                'Mediciones registradas automáticamente', 'Realizada', 1, 1)";
    
    $stmt = $con->prepare($sqlConsulta);
    $stmt->bind_param("iidi", $paciente_id, $me_id, $peso, $saturacion);
    $stmt->execute();
    $consulta_id = $con->insert_id;
    $stmt->close();

    // 2. Guardar en historial de mediciones
    $sqlMedicion = "INSERT INTO historial_mediciones 
        (paciente_id, consulta_id, peso, saturacion, notas) 
        VALUES (?, ?, ?, ?, 'Dispensación automática DotsBox')";
    
    $stmt = $con->prepare($sqlMedicion);
    $stmt->bind_param("iidi", $paciente_id, $consulta_id, $peso, $saturacion);
    $stmt->execute();
    $medicion_id = $con->insert_id;
    $stmt->close();

    // 3. Actualizar peso y saturación actuales del paciente
    $sqlUpdate = "UPDATE patient 
        SET peso_actual = ?, saturacion_actual = ? 
        WHERE paciente_id = ?";
    
    $stmt = $con->prepare($sqlUpdate);
    $stmt->bind_param("dii", $peso, $saturacion, $paciente_id);
    $stmt->execute();
    $stmt->close();

    // 4. Calcular estado de salud (comparar con medición anterior)
    $sqlPrev = "SELECT peso, saturacion 
        FROM historial_mediciones 
        WHERE paciente_id = ? AND medicion_id < ? 
        ORDER BY fecha_medicion DESC 
        LIMIT 1";
    
    $stmt = $con->prepare($sqlPrev);
    $stmt->bind_param("ii", $paciente_id, $medicion_id);
    $stmt->execute();
    $resPrev = $stmt->get_result();
    
    if ($resPrev->num_rows > 0) {
        $prev = $resPrev->fetch_assoc();
        $peso_anterior = $prev['peso'];
        $sat_anterior = $prev['saturacion'];
        
        // Lógica de estado de salud
        $nuevo_estado = 'Estable';
        
        if ($peso > $peso_anterior && $saturacion >= $sat_anterior) {
            $nuevo_estado = 'Mejorando';
        } elseif ($peso < $peso_anterior && $saturacion < $sat_anterior) {
            $nuevo_estado = 'Empeorando';
        }
        
        $sqlEstado = "UPDATE patient SET estado_salud = ? WHERE paciente_id = ?";
        $stmtEstado = $con->prepare($sqlEstado);
        $stmtEstado->bind_param("si", $nuevo_estado, $paciente_id);
        $stmtEstado->execute();
        $stmtEstado->close();
    }
    $stmt->close();

    // 5. Registrar en log de dispensaciones
    $sqlLog = "INSERT INTO log_dispensaciones 
        (paciente_id, consulta_id, huella_id, peso_registrado, 
         saturacion_registrada, medicamento_dispensado, notas) 
        VALUES (?, ?, ?, ?, ?, 1, 'Dispensación exitosa')";
    
    $stmt = $con->prepare($sqlLog);
    $stmt->bind_param("iiidi", $paciente_id, $consulta_id, $huella_id, $peso, $saturacion);
    $stmt->execute();
    $dispensacion_id = $con->insert_id;
    $stmt->close();

    // 6. Obtener información del paciente para respuesta
    $sqlPaciente = "SELECT nombre, apellido, gabinete_id FROM patient WHERE paciente_id = ?";
    $stmt = $con->prepare($sqlPaciente);
    $stmt->bind_param("i", $paciente_id);
    $stmt->execute();
    $resPaciente = $stmt->get_result();
    $paciente = $resPaciente->fetch_assoc();
    $stmt->close();

    $con->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Consulta registrada y medicamento dispensado correctamente',
        'data' => [
            'consulta_id' => $consulta_id,
            'dispensacion_id' => $dispensacion_id,
            'paciente' => $paciente['nombre'] . ' ' . $paciente['apellido'],
            'peso' => $peso,
            'saturacion' => $saturacion,
            'gabinete' => $paciente['gabinete_id'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    $con->rollback();
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar: ' . $e->getMessage()
    ]);
}

$con->close();
?>
