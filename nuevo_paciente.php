<?php
session_start();
include("dbconnection.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['me_id'])) {
    header("Location: index.php");
    exit();
}

$mensaje_error = "";
$mensaje_exito = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre']      ?? '');
    $apellido    = trim($_POST['apellido']    ?? '');
    $correo      = trim($_POST['correo']      ?? '');
    $telefono    = trim($_POST['telefono']    ?? '');
    $gabinete_id = (int)($_POST['gabinete_id'] ?? 0);
    $huella_id   = (int)($_POST['huella_id']   ?? 0);
    $emergencia1 = trim($_POST['emergencia1'] ?? '');
    $emergencia2 = trim($_POST['emergencia2'] ?? '');
    
    $peso        = !empty($_POST['peso']) ? (float)$_POST['peso'] : null;
    $saturacion  = !empty($_POST['saturacion']) ? (int)$_POST['saturacion'] : null;
    $proxima_consulta = !empty($_POST['proxima_consulta']) ? $_POST['proxima_consulta'] : null;

    if ($nombre === '' || $apellido === '' || $gabinete_id === 0 || $huella_id === 0) {
        $mensaje_error = "Por favor completa al menos: nombre, apellido, gabinete ID y huella ID.";
    } else {
        $con->begin_transaction();
        
        try {
            $sql = "INSERT INTO patient 
                        (nombre, apellido, correo, telefono, gabinete_id, huella_id, emergencia1, emergencia2, peso_actual, saturacion_actual, estado_salud)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Nuevo')";

            $stmt = $con->prepare($sql);
            $stmt->bind_param(
                "ssssiissdi",
                $nombre, $apellido, $correo, $telefono, $gabinete_id, $huella_id, $emergencia1, $emergencia2, $peso, $saturacion
            );

            if (!$stmt->execute()) {
                if ($stmt->errno == 1062 && strpos($stmt->error, 'huella_id') !== false) {
                    throw new Exception("Esta huella (ID: $huella_id) ya está asignada a otro paciente.");
                } else {
                    throw new Exception("Error al insertar paciente: " . $stmt->error);
                }
            }
            
            $paciente_id = $con->insert_id;
            $stmt->close();

            if ($peso !== null && $saturacion !== null) {
                $sqlMed = "INSERT INTO historial_mediciones (paciente_id, peso, saturacion, notas) 
                          VALUES (?, ?, ?, 'Registro inicial')";
                $stmtMed = $con->prepare($sqlMed);
                $stmtMed->bind_param("idi", $paciente_id, $peso, $saturacion);
                $stmtMed->execute();
                $stmtMed->close();
            }

            if ($proxima_consulta) {
                $sqlCon = "INSERT INTO consultas 
                          (paciente_id, me_personel_id, fecha_consulta, proxima_consulta, peso, saturacion, estado, notas) 
                          VALUES (?, ?, NOW(), ?, ?, ?, 'Realizada', 'Consulta inicial - Registro del paciente')";
                $stmtCon = $con->prepare($sqlCon);
                $me_id = $_SESSION['me_id'];
                $stmtCon->bind_param("iisii", $paciente_id, $me_id, $proxima_consulta, $peso, $saturacion);
                $stmtCon->execute();
                $stmtCon->close();

                $sqlAlert = "INSERT INTO alertas_paciente 
                            (paciente_id, tipo, titulo, mensaje, fecha_programada, prioridad) 
                            VALUES (?, 'ConsultaProxima', 'Consulta Programada', 
                            CONCAT('Tiene una consulta programada para el ', ?), ?, 'Media')";
                $stmtAlert = $con->prepare($sqlAlert);
                $stmtAlert->bind_param("iss", $paciente_id, $proxima_consulta, $proxima_consulta);
                $stmtAlert->execute();
                $stmtAlert->close();
            }

            $con->commit();
            header("Location: ver_paciente.php?id=$paciente_id&nuevo=1");
            exit();
            
        } catch (Exception $e) {
            $con->rollback();
            $mensaje_error = $e->getMessage();
        }
    }
}

