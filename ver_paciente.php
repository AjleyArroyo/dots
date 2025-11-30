<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: login.php");
    exit();
}
include("dbconnection.php");

if (!isset($_GET['id'])) {
    echo "Paciente no especificado.";
    exit();
}

$paciente_id = intval($_GET['id']);

// Obtener información del paciente
$sqlPaciente = "SELECT * FROM patient WHERE paciente_id = $paciente_id";
$resPaciente = $con->query($sqlPaciente);
if ($resPaciente->num_rows == 0) {
    echo "Paciente no encontrado.";
    exit();
}
$paciente = $resPaciente->fetch_assoc();

// Obtener información del gabinete asignado
$sqlGabinete = "SELECT nombre, sensor_temp_id, sensor_hum_id FROM gabinete WHERE gabinete_id = " . $paciente['gabinete_id'];
$resGabinete = $con->query($sqlGabinete);
$gabinete = $resGabinete->fetch_assoc();

// Obtener dispensaciones del paciente
$sqlDisp = "SELECT d.*, m.nombre AS medicamentonombre, m.dosis_sugerida 
            FROM dispensacionprogramada d 
            JOIN medicine m ON d.medicamento_id = m.medicine_id 
            WHERE d.paciente_id = $paciente_id 
            ORDER BY d.fecha DESC, d.hora DESC
            LIMIT 10";
$resDisp = $con->query($sqlDisp);

// Contar dispensaciones
$sqlStats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Completed' THEN 1 ELSE 0 END) as completadas,
    SUM(CASE WHEN estado = 'Pending' THEN 1 ELSE 0 END) as pendientes
    FROM dispensacionprogramada 
    WHERE paciente_id = $paciente_id";
