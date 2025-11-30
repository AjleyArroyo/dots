<?php
session_start();
include("dbconnection.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['me_id'])) {
    header("Location: login.php");
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

    if ($nombre === '' || $apellido === '' || $gabinete_id === 0 || $huella_id === 0) {
        $mensaje_error = "Por favor completa al menos: nombre, apellido, gabinete ID y huella ID.";
    } else {
        $sql = "INSERT INTO patient 
                    (nombre, apellido, correo, telefono, gabinete_id, huella_id, emergencia1, emergencia2)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $con->prepare($sql);
        if (!$stmt) {
            $mensaje_error = "Error en preparación: " . $con->error;
        } else {
            $stmt->bind_param(
                "ssssiiss",
                $nombre,
                $apellido,
                $correo,
                $telefono,
                $gabinete_id,
                $huella_id,
                $emergencia1,
                $emergencia2
            );

            if (!$stmt->execute()) {
                if ($stmt->errno == 1062 && strpos($stmt->error, 'huella_id') !== false) {
                    $mensaje_error = "Esta huella (ID: $huella_id) ya está asignada a otro paciente.";
                } else {
                    $mensaje_error = "Error al insertar: " . $stmt->error;
                }
            } else {
                header("Location: pacientes.php");
                exit();
            }
            $stmt->close();
        }
    }
}

// Obtener lista de gabinetes disponibles
$gabinetes = $con->query("SELECT gabinete_id, nombre FROM gabinete ORDER BY gabinete_id ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            max-width: 800px;
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
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

        input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        .fingerprint-group {
            position: relative;
        }

        .fingerprint-input-wrapper {
            display: flex;
            gap: 10px;
        }

        #huella_id {
            flex: 1;
        }

        .btn-fingerprint {
            padding: 12px 20px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .btn-fingerprint:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.4);
        }

        .btn-fingerprint:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-fingerprint.reading {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.6;
            }
        }

        .fingerprint-status {
            margin-top: 10px;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            display: none;
            align-items: center;
            gap: 8px;
        }

        .fingerprint-status.show {
            display: flex;
        }

        .fingerprint-status.waiting {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .fingerprint-status.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .fingerprint-status.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
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

        .helper-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
            }

            .fingerprint-input-wrapper {
                flex-direction: column;
            }

            .btn-fingerprint {
                width: 100%;
            }
        }

        /* Animación de carga */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-container {
            animation: fadeIn 0.5s ease;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>➕ Registrar Nuevo Paciente</h1>
        <p>Complete la información del paciente y registre su huella digital</p>
    </div>

    <div class="form-container">
        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-error">
                ⚠️ <?php echo htmlspecialchars($mensaje_error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje_exito)): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($mensaje_exito); ?>
            </div>
        <?php endif; ?>

        <form action="nuevo_paciente.php" method="POST" id="formPaciente">
            <div class="form-grid">
                <!-- Nombre -->
                <div class="form-group">
                    <label>
                        👤 Nombre <span class="required">*</span>
                    </label>
                    <input type="text" name="nombre" id="nombre" required>
                </div>

                <!-- Apellido -->
                <div class="form-group">
                    <label>
                        👤 Apellido <span class="required">*</span>
                    </label>
                    <input type="text" name="apellido" id="apellido" required>
                </div>

                <!-- Correo -->
                <div class="form-group">
                    <label>📧 Correo Electrónico</label>
                    <input type="email" name="correo" id="correo">
                    <div class="helper-text">Opcional: para notificaciones</div>
                </div>

                <!-- Teléfono -->
                <div class="form-group">
                    <label>📱 Teléfono</label>
                    <input type="tel" name="telefono" id="telefono">
                    <div class="helper-text">Opcional: contacto principal</div>
                </div>

                <!-- Gabinete -->
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

                <!-- Huella Digital -->
                <div class="form-group fingerprint-group">
                    <label>
                        👆 ID Huella Digital <span class="required">*</span>
                    </label>
                    <div class="fingerprint-input-wrapper">
                        <input type="number" name="huella_id" id="huella_id" required readonly>
                        <button type="button" class="btn-fingerprint" id="btnLeerHuella">
                            <span id="btnText">🔍 Leer Huella</span>
                        </button>
                    </div>
                    <div class="fingerprint-status" id="fingerprintStatus"></div>
                </div>

                <!-- Emergencia 1 -->
                <div class="form-group full-width">
                    <label>🚨 Contacto de Emergencia 1</label>
                    <input type="text" name="emergencia1" id="emergencia1" placeholder="Nombre y teléfono">
                    <div class="helper-text">Ej: María López - 555-1234</div>
                </div>

                <!-- Emergencia 2 -->
                <div class="form-group full-width">
                    <label>🚨 Contacto de Emergencia 2</label>
                    <input type="text" name="emergencia2" id="emergencia2" placeholder="Nombre y teléfono">
                    <div class="helper-text">Ej: Juan Pérez - 555-5678</div>
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
// ==============================================
// SISTEMA DE LECTURA DE HUELLA DESDE ESP32
// ==============================================