$gabinetes = $con->query("SELECT gabinete_id, nombre FROM gabinete ORDER BY gabinete_id ASC");
$fecha_default = date('Y-m-d\TH:i', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300">
    <title>Registrar Paciente - DOTS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            color: #6c757d;
            font-size: 14px;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-error {
            background: #ffe6e6;
            color: #c41e3a;
            border-left: 4px solid #c41e3a;
        }

        .alert-success {
            background: #e6f7e6;
            color: #2d7a2d;
            border-left: 4px solid #2d7a2d;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

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

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .required {
            color: #dc3545;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"],
        input[type="datetime-local"],
        select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input:disabled, input:read-only {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        .helper-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        .measurement-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            position: relative;
        }

        .measurement-value {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .measurement-label {
            font-size: 12px;
            opacity: 0.9;
        }

        .btn-measure {
            background: white;
            color: #667eea;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        .btn-measure:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-measure:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid rgba(102, 126, 234, 0.3);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .status-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
        }

        .status-indicator.active {
            background: #28a745;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.1); }
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>➕ Registrar Nuevo Paciente</h1>
        <p>Complete la información del paciente y registre sus mediciones iniciales</p>
    </div>

    <div class="form-container">
        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-error">
                ⚠️ <?php echo htmlspecialchars($mensaje_error); ?>
            </div>
        <?php endif; ?>

        <form action="nuevo_paciente.php" method="POST" id="formPaciente">
            <!-- Información Personal -->
            <div class="form-section">
                <h3>👤 Información Personal</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            Nombre <span class="required">*</span>
                        </label>
                        <input type="text" name="nombre" id="nombre" required>
                    </div>

                    <div class="form-group">
                        <label>
                            Apellido <span class="required">*</span>
                        </label>
                        <input type="text" name="apellido" id="apellido" required>
                    </div>

                    <div class="form-group">
                        <label>📧 Correo Electrónico</label>
                        <input type="email" name="correo" id="correo">
                        <div class="helper-text">Opcional: para notificaciones</div>
                    </div>

                    <div class="form-group">
                        <label>📱 Teléfono</label>
                        <input type="tel" name="telefono" id="telefono">
                        <div class="helper-text">Opcional: contacto principal</div>
                    </div>
                </div>
            </div>

            <!-- Asignaciones del Sistema -->
            <div class="form-section">
                <h3>🏥 Asignaciones del Sistema</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            🏢 Gabinete Asignado <span class="required">*</span>
                        </label>
                        <select name="gabinete_id" id="gabinete_id" required>
                            <option value="">Seleccionar gabinete...</option>
                            <?php while($gab = $gabinetes->fetch_assoc()): ?>
                                <option value="<?= $gab['gabinete_id'] ?>">
                                    Gabinete <?= $gab['gabinete_id'] ?> - <?= htmlspecialchars($gab['nombre']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            👆 ID Huella Digital <span class="required">*</span>
                        </label>
                        <div class="measurement-box">
                            <div class="status-indicator" id="huellaStatus"></div>
                            <div class="measurement-label">HUELLA</div>
                            <div class="measurement-value" id="huellaDisplay">--</div>
                            <button type="button" class="btn-measure" id="btnLeerHuella" onclick="leerHuella()">
                                👆 Leer Huella
                            </button>
                        </div>
                        <input type="hidden" name="huella_id" id="huella_id" required>
                        <div class="helper-text">Presione el botón y coloque el dedo en el sensor</div>
                    </div>
                </div>
            </div>

            <!-- Mediciones Iniciales -->
            <div class="form-section">
                <h3>📊 Mediciones Iniciales</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <div class="measurement-box">
                            <div class="status-indicator" id="pesoStatus"></div>
                            <div class="measurement-label">PESO (kg)</div>
                            <div class="measurement-value" id="pesoDisplay">--</div>
                            <button type="button" class="btn-measure" id="btnLeerPeso" onclick="leerPeso()">
                                ⚖️ Leer Peso
                            </button>
                        </div>
                        <input type="hidden" name="peso" id="peso">
                    </div>

                    <div class="form-group">
                        <div class="measurement-box">
                            <div class="status-indicator" id="saturacionStatus"></div>
                            <div class="measurement-label">SATURACIÓN (%)</div>
                            <div class="measurement-value" id="saturacionDisplay">--</div>
                            <button type="button" class="btn-measure" id="btnLeerSaturacion" onclick="leerSaturacion()">
                                🫀 Leer Saturación
                            </button>
                        </div>
                        <input type="hidden" name="saturacion" id="saturacion">
                    </div>
                </div>
            </div>

            <!-- Próxima Consulta -->
            <div class="form-section">
                <h3>📅 Programación de Consulta</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>
                            🗓️ Próxima Consulta
                        </label>
                        <input type="datetime-local" name="proxima_consulta" id="proxima_consulta" value="<?= $fecha_default ?>">
                        <div class="helper-text">Por defecto: 7 días desde hoy. Ajusta según necesidad del paciente.</div>
                    </div>
                </div>
            </div>

            <!-- Contactos de Emergencia -->
            <div class="form-section">
                <h3>🚨 Contactos de Emergencia</h3>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Contacto de Emergencia 1</label>
                        <input type="text" name="emergencia1" id="emergencia1" placeholder="Nombre y teléfono">
                        <div class="helper-text">Ej: María López - 555-1234</div>
                    </div>

                    <div class="form-group full-width">
                        <label>Contacto de Emergencia 2</label>
                        <input type="text" name="emergencia2" id="emergencia2" placeholder="Nombre y teléfono">
                        <div class="helper-text">Ej: Juan Pérez - 555-5678</div>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <a href="pacientes.php" class="btn btn-secondary">
                    ❌ Cancelar
                </a>
                <button type="submit" class="btn btn-primary" id="btnSubmit">
                    ✅ Registrar Paciente
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// =====================================================
// CONFIGURACIÓN ESP32 - IGUAL QUE DOTSBOX.PHP
// =====================================================
const ESP32_IP = '192.168.0.24'; // CAMBIAR POR TU IP
const ESP32_HUELLA_URL = `http://${ESP32_IP}/leer_huella`;
const ESP32_PESO_URL = `http://${ESP32_IP}/leer_peso`;
const ESP32_SATURACION_URL = `http://${ESP32_IP}/leer_saturacion`;

// =====================================================
// LEER HUELLA - MÉTODO SIMPLE COMO EN DOTSBOX
// =====================================================
function leerHuella() {
    console.log('🔍 Leyendo huella...');
    
    const btn = document.getElementById('btnLeerHuella');
    const display = document.getElementById('huellaDisplay');
    const status = document.getElementById('huellaStatus');
    const input = document.getElementById('huella_id');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Leyendo...';
    display.textContent = '⏳';
    status.classList.add('active');
    
    // Llamar al ESP32 para leer huella
    fetch(ESP32_HUELLA_URL)
        .then(r => r.json())
        .then(data => {
            if (data && data.huella_id) {
                console.log('✅ Huella leída:', data.huella_id);
                input.value = data.huella_id;
                display.textContent = '#' + data.huella_id;
                
                // Verificar si la huella ya existe
                verificarHuellaExistente(data.huella_id);
            } else {
                throw new Error('No se pudo leer la huella');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            display.textContent = '❌';
            alert('❌ No se pudo leer la huella. Verifique la conexión con el ESP32.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '👆 Leer Huella';
            status.classList.remove('active');
        });
}

// =====================================================
// VERIFICAR SI HUELLA YA EXISTE
// =====================================================
function verificarHuellaExistente(huella_id) {
    fetch(`verificar_huella.php?huella_id=${huella_id}`)
        .then(r => r.json())
        .then(check => {
            if (check.existe) {
                alert(`⚠️ Esta huella ya está asignada a: ${check.paciente}`);
                document.getElementById('huella_id').value = '';
                document.getElementById('huellaDisplay').textContent = '❌';
            }
        })
        .catch(error => {
            console.error('Error al verificar huella:', error);
        });
}

// =====================================================
// LEER PESO - MÉTODO SIMPLE COMO EN DOTSBOX
// =====================================================
function leerPeso() {
    console.log('⚖️ Leyendo peso...');
    
    const btn = document.getElementById('btnLeerPeso');
    const display = document.getElementById('pesoDisplay');
    const status = document.getElementById('pesoStatus');
    const input = document.getElementById('peso');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Leyendo...';
    display.textContent = '⏳';
    status.classList.add('active');
    
    fetch(ESP32_PESO_URL)
        .then(r => r.json())
        .then(data => {
            if (data && data.peso) {
                console.log('✅ Peso leído:', data.peso);
                input.value = data.peso;
                display.textContent = data.peso + ' kg';
            } else {
                throw new Error('No se pudo leer el peso');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            display.textContent = '❌';
            alert('❌ No se pudo leer el peso. Verifique la conexión con el ESP32.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '⚖️ Leer Peso';
            status.classList.remove('active');
        });
}

// =====================================================
// LEER SATURACIÓN - MÉTODO SIMPLE COMO EN DOTSBOX
// =====================================================
function leerSaturacion() {
    console.log('🫀 Leyendo saturación...');
    
    const btn = document.getElementById('btnLeerSaturacion');
    const display = document.getElementById('saturacionDisplay');
    const status = document.getElementById('saturacionStatus');
    const input = document.getElementById('saturacion');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Leyendo...';
    display.textContent = '⏳';
    status.classList.add('active');
    
    fetch(ESP32_SATURACION_URL)
        .then(r => r.json())
        .then(data => {
            if (data && data.saturacion) {
                console.log('✅ Saturación leída:', data.saturacion);
                input.value = data.saturacion;
                display.textContent = data.saturacion + '%';
            } else {
                throw new Error('No se pudo leer la saturación');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            display.textContent = '❌';
            alert('❌ No se pudo leer la saturación. Verifique la conexión con el ESP32.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '🫀 Leer Saturación';
            status.classList.remove('active');
        });
}

// =====================================================
// VALIDACIÓN DEL FORMULARIO
// =====================================================
document.getElementById('formPaciente').addEventListener('submit', function(e) {
    const huella = document.getElementById('huella_id').value;
    
    if (!huella) {
        e.preventDefault();
        alert('⚠️ Por favor, lea la huella digital antes de continuar.');
        document.getElementById('btnLeerHuella').focus();
        return false;
    }
});

// =====================================================
// VERIFICAR CONEXIÓN AL CARGAR
// =====================================================
window.addEventListener('load', function() {
    console.log('✅ Sistema cargado');
    console.log('ESP32 URL:', ESP32_HUELLA_URL);
});
</script>

</body>
</html>