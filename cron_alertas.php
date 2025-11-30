<?php
/**
 * =====================================================
 * CRON JOB - SISTEMA DE ALERTAS AUTOMÁTICAS
 * =====================================================
 * Este archivo debe ejecutarse cada minuto mediante cron job
 * 
 * CONFIGURACIÓN DEL CRON (en tu servidor):
 * * * * * * /usr/bin/php /ruta/completa/cron_alertas.php
 * 
 * O cada 5 minutos:
 * */5 * * * * /usr/bin/php /ruta/completa/cron_alertas.php
 * =====================================================
 */

// Evitar ejecución directa desde navegador (opcional pero recomendado)
if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde línea de comandos.");
}

include("dbconnection.php");

// Log de ejecución
$log_file = __DIR__ . '/cron_alertas.log';
function escribir_log($mensaje) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $mensaje\n", FILE_APPEND);
}

escribir_log("========== INICIO DE EJECUCIÓN ==========");

// =====================================================
// CONFIGURACIÓN DE FIREBASE (Cloud Messaging)
// =====================================================
// EDITA AQUÍ con tu Server Key de Firebase
$FIREBASE_SERVER_KEY = 'TU_SERVER_KEY_DE_FIREBASE_AQUI';
$FIREBASE_URL = 'https://fcm.googleapis.com/fcm/send';

/**
 * Función para enviar notificación push via Firebase
 */
