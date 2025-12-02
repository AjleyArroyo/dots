<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: index.php");
    exit();
}
include("dbconnection.php");

if (!isset($_GET['id'])) {
    echo "Consulta no especificada.";
    exit();
}

$consulta_id = intval($_GET['id']);

// Obtener información de la consulta
$sqlConsulta = "SELECT c.*, 
    p.nombre as paciente_nombre, p.apellido as paciente_apellido, p.paciente_id,
    m.nombre as medico_nombre, m.apellido as medico_apellido, m.tipo_usuario
    FROM consultas c
    JOIN patient p ON c.paciente_id = p.paciente_id
    LEFT JOIN mepersonel m ON c.me_personel_id = m.id
    WHERE c.consulta_id = ?";

$stmt = $con->prepare($sqlConsulta);
$stmt->bind_param("i", $consulta_id);
$stmt->execute();
$resConsulta = $stmt->get_result();

if ($resConsulta->num_rows == 0) {
    echo "Consulta no encontrada.";
    exit();
}

$consulta = $resConsulta->fetch_assoc();

// Obtener recetas asociadas a esta consulta
$sqlRecetas = "SELECT * FROM recetas WHERE consulta_id = ? ORDER BY fecha_emision DESC";
$stmtRecetas = $con->prepare($sqlRecetas);
$stmtRecetas->bind_param("i", $consulta_id);
$stmtRecetas->execute();
$resRecetas = $stmtRecetas->get_result();

// Obtener medición asociada si existe
$sqlMedicion = "SELECT * FROM historial_mediciones WHERE consulta_id = ? LIMIT 1";
$stmtMedicion = $con->prepare($sqlMedicion);
$stmtMedicion->bind_param("i", $consulta_id);
$stmtMedicion->execute();
$resMedicion = $stmtMedicion->get_result();
$medicion = $resMedicion->num_rows > 0 ? $resMedicion->fetch_assoc() : null;

