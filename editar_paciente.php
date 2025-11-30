<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: login.php");
    exit();
}

include("dbconnection.php");

if (!isset($_GET['id'])) {
    header("Location: pacientes.php");
    exit();
}

$id = intval($_GET['id']);
$mensaje_error = "";
$mensaje_exito = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);
    $gabinete_id = (int)$_POST['gabinete_id'];
    $huella_id = (int)$_POST['huella_id'];
    $emergencia1 = trim($_POST['emergencia1']);
    $emergencia2 = trim($_POST['emergencia2']);

    if (empty($nombre) || empty($apellido) || $gabinete_id == 0 || $huella_id == 0) {
        $mensaje_error = "Por favor completa los campos obligatorios.";
    } else {
        $stmt = $con->prepare("UPDATE patient 
            SET nombre=?, apellido=?, correo=?, telefono=?, gabinete_id=?, huella_id=?, emergencia1=?, emergencia2=? 
            WHERE paciente_id=?");

        $stmt->bind_param("ssssiissi", $nombre, $apellido, $correo, $telefono, $gabinete_id, $huella_id, $emergencia1, $emergencia2, $id);
        
        if ($stmt->execute()) {
            $mensaje_exito = "Paciente actualizado correctamente.";
            // Recargar datos
            $res = $con->query("SELECT * FROM patient WHERE paciente_id = $id");
            $p = $res->fetch_assoc();
        } else {
            if ($stmt->errno == 1062 && strpos($stmt->error, 'huella_id') !== false) {
                $mensaje_error = "Esta huella ya está asignada a otro paciente.";
            } else {
                $mensaje_error = "Error al actualizar: " . $stmt->error;
            }
        }
        $stmt->close();
    }
} else {
    $res = $con->query("SELECT * FROM patient WHERE paciente_id = $id");
    if ($res->num_rows == 0) {
        header("Location: pacientes.php");
        exit();
    }
    $p = $res->fetch_assoc();
}