function enviarNotificacionFirebase($token, $titulo, $mensaje, $data = []) {
    global $FIREBASE_SERVER_KEY, $FIREBASE_URL;
    
    if (empty($token) || $FIREBASE_SERVER_KEY === 'TU_SERVER_KEY_DE_FIREBASE_AQUI') {
        escribir_log("⚠️ Firebase no configurado o token vacío");
        return false;
    }
    
    $notification = [
        'title' => $titulo,
        'body' => $mensaje,
        'sound' => 'default',
        'badge' => 1,
        'icon' => 'notification_icon',
        'priority' => 'high'
    ];
    
    $payload = [
        'to' => $token,
        'notification' => $notification,
        'data' => $data,
        'priority' => 'high'
    ];
    
    $headers = [
        'Authorization: key=' . $FIREBASE_SERVER_KEY,
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $FIREBASE_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    escribir_log("Firebase response code: $httpcode");
    return $httpcode === 200;
}

// =====================================================
// 1. PROCESAR ALERTAS PROGRAMADAS
// =====================================================
escribir_log("📬 Procesando alertas programadas...");

$sqlAlertas = "SELECT a.*, p.nombre, p.apellido, p.correo 
               FROM alertas_paciente a 
               JOIN patient p ON a.paciente_id = p.paciente_id 
               WHERE a.estado = 'Pendiente' 
               AND a.fecha_programada <= NOW() 
               LIMIT 100";

$resAlertas = $con->query($sqlAlertas);
$alertas_enviadas = 0;

while ($alerta = $resAlertas->fetch_assoc()) {
    $enviado = false;
    
    // Enviar notificación push si tiene token
    if (!empty($alerta['firebase_token'])) {
        $enviado = enviarNotificacionFirebase(
            $alerta['firebase_token'],
            $alerta['titulo'],
            $alerta['mensaje'],
            [
                'tipo' => $alerta['tipo'],
                'paciente_id' => $alerta['paciente_id'],
                'alerta_id' => $alerta['alerta_id']
            ]
        );
    }
    
    // Actualizar estado de la alerta
    $nuevo_estado = $enviado ? 'Enviada' : 'Enviada'; // Marcamos como enviada aunque falle Firebase
    $sqlUpdate = "UPDATE alertas_paciente 
                  SET estado = '$nuevo_estado', fecha_enviada = NOW() 
                  WHERE alerta_id = " . $alerta['alerta_id'];
    $con->query($sqlUpdate);
    
    $alertas_enviadas++;
    escribir_log("✅ Alerta #{$alerta['alerta_id']} enviada a: {$alerta['nombre']} {$alerta['apellido']}");
}

escribir_log("📊 Total alertas enviadas: $alertas_enviadas");

// =====================================================
// 2. DETECTAR CONSULTAS NO ASISTIDAS
// =====================================================
escribir_log("🔍 Detectando consultas no asistidas...");

// CONFIGURACIÓN: Obtener tiempo de espera desde config_alertas
// EDITA en la tabla config_alertas el valor 'tiempo_no_asistencia'
$sqlConfig = "SELECT valor FROM config_alertas WHERE nombre = 'tiempo_no_asistencia' AND activo = 1";
$resConfig = $con->query($sqlConfig);
$minutos_no_asistencia = 1440; // Default: 1 día

if ($resConfig && $resConfig->num_rows > 0) {
    $config = $resConfig->fetch_assoc();
    $minutos_no_asistencia = $config['valor'];
}

$sqlConsultas = "SELECT c.*, p.nombre, p.apellido 
                 FROM consultas c 
                 JOIN patient p ON c.paciente_id = p.paciente_id 
                 WHERE c.estado = 'Programada' 
                 AND c.fecha_consulta < DATE_SUB(NOW(), INTERVAL $minutos_no_asistencia MINUTE)
                 AND c.tratamiento_finalizado = 0
                 LIMIT 50";

$resConsultas = $con->query($sqlConsultas);
$consultas_no_asistidas = 0;

while ($consulta = $resConsultas->fetch_assoc()) {
    // Marcar como no asistida
    $sqlUpdate = "UPDATE consultas SET estado = 'NoAsistio' WHERE consulta_id = " . $consulta['consulta_id'];
    $con->query($sqlUpdate);
    
    // Crear alerta de no asistencia
    $mensaje = "El paciente {$consulta['nombre']} {$consulta['apellido']} no asistió a su consulta programada";
    $sqlAlerta = "INSERT INTO alertas_paciente 
                  (paciente_id, tipo, titulo, mensaje, fecha_programada, estado, prioridad) 
                  VALUES (?, 'ConsultaPerdida', 'Consulta No Asistida', ?, NOW(), 'Pendiente', 'Alta')";
    $stmt = $con->prepare($sqlAlerta);
    $stmt->bind_param("is", $consulta['paciente_id'], $mensaje);
    $stmt->execute();
    $stmt->close();
    
    $consultas_no_asistidas++;
    escribir_log("⚠️ Consulta #{$consulta['consulta_id']} marcada como no asistida: {$consulta['nombre']} {$consulta['apellido']}");
}

escribir_log("📊 Consultas no asistidas detectadas: $consultas_no_asistidas");

// =====================================================
// 3. RECORDATORIOS DE RECETAS PENDIENTES
// =====================================================
escribir_log("💊 Verificando recetas pendientes...");

// CONFIGURACIÓN: Tiempo para recordar recetas
$sqlConfig = "SELECT valor FROM config_alertas WHERE nombre = 'recordatorio_receta' AND activo = 1";
$resConfig = $con->query($sqlConfig);
$minutos_recordatorio = 4320; // Default: 3 días

if ($resConfig && $resConfig->num_rows > 0) {
    $config = $resConfig->fetch_assoc();
    $minutos_recordatorio = $config['valor'];
}

$sqlRecetas = "SELECT r.*, p.nombre, p.apellido 
               FROM recetas r 
               JOIN patient p ON r.paciente_id = p.paciente_id 
               WHERE r.estado = 'Pendiente' 
               AND r.fecha_emision < DATE_SUB(NOW(), INTERVAL $minutos_recordatorio MINUTE)
               AND NOT EXISTS (
                   SELECT 1 FROM alertas_paciente 
                   WHERE paciente_id = r.paciente_id 
                   AND tipo = 'RecetaPendiente' 
                   AND DATE(created_at) = CURDATE()
               )
               LIMIT 50";

$resRecetas = $con->query($sqlRecetas);
$recordatorios_enviados = 0;

while ($receta = $resRecetas->fetch_assoc()) {
    $mensaje = "Tiene una {$receta['tipo']} pendiente: " . substr($receta['descripcion'], 0, 50);
    $sqlAlerta = "INSERT INTO alertas_paciente 
                  (paciente_id, tipo, titulo, mensaje, fecha_programada, estado, prioridad) 
                  VALUES (?, 'RecetaPendiente', 'Recordatorio de Receta', ?, NOW(), 'Pendiente', 'Media')";
    $stmt = $con->prepare($sqlAlerta);
    $stmt->bind_param("is", $receta['paciente_id'], $mensaje);
    $stmt->execute();
    $stmt->close();
    
    $recordatorios_enviados++;
    escribir_log("📋 Recordatorio de receta enviado: {$receta['nombre']} {$receta['apellido']}");
}

escribir_log("📊 Recordatorios de recetas enviados: $recordatorios_enviados");

// =====================================================
// 4. RECORDATORIOS DE LABORATORIOS PENDIENTES
// =====================================================
escribir_log("🔬 Verificando laboratorios pendientes...");

$sqlConfig = "SELECT valor FROM config_alertas WHERE nombre = 'recordatorio_laboratorio' AND activo = 1";
$resConfig = $con->query($sqlConfig);
$minutos_lab = 2880; // Default: 2 días

if ($resConfig && $resConfig->num_rows > 0) {
    $config = $resConfig->fetch_assoc();
    $minutos_lab = $config['valor'];
}

$sqlLabs = "SELECT r.*, p.nombre, p.apellido 
            FROM recetas r 
            JOIN patient p ON r.paciente_id = p.paciente_id 
            WHERE r.tipo = 'Laboratorio' 
            AND r.estado = 'Pendiente' 
            AND r.fecha_emision < DATE_SUB(NOW(), INTERVAL $minutos_lab MINUTE)
            AND NOT EXISTS (
                SELECT 1 FROM alertas_paciente 
                WHERE paciente_id = r.paciente_id 
                AND tipo = 'LaboratorioPendiente' 
                AND DATE(created_at) = CURDATE()
            )
            LIMIT 50";

$resLabs = $con->query($sqlLabs);
$labs_enviados = 0;

while ($lab = $resLabs->fetch_assoc()) {
    $mensaje = "Laboratorio pendiente: " . substr($lab['descripcion'], 0, 50);
    $sqlAlerta = "INSERT INTO alertas_paciente 
                  (paciente_id, tipo, titulo, mensaje, fecha_programada, estado, prioridad) 
                  VALUES (?, 'LaboratorioPendiente', 'Recordatorio de Laboratorio', ?, NOW(), 'Pendiente', 'Alta')";
    $stmt = $con->prepare($sqlAlerta);
    $stmt->bind_param("is", $lab['paciente_id'], $mensaje);
    $stmt->execute();
    $stmt->close();
    
    $labs_enviados++;
    escribir_log("🔬 Recordatorio de laboratorio enviado: {$lab['nombre']} {$lab['apellido']}");
}

escribir_log("📊 Recordatorios de laboratorios enviados: $labs_enviados");

// =====================================================
// RESUMEN FINAL
// =====================================================
$total_acciones = $alertas_enviadas + $consultas_no_asistidas + $recordatorios_enviados + $labs_enviados;
escribir_log("========== FIN DE EJECUCIÓN ==========");
escribir_log("📊 RESUMEN: $total_acciones acciones realizadas");
escribir_log("");

$con->close();

// Retornar código de éxito para el cron
exit(0);
?>