// Si es DotsBox, obtener info del log de dispensación
$logDispensacion = null;
if ($consulta['es_dotsbox'] == 1) {
    $sqlLog = "SELECT * FROM log_dispensaciones WHERE consulta_id = ? LIMIT 1";
    $stmtLog = $con->prepare($sqlLog);
    $stmtLog->bind_param("i", $consulta_id);
    $stmtLog->execute();
    $resLog = $stmtLog->get_result();
    $logDispensacion = $resLog->num_rows > 0 ? $resLog->fetch_assoc() : null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ver Consulta - DOTS</title>
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

    .header h1 { color: #667eea; font-size: 28px; }
    
    .badge-tipo {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 14px;
      font-weight: 600;
    }
    
    .badge-dotsbox {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
    }
    
    .badge-medico {
      background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
      color: white;
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

    .btn-secondary { 
      background: white; 
      color: #667eea; 
      border: 2px solid #667eea; 
    }
    
    .btn:hover { 
      transform: translateY(-2px); 
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); 
    }

    .card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      margin-bottom: 25px;
    }

    .card h2 {
      color: #667eea;
      font-size: 20px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      padding-bottom: 15px;
      border-bottom: 2px solid #e0e0e0;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 25px;
    }

    .info-item {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 15px;
      border-left: 4px solid #667eea;
    }

    .info-label {
      font-size: 12px;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .info-value {
      font-size: 16px;
      color: #333;
      font-weight: 600;
    }

    .mediciones-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 25px;
    }

    .medicion-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 25px;
      border-radius: 15px;
      text-align: center;
    }

    .medicion-value {
      font-size: 48px;
      font-weight: 700;
      margin: 10px 0;
    }

    .medicion-label {
      font-size: 14px;
      opacity: 0.9;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .text-section {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 15px;
      margin-bottom: 20px;
    }

    .text-section h3 {
      color: #667eea;
      font-size: 16px;
      margin-bottom: 12px;
    }

    .text-content {
      color: #333;
      line-height: 1.6;
      white-space: pre-wrap;
    }

    .recetas-list {
      display: grid;
      gap: 15px;
    }

    .receta-item {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 15px;
      border-left: 4px solid #667eea;
    }

    .receta-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .receta-tipo {
      font-weight: 700;
      color: #667eea;
      font-size: 16px;
    }

    .badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
    }

    .badge-pendiente { background: #fff3cd; color: #856404; }
    .badge-entregado { background: #d4edda; color: #155724; }
    .badge-urgente { background: #f8d7da; color: #721c24; }
    .badge-alta { background: #ffe5cc; color: #cc5500; }
    .badge-normal { background: #e3f2fd; color: #1976d2; }

    .estado-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 15px;
      font-weight: 700;
      font-size: 14px;
      margin-bottom: 20px;
    }

    .estado-realizada { background: #d4edda; color: #155724; }
    .estado-programada { background: #d1ecf1; color: #0c5460; }
    .estado-cancelada { background: #f8d7da; color: #721c24; }
    .estado-noasistio { background: #fff3cd; color: #856404; }

    .dispensacion-info {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
      padding: 25px;
      border-radius: 15px;
      margin-bottom: 25px;
    }

    .dispensacion-info h3 {
      font-size: 18px;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .disp-info-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
    }

    .disp-info-item {
      background: rgba(255, 255, 255, 0.2);
      padding: 15px;
      border-radius: 10px;
      text-align: center;
    }

    .disp-info-value {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .disp-info-label {
      font-size: 12px;
      opacity: 0.9;
    }

    .empty-state {
      text-align: center;
      padding: 40px;
      color: #6c757d;
    }

    @media (max-width: 768px) {
      .info-grid, .mediciones-grid, .disp-info-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <div class="header">
    <div>
      <h1>📋 Detalle de Consulta</h1>
      <div style="margin-top: 10px;">
        <?php if ($consulta['es_dotsbox'] == 1): ?>
          <span class="badge-tipo badge-dotsbox">🤖 Consulta DotsBox Automática</span>
        <?php else: ?>
          <span class="badge-tipo badge-medico">👨‍⚕️ Consulta Médica</span>
        <?php endif; ?>
      </div>
    </div>
    <div style="display: flex; gap: 10px;">
      <a href="ver_paciente.php?id=<?= $consulta['paciente_id'] ?>" class="btn btn-secondary">
        ⬅️ Volver al Paciente
      </a>
    </div>
  </div>

  <!-- Información General -->
  <div class="card">
    <h2>📄 Información General</h2>
    
    <div class="estado-badge estado-<?= strtolower($consulta['estado']) ?>">
      <?php
      $iconos_estado = [
        'Realizada' => '✅',
        'Programada' => '📅',
        'Cancelada' => '❌',
        'NoAsistio' => '⚠️'
      ];
      echo ($iconos_estado[$consulta['estado']] ?? '❓') . ' ' . $consulta['estado'];
      ?>
    </div>

    <div class="info-grid">
      <div class="info-item">
        <div class="info-label">👤 Paciente</div>
        <div class="info-value">
          <?= htmlspecialchars($consulta['paciente_nombre'] . ' ' . $consulta['paciente_apellido']) ?>
        </div>
      </div>

      <div class="info-item">
        <div class="info-label">
          <?= $consulta['es_dotsbox'] == 1 ? '🤖 Sistema' : '👨‍⚕️ Médico' ?>
        </div>
        <div class="info-value">
          <?= $consulta['es_dotsbox'] == 1 ? 'DotsBox System' : htmlspecialchars($consulta['medico_nombre'] . ' ' . $consulta['medico_apellido']) ?>
        </div>
      </div>

      <div class="info-item">
        <div class="info-label">📅 Fecha de Consulta</div>
        <div class="info-value">
          <?= date('d/m/Y H:i', strtotime($consulta['fecha_consulta'])) ?>
        </div>
      </div>

      <?php if ($consulta['proxima_consulta']): ?>
      <div class="info-item">
        <div class="info-label">🔜 Próxima Consulta</div>
        <div class="info-value">
          <?= date('d/m/Y H:i', strtotime($consulta['proxima_consulta'])) ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($consulta['tratamiento_finalizado']): ?>
      <div class="info-item" style="background: #d4edda; border-left-color: #28a745;">
        <div class="info-label">✅ Tratamiento</div>
        <div class="info-value" style="color: #155724;">Finalizado</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mediciones -->
  <?php if ($consulta['peso'] || $consulta['saturacion']): ?>
  <div class="card">
    <h2>📊 Mediciones</h2>
    <div class="mediciones-grid">
      <?php if ($consulta['peso']): ?>
      <div class="medicion-card">
        <div class="medicion-label">⚖️ Peso</div>
        <div class="medicion-value"><?= $consulta['peso'] ?></div>
        <div class="medicion-label">kilogramos</div>
      </div>
      <?php endif; ?>

      <?php if ($consulta['saturacion']): ?>
      <div class="medicion-card">
        <div class="medicion-label">🫀 Saturación</div>
        <div class="medicion-value"><?= $consulta['saturacion'] ?>%</div>
        <div class="medicion-label">oxígeno</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Información de Dispensación (solo si es DotsBox) -->
  <?php if ($consulta['es_dotsbox'] == 1 && $logDispensacion): ?>
  <div class="dispensacion-info">
    <h3>💊 Información de Dispensación Automática</h3>
    <div class="disp-info-grid">
      <div class="disp-info-item">
        <div class="disp-info-value">👆 #<?= $logDispensacion['huella_id'] ?></div>
        <div class="disp-info-label">ID Huella</div>
      </div>
      <div class="disp-info-item">
        <div class="disp-info-value">
          <?= $logDispensacion['medicamento_dispensado'] ? '✅' : '❌' ?>
        </div>
        <div class="disp-info-label">Medicamento Dispensado</div>
      </div>
      <div class="disp-info-item">
        <div class="disp-info-value">🕐</div>
        <div class="disp-info-label">
          <?= date('H:i:s', strtotime($logDispensacion['timestamp'])) ?>
        </div>
      </div>
    </div>
    <?php if ($logDispensacion['notas']): ?>
    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.3);">
      <strong>Notas:</strong> <?= htmlspecialchars($logDispensacion['notas']) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Notas y Diagnóstico -->
  <div class="card">
    <h2>📝 Notas Clínicas</h2>

    <?php if ($consulta['notas']): ?>
    <div class="text-section">
      <h3>Notas de la Consulta</h3>
      <div class="text-content"><?= htmlspecialchars($consulta['notas']) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($consulta['diagnostico']): ?>
    <div class="text-section">
      <h3>Diagnóstico</h3>
      <div class="text-content"><?= htmlspecialchars($consulta['diagnostico']) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!$consulta['notas'] && !$consulta['diagnostico']): ?>
    <div class="empty-state">
      <p>No hay notas o diagnóstico registrado para esta consulta</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recetas Asociadas -->
  <div class="card">
    <h2>💊 Recetas y Estudios</h2>

    <?php if ($resRecetas->num_rows > 0): ?>
    <div class="recetas-list">
      <?php while ($receta = $resRecetas->fetch_assoc()): ?>
      <div class="receta-item">
        <div class="receta-header">
          <div>
            <span class="receta-tipo">
              <?php
              $iconos_tipo = [
                'Medicamento' => '💊',
                'Laboratorio' => '🔬',
                'Estudio' => '📋',
                'Otro' => '📌'
              ];
              echo ($iconos_tipo[$receta['tipo']] ?? '📌') . ' ' . $receta['tipo'];
              ?>
            </span>
          </div>
          <div>
            <span class="badge badge-<?= strtolower($receta['estado']) ?>">
              <?= $receta['estado'] ?>
            </span>
            <span class="badge badge-<?= strtolower($receta['prioridad']) ?>">
              <?= $receta['prioridad'] ?>
            </span>
          </div>
        </div>

        <div style="margin: 15px 0;">
          <strong style="color: #667eea;">Descripción:</strong><br>
          <?= htmlspecialchars($receta['descripcion']) ?>
        </div>

        <?php if ($receta['instrucciones']): ?>
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0e0e0;">
          <strong style="color: #667eea;">Instrucciones:</strong><br>
          <?= htmlspecialchars($receta['instrucciones']) ?>
        </div>
        <?php endif; ?>

        <div style="margin-top: 10px; font-size: 12px; color: #6c757d;">
          📅 Emitida: <?= date('d/m/Y H:i', strtotime($receta['fecha_emision'])) ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <p>No hay recetas asociadas a esta consulta</p>
    </div>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