$resStats = $con->query($sqlStats);
$stats = $resStats->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil del Paciente - DOTS</title>
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
      max-width: 1400px;
      margin: 0 auto;
    }

    /* Header */
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

    .header-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn {
      padding: 12px 20px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      font-size: 14px;
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

    .btn-success {
      background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
      color: white;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    /* Grid Layout */
    .content-grid {
      display: grid;
      grid-template-columns: 1fr 2fr;
      gap: 25px;
      margin-bottom: 25px;
    }

    /* Card Base */
    .card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .card h2 {
      color: #667eea;
      font-size: 20px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* Patient Info Card */
    .patient-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
      color: white;
      margin: 0 auto 20px;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .info-group {
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }

    .info-group:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }

    .info-label {
      font-size: 12px;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 5px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .info-value {
      font-size: 16px;
      color: #333;
      font-weight: 600;
    }

    /* Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      margin-bottom: 25px;
    }

    .stat-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 15px;
      text-align: center;
    }

    .stat-card.success {
      background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }

    .stat-card.warning {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stat-number {
      font-size: 36px;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .stat-label {
      font-size: 12px;
      opacity: 0.9;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Gabinete Info */
    .gabinete-info {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 15px;
      display: flex;
      justify-content: space-around;
      align-items: center;
      margin-bottom: 25px;
    }

    .gabinete-metric {
      text-align: center;
    }

    .gabinete-metric-value {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .gabinete-metric-label {
      font-size: 12px;
      opacity: 0.9;
    }

    /* Table */
    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }

    th {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 12px;
      text-align: left;
      font-weight: 600;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    th:first-child {
      border-radius: 10px 0 0 0;
    }

    th:last-child {
      border-radius: 0 10px 0 0;
    }

    td {
      padding: 12px;
      border-bottom: 1px solid #eee;
      font-size: 14px;
      color: #333;
    }

    tr:hover {
      background-color: #f8f9fa;
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
    }

    .badge-pending {
      background: #fff3cd;
      color: #856404;
    }

    .badge-completed {
      background: #d4edda;
      color: #155724;
    }

    .badge-cancelled {
      background: #f8d7da;
      color: #721c24;
    }

    .action-buttons {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
    }

    .btn-small {
      padding: 5px 10px;
      font-size: 11px;
      border-radius: 6px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      font-weight: 600;
    }

    .btn-edit {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      color: white;
    }

    .btn-delete {
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      color: white;
    }

    .btn-view {
      background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
      color: #333;
    }

    .btn-small:hover {
      transform: translateY(-2px);
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #6c757d;
    }

    @media (max-width: 1024px) {
      .content-grid {
        grid-template-columns: 1fr;
      }

      .stats-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    @media (max-width: 768px) {
      .header {
        flex-direction: column;
        align-items: flex-start;
      }

      .header-actions {
        width: 100%;
        flex-direction: column;
      }

      .btn {
        width: 100%;
        justify-content: center;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }

      .gabinete-info {
        flex-direction: column;
        gap: 15px;
      }

      table {
        font-size: 12px;
      }

      th, td {
        padding: 8px 5px;
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

    .card {
      animation: fadeIn 0.5s ease;
    }
  </style>
</head>
<body>

<div class="container">
  <!-- Header -->
  <div class="header">
    <div class="header-info">
      <h1>👤 Perfil del Paciente</h1>
      <p>Información detallada y historial de tratamientos</p>
    </div>
    <div class="header-actions">
      <a href="pacientes.php" class="btn btn-secondary">
        ⬅️ Volver a Lista
      </a>
      <a href="editar_paciente.php?id=<?= $paciente_id ?>" class="btn btn-primary">
        ✏️ Editar Paciente
      </a>
      <a href="registrar_dispensacion.php?paciente_id=<?= $paciente_id ?>" class="btn btn-success">
        ➕ Nueva Dispensación
      </a>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-number"><?= $stats['total'] ?></div>
      <div class="stat-label">Total Dispensaciones</div>
    </div>
    <div class="stat-card success">
      <div class="stat-number"><?= $stats['completadas'] ?></div>
      <div class="stat-label">Completadas</div>
    </div>
    <div class="stat-card warning">
      <div class="stat-number"><?= $stats['pendientes'] ?></div>
      <div class="stat-label">Pendientes</div>
    </div>
  </div>

  <!-- Content Grid -->
  <div class="content-grid">
    <!-- Patient Info -->
    <div class="card">
      <div class="patient-avatar">
        <?= strtoupper(substr($paciente['nombre'], 0, 1) . substr($paciente['apellido'], 0, 1)) ?>
      </div>
      
      <h2 style="text-align: center; margin-bottom: 30px;">
        <?= htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']) ?>
      </h2>

      <div class="info-group">
        <div class="info-label">📧 Correo Electrónico</div>
        <div class="info-value"><?= htmlspecialchars($paciente['correo']) ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">📱 Teléfono</div>
        <div class="info-value"><?= htmlspecialchars($paciente['telefono']) ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">🏢 Gabinete Asignado</div>
        <div class="info-value">Gabinete #<?= $paciente['gabinete_id'] ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">👆 ID Huella Digital</div>
        <div class="info-value">#<?= $paciente['huella_id'] ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">🚨 Contacto de Emergencia 1</div>
        <div class="info-value"><?= htmlspecialchars($paciente['emergencia1']) ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">🚨 Contacto de Emergencia 2</div>
        <div class="info-value"><?= htmlspecialchars($paciente['emergencia2']) ?></div>
      </div>
    </div>

    <!-- Right Column -->
    <div>
      <!-- Gabinete Info -->
      <?php if ($gabinete): ?>
      <div class="gabinete-info">
        <div class="gabinete-metric">
          <div class="gabinete-metric-value">🏢</div>
          <div class="gabinete-metric-label"><?= htmlspecialchars($gabinete['nombre']) ?></div>
        </div>
        <div class="gabinete-metric">
          <div class="gabinete-metric-value"><?= $gabinete['sensor_temp_id'] ?>°C</div>
          <div class="gabinete-metric-label">Temperatura</div>
        </div>
        <div class="gabinete-metric">
          <div class="gabinete-metric-value"><?= $gabinete['sensor_hum_id'] ?>%</div>
          <div class="gabinete-metric-label">Humedad</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Dispensations History -->
      <div class="card">
        <h2>📋 Historial de Dispensaciones</h2>
        
        <?php if ($resDisp->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Medicamento</th>
              <th>Dosis</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($disp = $resDisp->fetch_assoc()): ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($disp['fecha'])) ?></td>
              <td><?= date('H:i', strtotime($disp['hora'])) ?></td>
              <td><strong><?= htmlspecialchars($disp['medicamentonombre']) ?></strong></td>
              <td><?= htmlspecialchars($disp['dosis_sugerida']) ?></td>
              <td>
                <?php
                $badgeClass = 'badge-pending';
                if ($disp['estado'] == 'Completed') $badgeClass = 'badge-completed';
                if ($disp['estado'] == 'Cancelled') $badgeClass = 'badge-cancelled';
                ?>
                <span class="badge <?= $badgeClass ?>">
                  <?= htmlspecialchars($disp['estado']) ?>
                </span>
              </td>
              <td>
                <div class="action-buttons">
                  <a class="btn-small btn-view" href="ver_prescripcion.php?dispensacion_id=<?= $disp['dispensacion_id'] ?>">
                    📄 Ver
                  </a>
                  <a class="btn-small btn-edit" href="editar_dispensacion.php?id=<?= $disp['dispensacion_id'] ?>">
                    ✏️ Editar
                  </a>
                  <a class="btn-small btn-delete" href="eliminar_dispensacion.php?id=<?= $disp['dispensacion_id'] ?>&paciente_id=<?= $paciente_id ?>" onclick="return confirm('¿Eliminar esta dispensación?')">
                    🗑️
                  </a>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
          <p>📋 No hay dispensaciones registradas</p>
          <a href="registrar_dispensacion.php?paciente_id=<?= $paciente_id ?>" class="btn btn-success" style="margin-top: 15px;">
            ➕ Registrar Primera Dispensación
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

</body>
</html>