// CONFIGURACIÓN - Ajusta esto según tu ESP32
const ESP32_URL = 'http://192.168.1.100/leer_huella'; // Cambia la IP de tu ESP32
const TIMEOUT = 30000; // 30 segundos de timeout

const btnLeerHuella = document.getElementById('btnLeerHuella');
const huellaInput = document.getElementById('huella_id');
const fingerprintStatus = document.getElementById('fingerprintStatus');
const btnText = document.getElementById('btnText');
const btnSubmit = document.getElementById('btnSubmit');

let leyendoHuella = false;

btnLeerHuella.addEventListener('click', function() {
    if (leyendoHuella) {
        cancelarLectura();
        return;
    }
    
    iniciarLecturaHuella();
});

function iniciarLecturaHuella() {
    leyendoHuella = true;
    btnLeerHuella.classList.add('reading');
    btnLeerHuella.disabled = false;
    btnText.textContent = '⏸️ Cancelar';
    btnSubmit.disabled = true;
    
    mostrarEstado('waiting', '👆 Coloque el dedo en el lector de huellas del ESP32...');
    
    console.log('🔍 Iniciando lectura de huella desde ESP32...');
    
    // Hacer petición al ESP32
    fetch(ESP32_URL, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        },
        signal: AbortSignal.timeout(TIMEOUT)
    })
    .then(response => {
        console.log('📡 Respuesta del ESP32:', response.status);
        
        if (!response.ok) {
            throw new Error('Error en la respuesta del ESP32: ' + response.status);
        }
        
        return response.json();
    })
    .then(data => {
        console.log('📦 Datos recibidos:', data);
        
        // AJUSTA ESTO según el formato de respuesta de tu ESP32
        // Ejemplo: { "success": true, "huella_id": 123 }
        // O: { "fingerprint_id": 123, "status": "ok" }
        
        if (data.success && data.huella_id) {
            // Éxito
            huellaInput.value = data.huella_id;
            mostrarEstado('success', `✅ Huella registrada correctamente! ID: ${data.huella_id}`);
            console.log('✅ Huella registrada:', data.huella_id);
        } else if (data.huella_id || data.fingerprint_id) {
            // Formato alternativo
            const id = data.huella_id || data.fingerprint_id;
            huellaInput.value = id;
            mostrarEstado('success', `✅ Huella registrada correctamente! ID: ${id}`);
            console.log('✅ Huella registrada:', id);
        } else {
            throw new Error(data.error || 'No se pudo leer la huella');
        }
        
        finalizarLectura(true);
    })
    .catch(error => {
        console.error('❌ Error:', error);
        
        let mensaje = '❌ Error al leer la huella: ';
        
        if (error.name === 'AbortError' || error.name === 'TimeoutError') {
            mensaje += 'Tiempo de espera agotado. Intente nuevamente.';
        } else if (error.message.includes('NetworkError') || error.message.includes('Failed to fetch')) {
            mensaje += 'No se puede conectar al ESP32. Verifique la conexión.';
        } else {
            mensaje += error.message;
        }
        
        mostrarEstado('error', mensaje);
        finalizarLectura(false);
    });
}

function cancelarLectura() {
    console.log('⏸️ Lectura cancelada por el usuario');
    mostrarEstado('error', '⏸️ Lectura cancelada');
    finalizarLectura(false);
}

function finalizarLectura(exito) {
    leyendoHuella = false;
    btnLeerHuella.classList.remove('reading');
    btnText.textContent = '🔍 Leer Huella';
    btnSubmit.disabled = false;
    
    if (exito) {
        huellaInput.readOnly = false;
        huellaInput.focus();
    }
}

function mostrarEstado(tipo, mensaje) {
    fingerprintStatus.className = 'fingerprint-status show ' + tipo;
    fingerprintStatus.textContent = mensaje;
}

// Validación del formulario
document.getElementById('formPaciente').addEventListener('submit', function(e) {
    if (!huellaInput.value) {
        e.preventDefault();
        mostrarEstado('error', '❌ Debe leer la huella antes de guardar');
        return false;
    }
});

// Limpiar estado al hacer cambios manuales en la huella
huellaInput.addEventListener('input', function() {
    if (fingerprintStatus.classList.contains('show')) {
        fingerprintStatus.classList.remove('show');
    }
});

console.log('✅ Sistema de lectura de huellas inicializado');
console.log('📍 ESP32 URL:', ESP32_URL);
</script>

</body>
</html>