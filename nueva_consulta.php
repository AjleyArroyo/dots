<?php
session_start();
include("dbconnection.php");

if (!isset($_SESSION['me_id'])) {
    header("Location: login.php");
    exit();
}

$paciente_id = isset($_GET['paciente_id']) ? intval($_GET['paciente_id']) : 0;

if ($paciente_id == 0) {
    header("Location: pacientes.php");
    exit();
}

// Obtener información del paciente
$sqlPac = "SELECT * FROM patient WHERE paciente_id = $paciente_id";
$resPac = $con->query($sqlPac);
if ($resPac->num_rows == 0) {
    header("Location: pacientes.php");
    exit();
}
$paciente = $resPac->fetch_assoc();

$mensaje_error = "";
$mensaje_exito = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $peso = !empty($_POST['peso']) ? (float)$_POST['peso'] : null;
    $saturacion = !empty($_POST['saturacion']) ? (int)$_POST['saturacion'] : null;
    $notas = trim($_POST['notas'] ?? '');
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $proxima_consulta = !empty($_POST['proxima_consulta']) ? $_POST['proxima_consulta'] : null;
    $tratamiento_finalizado = isset($_POST['tratamiento_finalizado']) ? 1 : 0;
    
    // CONFIGURACIÓN: Minutos para próximas consultas por defecto
    // EDITA AQUÍ para cambiar el intervalo de consultas en pruebas
    $minutos_default = 10080; // 7 días = 10080 minutos. Para pruebas usa 1 minuto
    
    if (!$proxima_consulta && !$tratamiento_finalizado) {
        $proxima_consulta = date('Y-m-d H:i:s', strtotime("+$minutos_default minutes"));
    }

    $con->begin_transaction();
    
    try {
        // 1. Registrar consulta
        $sqlCon = "INSERT INTO consultas 
                   (paciente_id, me_personel_id, fecha_consulta, proxima_consulta, peso, saturacion, notas, diagnostico, estado, tratamiento_finalizado) 
                   VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, 'Realizada', ?)";
        $stmtCon = $con->prepare($sqlCon);
        $me_id = $_SESSION['me_id'];
        $stmtCon->bind_param("iisdissi", $paciente_id, $me_id, $proxima_consulta, $peso, $saturacion, $notas, $diagnostico, $tratamiento_finalizado);
        $stmtCon->execute();
        $consulta_id = $con->insert_id;
        $stmtCon->close();

        // 2. Guardar medición en historial
        if ($peso !== null && $saturacion !== null) {
            $sqlMed = "INSERT INTO historial_mediciones (paciente_id, consulta_id, peso, saturacion) 
                      VALUES (?, ?, ?, ?)";
            $stmtMed = $con->prepare($sqlMed);
            $stmtMed->bind_param("iidi", $paciente_id, $consulta_id, $peso, $saturacion);
            $stmtMed->execute();
            $stmtMed->close();

            // 3. Actualizar peso y saturación actuales del paciente
            $sqlUpd = "UPDATE patient SET peso_actual = ?, saturacion_actual = ? WHERE paciente_id = ?";
            $stmtUpd = $con->prepare($sqlUpd);
            $stmtUpd->bind_param("dii", $peso, $saturacion, $paciente_id);
            $stmtUpd->execute();
            $stmtUpd->close();

            // 4. Calcular estado de salud (comparar con medición anterior)
            $sqlPrev = "SELECT peso, saturacion FROM historial_mediciones 
                       WHERE paciente_id = ? AND medicion_id < ? 
                       ORDER BY fecha_medicion DESC LIMIT 1";
            $stmtPrev = $con->prepare($sqlPrev);
            $medicion_id_actual = $con->insert_id;
            $stmtPrev->bind_param("ii", $paciente_id, $medicion_id_actual);
            $stmtPrev->execute();
            $resPrev = $stmtPrev->get_result();
            
            if ($resPrev->num_rows > 0) {
                $prev = $resPrev->fetch_assoc();
                $peso_anterior = $prev['peso'];
                $sat_anterior = $prev['saturacion'];
                
                // Lógica simple: si peso aumenta Y saturación mejora = Mejorando
                // Si ambos empeoran = Empeorando, sino = Estable
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
            $stmtPrev->close();
        }

        // 5. Crear alerta para próxima consulta si no finalizó tratamiento
        if ($proxima_consulta && !$tratamiento_finalizado) {
            // CONFIGURACIÓN: Obtener anticipación de alertas desde config_alertas
            // EDITA en la tabla config_alertas el valor 'anticipacion_consulta' para cambiar cuándo notificar
            $sqlConfig = "SELECT valor FROM config_alertas WHERE nombre = 'anticipacion_consulta' AND activo = 1";
            $resConfig = $con->query($sqlConfig);
            $minutos_anticipacion = 10080; // Default 7 días
            if ($resConfig && $resConfig->num_rows > 0) {
                $configRow = $resConfig->fetch_assoc();
                $minutos_anticipacion = $configRow['valor'];
            }
            
            $fecha_alerta = date('Y-m-d H:i:s', strtotime($proxima_consulta) - ($minutos_anticipacion * 60));
            
            $sqlAlert = "INSERT INTO alertas_paciente 
                        (paciente_id, tipo, titulo, mensaje, fecha_programada, prioridad) 
                        VALUES (?, 'ConsultaProxima', 'Consulta Programada', 
                        CONCAT('Tiene una consulta programada para el ', ?), ?, 'Media')";
            $stmtAlert = $con->prepare($sqlAlert);
            $stmtAlert->bind_param("iss", $paciente_id, $proxima_consulta, $fecha_alerta);
            $stmtAlert->execute();
            $stmtAlert->close();
        }

        // 6. Procesar recetas si las hay
        if (!empty($_POST['recetas'])) {
            foreach ($_POST['recetas'] as $receta) {
                if (empty($receta['descripcion'])) continue;
                
                $sqlRec = "INSERT INTO recetas 
                          (paciente_id, consulta_id, me_personel_id, tipo, descripcion, instrucciones, prioridad) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmtRec = $con->prepare($sqlRec);
                $stmtRec->bind_param("iiissss", 
                    $paciente_id, $consulta_id, $me_id, 
                    $receta['tipo'], $receta['descripcion'], 
                    $receta['instrucciones'], $receta['prioridad']
                );
                $stmtRec->execute();
                $stmtRec->close();
            }
        }

        $con->commit();
        header("Location: ver_paciente.php?id=$paciente_id&consulta_registrada=1");
        exit();
        
    } catch (Exception $e) {
        $con->rollback();
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Fecha por defecto (próxima semana)
$fecha_default = date('Y-m-d\TH:i', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Consulta - DOTS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .header h1 { color: #667eea; font-size: 28px; margin-bottom: 5px; }
        .patient-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 15px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-error { background: #ffe6e6; color: #c41e3a; border-left: 4px solid #c41e3a; }
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }
        .form-section:last-of-type { border-bottom: none; }
        .form-section h3 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        label { font-weight: 600; color: #333; margin-bottom: 8px; font-size: 14px; }
        input, select, textarea {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        textarea { resize: vertical; min-height: 100px; }
        .measurement-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        .measurement-value { font-size: 32px; font-weight: 700; margin: 10px 0; }
        .btn-measure {
            background: white;
            color: #667eea;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }
        .receta-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }
        .btn-add-receta {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .button-group { display: flex; gap: 15px; margin-top: 30px; }
        .btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: white; color: #667eea; border: 2px solid #667eea; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-top: 15px; }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>📋 Nueva Consulta Médica</h1>
        <div class="patient-info">
            <div style="font-size: 32px;">👤</div>
            <div>
                <div style="font-size: 18px; font-weight: 700;">
                    <?= htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']) ?>
                </div>
                <div style="font-size: 14px; opacity: 0.9;">
                    Peso actual: <?= $paciente['peso_actual'] ?? 'No registrado' ?> kg | 
                    Saturación: <?= $paciente['saturacion_actual'] ?? 'No registrada' ?>%
                </div>
            </div>
        </div>
    </div>

    <div class="form-container">
        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($mensaje_error) ?></div>
        <?php endif; ?>

        <form method="POST" id="formConsulta">
            <!-- Mediciones -->
            <div class="form-section">
                <h3>📊 Mediciones</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <div class="measurement-box">
                            <div style="font-size: 12px; opacity: 0.9;">PESO (kg)</div>
                            <div class="measurement-value" id="pesoDisplay">--</div>
                            <button type="button" class="btn-measure" onclick="leerPeso()">⚖️ Leer Peso</button>
                        </div>
                        <input type="hidden" name="peso" id="peso">
                    </div>

                    <div class="form-group">
                        <div class="measurement-box">
                            <div style="font-size: 12px; opacity: 0.9;">SATURACIÓN (%)</div>
                            <div class="measurement-value" id="saturacionDisplay">--</div>
                            <button type="button" class="btn-measure" onclick="leerSaturacion()">🫀 Leer Saturación</button>
                        </div>
                        <input type="hidden" name="saturacion" id="saturacion">
                    </div>
                </div>
            </div>

            <!-- Notas y Diagnóstico -->
            <div class="form-section">
                <h3>📝 Notas de la Consulta</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Notas</label>
                        <textarea name="notas" placeholder="Síntomas, observaciones, quejas del paciente..."></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Diagnóstico</label>
                        <textarea name="diagnostico" placeholder="Diagnóstico y plan de tratamiento..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Recetas y Laboratorios -->
            <div class="form-section">
                <h3>💊 Recetas / Laboratorios</h3>
                <div id="recetas-container"></div>
                <button type="button" class="btn-add-receta" onclick="agregarReceta()">➕ Agregar Receta/Laboratorio</button>
            </div>

            <!-- Próxima Consulta -->
            <div class="form-section">
                <h3>📅 Seguimiento</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Próxima Consulta</label>
                        <input type="datetime-local" name="proxima_consulta" id="proxima_consulta" value="<?= $fecha_default ?>">
                        <div style="font-size: 12px; color: #6c757d; margin-top: 5px;">
                            Por defecto: 7 días. Ajustar según necesidad.
                        </div>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="tratamiento_finalizado" id="tratamiento_finalizado">
                    <label for="tratamiento_finalizado" style="margin-bottom: 0;">✅ Tratamiento finalizado (no programar más consultas)</label>
                </div>
            </div>

            <div class="button-group">
                <a href="ver_paciente.php?id=<?= $paciente_id ?>" class="btn btn-secondary">❌ Cancelar</a>
                <button type="submit" class="btn btn-primary">✅ Guardar Consulta</button>
            </div>
        </form>
    </div>
</div>

<script>
const ESP32_PESO_URL = 'http://192.168.1.100/leer_peso';
const ESP32_SATURACION_URL = 'http://192.168.1.100/leer_saturacion';

function leerPeso() {
    document.getElementById('pesoDisplay').textContent = '⏳';
    fetch(ESP32_PESO_URL)
        .then(r => r.json())
        .then(data => {
            if (data.peso) {
                document.getElementById('peso').value = data.peso;
                document.getElementById('pesoDisplay').textContent = data.peso;
            }
        })
        .catch(e => {
            console.error(e);
            document.getElementById('pesoDisplay').textContent = 'Error';
        });
}

function leerSaturacion() {
    document.getElementById('saturacionDisplay').textContent = '⏳';
    fetch(ESP32_SATURACION_URL)
        .then(r => r.json())
        .then(data => {
            if (data.saturacion) {
                document.getElementById('saturacion').value = data.saturacion;
                document.getElementById('saturacionDisplay').textContent = data.saturacion + '%';
            }
        })
        .catch(e => {
            console.error(e);
            document.getElementById('saturacionDisplay').textContent = 'Error';
        });
}

let recetaCount = 0;
function agregarReceta() {
    recetaCount++;
    const html = `
        <div class="receta-item" id="receta-${recetaCount}">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h4 style="color: #667eea;">Receta #${recetaCount}</h4>
                <button type="button" onclick="eliminarReceta(${recetaCount})" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">🗑️</button>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <label style="font-size: 13px; display: block; margin-bottom: 5px;">Tipo</label>
                    <select name="recetas[${recetaCount}][tipo]" style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 5px;">
                        <option value="Medicamento">💊 Medicamento</option>
                        <option value="Laboratorio">🔬 Laboratorio</option>
                        <option value="Estudio">📋 Estudio</option>
                        <option value="Otro">📌 Otro</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 13px; display: block; margin-bottom: 5px;">Prioridad</label>
                    <select name="recetas[${recetaCount}][prioridad]" style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 5px;">
                        <option value="Normal">Normal</option>
                        <option value="Urgente">⚠️ Urgente</option>
                        <option value="Alta">🔴 Alta</option>
                    </select>
                </div>
            </div>
            <div style="margin-top: 15px;">
                <label style="font-size: 13px; display: block; margin-bottom: 5px;">Descripción</label>
                <textarea name="recetas[${recetaCount}][descripcion]" placeholder="Ej: Ibuprofeno 400mg..." style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 5px; font-family: inherit;" rows="2"></textarea>
            </div>
            <div style="margin-top: 15px;">
                <label style="font-size: 13px; display: block; margin-bottom: 5px;">Instrucciones</label>
                <textarea name="recetas[${recetaCount}][instrucciones]" placeholder="Ej: Tomar 1 cada 8 horas..." style="width: 100%; padding: 8px; border: 2px solid #e0e0e0; border-radius: 5px; font-family: inherit;" rows="2"></textarea>
            </div>
        </div>
    `;
    document.getElementById('recetas-container').insertAdjacentHTML('beforeend', html);
}

function eliminarReceta(id) {
    document.getElementById('receta-' + id).remove();
}

document.getElementById('tratamiento_finalizado').addEventListener('change', function() {
    document.getElementById('proxima_consulta').disabled = this.checked;
});
</script>

</body>
</html>