// Obtener lista de gabinetes
$gabinetes = $con->query("SELECT gabinete_id, nombre FROM gabinete ORDER BY gabinete_id ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Paciente - DOTS</title>
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
      max-width: 900px;
      margin: 0 auto;
    }

    .header {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 25px 30px;
      border-radius: 20px;
      margin-bottom: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }

    .header-info h1 {
      color: #667eea;
      font-size: 28px;
      margin-bottom: 5px;
    }

    .header-info p {
      color: #6c757d;
      font-size: 14px;
    }

    .patient-badge {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 10px 20px;
      border-radius: 10px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
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
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
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
    }

    .form-section h3 {
      color: #667eea;
      font-size: 18px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      padding-bottom: 10px;
      border-bottom: 2px solid #e0e0e0;
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

    .helper-text {
      font-size: 12px;
      color: #6c757d;
      margin-top: 5px;
    }

    .input-with-icon {
      position: relative;
    }

    .input-icon {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #667eea;
      font-size: 18px;
    }

    .fingerprint-info {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 10px;
      border-left: 4px solid #667eea;
      margin-top: 10px;
      font-size: 13px;
      color: #6c757d;
    }

    .button-group {
      display: flex;
      gap: 15px;
      margin-top: 30px;
      padding-top: 30px;
      border-top: 2px solid #e0e0e0;
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

    .btn-danger {
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      color: white;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .danger-zone {
      background: #fff5f5;
      border: 2px solid #fc8181;
      border-radius: 15px;
      padding: 20px;
      margin-top: 30px;
    }

    .danger-zone h3 {
      color: #c41e3a;
      font-size: 16px;
      margin-bottom: 10px;
    }

    .danger-zone p {
      color: #6c757d;
      font-size: 14px;
      margin-bottom: 15px;
    }

    @media (max-width: 768px) {
      .header {
        flex-direction: column;
        align-items: flex-start;
      }

      .patient-badge {
        width: 100%;
        justify-content: center;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }

      .button-group {
        flex-direction: column;
      }
    }

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
    <div class="header-info">
      <h1>✏️ Editar Paciente</h1>
      <p>Actualiza la información del paciente</p>
    </div>
    <div class="patient-badge">
      👤 <?= htmlspecialchars($p['nombre'] . ' ' . $p['apellido']) ?>
    </div>
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

    <form method="POST">
      <!-- Información Personal -->
      <div class="form-section">
        <h3>👤 Información Personal</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>
              Nombre <span class="required">*</span>
            </label>
            <input type="text" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>" required>
          </div>

          <div class="form-group">
            <label>
              Apellido <span class="required">*</span>
            </label>
            <input type="text" name="apellido" value="<?= htmlspecialchars($p['apellido']) ?>" required>
          </div>

          <div class="form-group">
            <label>📧 Correo Electrónico</label>
            <input type="email" name="correo" value="<?= htmlspecialchars($p['correo']) ?>">
            <div class="helper-text">Para notificaciones y comunicación</div>
          </div>

          <div class="form-group">
            <label>📱 Teléfono</label>
            <input type="tel" name="telefono" value="<?= htmlspecialchars($p['telefono']) ?>">
            <div class="helper-text">Número de contacto principal</div>
          </div>
        </div>
      </div>

      <!-- Asignaciones -->
      <div class="form-section">
        <h3>🏥 Asignaciones del Sistema</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>
              🏢 Gabinete Asignado <span class="required">*</span>
            </label>
            <select name="gabinete_id" required>
              <option value="">Seleccionar gabinete...</option>
              <?php
              $gabinetes->data_seek(0); // Reset pointer
              while($gab = $gabinetes->fetch_assoc()):
              ?>
                <option value="<?= $gab['gabinete_id'] ?>" 
                  <?= $gab['gabinete_id'] == $p['gabinete_id'] ? 'selected' : '' ?>>
                  Gabinete <?= $gab['gabinete_id'] ?> - <?= htmlspecialchars($gab['nombre']) ?>
                </option>
              <?php endwhile; ?>
            </select>
            <div class="helper-text">Ubicación del tratamiento del paciente</div>
          </div>

          <div class="form-group">
            <label>
              👆 ID Huella Digital <span class="required">*</span>
            </label>
            <input type="number" name="huella_id" value="<?= htmlspecialchars($p['huella_id']) ?>" required>
            <div class="fingerprint-info">
              ⚠️ <strong>Importante:</strong> Solo modifica si necesitas reasignar la huella. 
              Cambiar este valor requiere volver a registrar la huella en el sensor.
            </div>
          </div>
        </div>
      </div>

      <!-- Contactos de Emergencia -->
      <div class="form-section">
        <h3>🚨 Contactos de Emergencia</h3>
        <div class="form-grid">
          <div class="form-group full-width">
            <label>Contacto de Emergencia 1</label>
            <input type="text" name="emergencia1" value="<?= htmlspecialchars($p['emergencia1']) ?>" placeholder="Nombre y teléfono">
            <div class="helper-text">Ej: María López - 555-1234</div>
          </div>

          <div class="form-group full-width">
            <label>Contacto de Emergencia 2</label>
            <input type="text" name="emergencia2" value="<?= htmlspecialchars($p['emergencia2']) ?>" placeholder="Nombre y teléfono">
            <div class="helper-text">Ej: Juan Pérez - 555-5678</div>
          </div>
        </div>
      </div>

      <!-- Botones de Acción -->
      <div class="button-group">
        <a href="ver_paciente.php?id=<?= $id ?>" class="btn btn-secondary">
          ❌ Cancelar
        </a>
        <button type="submit" class="btn btn-primary">
          ✅ Guardar Cambios
        </button>
      </div>
    </form>

    <!-- Zona de Peligro -->
    <div class="danger-zone">
      <h3>⚠️ Zona de Peligro</h3>
      <p>Eliminar este paciente removerá permanentemente toda su información y historial de dispensaciones.</p>
      <a href="pacientes.php?delete_id=<?= $id ?>" 
         class="btn btn-danger" 
         onclick="return confirm('¿Estás completamente seguro de eliminar a <?= htmlspecialchars($p['nombre']) ?>? Esta acción NO se puede deshacer.')"
         style="max-width: 300px;">
        🗑️ Eliminar Paciente
      </a>
    </div>
  </div>
</div>

<script>
// Confirmación al salir sin guardar
let formModified = false;
const form = document.querySelector('form');
const inputs = form.querySelectorAll('input, select, textarea');

inputs.forEach(input => {
  input.addEventListener('change', () => {
    formModified = true;
  });
});

window.addEventListener('beforeunload', (e) => {
  if (formModified) {
    e.preventDefault();
    e.returnValue = '';
  }
});

form.addEventListener('submit', () => {
  formModified = false;
});

// Auto-ocultar mensajes de éxito después de 5 segundos
const successAlert = document.querySelector('.alert-success');
if (successAlert) {
  setTimeout(() => {
    successAlert.style.transition = 'all 0.3s ease';
    successAlert.style.opacity = '0';
    successAlert.style.transform = 'translateY(-10px)';
    setTimeout(() => successAlert.remove(), 300);
  }, 5000);
}
</script>

</body>
</html>