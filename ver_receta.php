<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: index.php");
    exit();
}
include("dbconnection.php");

if (!isset($_GET['id'])) {
    echo "Receta no especificada.";
    exit();
}

$receta_id = intval($_GET['id']);

// Obtener información de la receta
$sqlReceta = "SELECT r.*,
    p.nombre as paciente_nombre, p.apellido as paciente_apellido, p.paciente_id,
    m.nombre as medico_nombre, m.apellido as medico_apellido,
    c.fecha_consulta
    FROM recetas r
    JOIN patient p ON r.paciente_id = p.paciente_id
    LEFT JOIN mepersonel m ON r.me_personel_id = m.id
    LEFT JOIN consultas c ON r.consulta_id = c.consulta_id
    WHERE r.receta_id = ?";

$stmt = $con->prepare($sqlReceta);
$stmt->bind_param("i", $receta_id);
$stmt->execute();
$resReceta = $stmt->get_result();

if ($resReceta->num_rows == 0) {
    echo "Receta no encontrada.";
    exit();
}

$receta = $resReceta->fetch_assoc();

// Manejar acciones (cambiar estado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $nuevo_estado = $_POST['nuevo_estado'];
    $sqlUpdate = "UPDATE recetas SET estado = ?, fecha_entrega = NOW() WHERE receta_id = ?";
    $stmtUpdate = $con->prepare($sqlUpdate);
    $stmtUpdate->bind_param("si", $nuevo_estado, $receta_id);
    $stmtUpdate->execute();
    $stmtUpdate->close();
    
    // Recargar página
    header("Location: ver_receta.php?id=$receta_id&actualizado=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ver Receta - DOTS</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }

    .container { max-width: 900px; margin: 0 auto; }

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

    .header h1 { 
      color: #667eea; 
      font-size: 28px;
      display: flex;
      align-items: center;
      gap: 10px;
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
    
    .btn-success {
      background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
      color: white;
    }
    
    .btn-warning {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
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

    .receta-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px;
      border-radius: 15px;
      margin-bottom: 30px;
    }

    .receta-tipo {
      font-size: 48px;
      margin-bottom: 15px;
    }

    .receta-titulo {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .receta-subtitulo {
      font-size: 16px;
      opacity: 0.9;
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

    .badge {
      display: inline-block;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 700;
    }

    .badge-pendiente { background: #fff3cd; color: #856404; }
    .badge-entregado { background: #d4edda; color: #155724; }
    .badge-recogido { background: #d1ecf1; color: #0c5460; }
    .badge-completado { background: #cfe2ff; color: #084298; }
    .badge-cancelado { background: #f8d7da; color: #721c24; }
    
    .badge-urgente { background: #dc3545; color: white; }
    .badge-alta { background: #ffc107; color: #333; }
    .badge-normal { background: #6c757d; color: white; }

    .descripcion-box {
      background: #f8f9fa;
      padding: 25px;
      border-radius: 15px;
      margin-bottom: 25px;
      border-left: 4px solid #667eea;
    }

    .descripcion-box h3 {
      color: #667eea;
      font-size: 16px;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .descripcion-text {
      color: #333;
      line-height: 1.8;
      font-size: 15px;
      white-space: pre-wrap;
    }

    .actions-card {
      background: #f8f9fa;
      padding: 25px;
      border-radius: 15px;
      border: 2px dashed #667eea;
    }

    .actions-card h3 {
      color: #667eea;
      font-size: 18px;
      margin-bottom: 20px;
    }

    .action-buttons {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
    }

    .timeline {
      position: relative;
      padding-left: 30px;
    }

    .timeline::before {
      content: '';
      position: absolute;
      left: 10px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: #e0e0e0;
    }

    .timeline-item {
      position: relative;
      padding-bottom: 25px;
    }

    .timeline-item::before {
      content: '';
      position: absolute;
      left: -24px;
      top: 5px;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: #667eea;
      border: 3px solid white;
      box-shadow: 0 0 0 2px #667eea;
    }

    .timeline-date {
      font-size: 12px;
      color: #6c757d;
      font-weight: 600;
      margin-bottom: 5px;
    }

    .timeline-content {
      color: #333;
      font-size: 14px;
    }

    .alert {
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
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

    .alert-success {
      background: #d4edda;
      color: #155724;
      border-left: 4px solid #28a745;
    }

    @media (max-width: 768px) {
      .info-grid, .action-buttons {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <?php if (isset($_GET['actualizado'])): ?>
    <div class="alert alert-success">
      ✅ Estado de la receta actualizado correctamente
    </div>
  <?php endif; ?>

  <div class="header">
    <h1>
      <?php
      $iconos_tipo = [
        'Medicamento' => '💊',
        'Laboratorio' => '🔬',
        'Estudio' => '📋',
        'Otro' => '📌'
      ];
      echo $iconos_tipo[$receta['tipo']] ?? '📌';
      ?> 
      <?= $receta['tipo'] ?>
    </h1>
    <div style="display: flex; gap: 10px;">
      <a href="ver_paciente.php?id=<?= $receta['paciente_id'] ?>" class="btn btn-secondary">
        ⬅️ Volver al Paciente
      </a>
    </div>
  </div>

  <!-- Header de Receta -->
  <div class="receta-header">
    <div class="receta-tipo">
      <?= $iconos_tipo[$receta['tipo']] ?? '📌' ?>
    </div>
    <div class="receta-titulo"><?= htmlspecialchars($receta['descripcion']) ?></div>
    <div class="receta-subtitulo">
      Receta ID: #<?= $receta['receta_id'] ?> | 
      Estado: 
      <span class="badge badge-<?= strtolower($receta['estado']) ?>">
        <?= $receta['estado'] ?>
      </span>
      <span class="badge badge-<?= strtolower($receta['prioridad']) ?>">
        <?= $receta['prioridad'] ?>
      </span>
    </div>
  </div>

  <!-- Información General -->
  <div class="card">
    <h2>📄 Información General</h2>

    <div class="info-grid">
      <div class="info-item">
        <div class="info-label">👤 Paciente</div>
        <div class="info-value">
          <?= htmlspecialchars($receta['paciente_nombre'] . ' ' . $receta['paciente_apellido']) ?>
        </div>
      </div>

      <div class="info-item">
        <div class="info-label">👨‍⚕️ Médico Prescriptor</div>
        <div class="info-value">
          <?= htmlspecialchars($receta['medico_nombre'] . ' ' . $receta['medico_apellido']) ?>
        </div>
      </div>

      <div class="info-item">
        <div class="info-label">📅 Fecha de Emisión</div>
        <div class="info-value">
          <?= date('d/m/Y H:i', strtotime($receta['fecha_emision'])) ?>
        </div>
      </div>

      <?php if ($receta['fecha_entrega']): ?>
      <div class="info-item">
        <div class="info-label">📦 Fecha de Entrega</div>
        <div class="info-value">
          <?= date('d/m/Y H:i', strtotime($receta['fecha_entrega'])) ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($receta['consulta_id']): ?>
      <div class="info-item">
        <div class="info-label">🔗 Consulta Asociada</div>
        <div class="info-value">
          <a href="ver_consulta.php?id=<?= $receta['consulta_id'] ?>" style="color: #667eea; text-decoration: none;">
            Consulta del <?= date('d/m/Y', strtotime($receta['fecha_consulta'])) ?>
          </a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Detalles -->
  <div class="card">
    <h2>📝 Detalles</h2>

    <div class="descripcion-box">
      <h3>📋 Descripción</h3>
      <div class="descripcion-text"><?= htmlspecialchars($receta['descripcion']) ?></div>
    </div>

    <?php if ($receta['instrucciones']): ?>
    <div class="descripcion-box">
      <h3>💡 Instrucciones</h3>
      <div class="descripcion-text"><?= htmlspecialchars($receta['instrucciones']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Timeline -->
  <div class="card">
    <h2>📅 Historial</h2>
    
    <div class="timeline">
      <div class="timeline-item">
        <div class="timeline-date">
          <?= date('d/m/Y H:i', strtotime($receta['fecha_emision'])) ?>
        </div>
        <div class="timeline-content">
          <strong>Receta emitida</strong><br>
          Por: Dr. <?= htmlspecialchars($receta['medico_nombre']) ?>
        </div>
      </div>

      <?php if ($receta['fecha_entrega']): ?>
      <div class="timeline-item">
        <div class="timeline-date">
          <?= date('d/m/Y H:i', strtotime($receta['fecha_entrega'])) ?>
        </div>
        <div class="timeline-content">
          <strong>Estado actualizado</strong><br>
          Nuevo estado: <?= $receta['estado'] ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Acciones -->
  <?php if ($receta['estado'] !== 'Completado' && $receta['estado'] !== 'Cancelado'): ?>
  <div class="card">
    <div class="actions-card">
      <h3>⚡ Acciones Rápidas</h3>
      <form method="POST">
        <input type="hidden" name="accion" value="cambiar_estado">
        <div class="action-buttons">
          <?php if ($receta['estado'] === 'Pendiente'): ?>
            <button type="submit" name="nuevo_estado" value="Entregado" class="btn btn-success">
              📦 Marcar como Entregado
            </button>
          <?php endif; ?>

          <?php if ($receta['estado'] === 'Entregado'): ?>
            <button type="submit" name="nuevo_estado" value="Recogido" class="btn btn-success">
              ✅ Marcar como Recogido
            </button>
          <?php endif; ?>

          <?php if ($receta['estado'] === 'Recogido'): ?>
            <button type="submit" name="nuevo_estado" value="Completado" class="btn btn-success">
              🎉 Marcar como Completado
            </button>
          <?php endif; ?>

          <button type="submit" name="nuevo_estado" value="Cancelado" class="btn btn-warning" 
                  onclick="return confirm('¿Está seguro de cancelar esta receta?')">
            ❌ Cancelar Receta
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>

</body>
</html>
