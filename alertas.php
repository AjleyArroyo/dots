<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: login.php");
    exit();
}
include("dbconnection.php");

// Marcar alerta como leída
if (isset($_GET['leer']) && isset($_GET['alerta_id'])) {
    $alerta_id = intval($_GET['alerta_id']);
    $con->query("UPDATE alertas_paciente SET estado = 'Leida' WHERE alerta_id = $alerta_id");
    header("Location: alertas.php");
    exit();
}

// Obtener estadísticas
$sqlStats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'Enviada' THEN 1 ELSE 0 END) as enviadas,
    SUM(CASE WHEN prioridad = 'Urgente' THEN 1 ELSE 0 END) as urgentes
    FROM alertas_paciente 
    WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$resStats = $con->query($sqlStats);
$stats = $resStats->fetch_assoc();

// Filtros
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$filtro_paciente = isset($_GET['paciente_id']) ? intval($_GET['paciente_id']) : 0;

// Obtener info del paciente si hay filtro
$paciente_filtrado = null;
if ($filtro_paciente > 0) {
    $sqlPac = "SELECT nombre, apellido FROM patient WHERE paciente_id = $filtro_paciente";
    $resPac = $con->query($sqlPac);
    if ($resPac && $resPac->num_rows > 0) {
        $paciente_filtrado = $resPac->fetch_assoc();
    }
}

// Query principal
$where = ["1=1"];
if ($filtro_tipo !== 'todos') {
    $where[] = "a.tipo = '" . $con->real_escape_string($filtro_tipo) . "'";
}
if ($filtro_estado !== 'todos') {
    $where[] = "a.estado = '" . $con->real_escape_string($filtro_estado) . "'";
}
if ($filtro_paciente > 0) {
    $where[] = "a.paciente_id = $filtro_paciente";
}

$sqlAlertas = "SELECT a.*, p.nombre, p.apellido 
               FROM alertas_paciente a 
               JOIN patient p ON a.paciente_id = p.paciente_id 
               WHERE " . implode(" AND ", $where) . " 
               ORDER BY 
                   CASE a.prioridad 
                       WHEN 'Urgente' THEN 1 
                       WHEN 'Alta' THEN 2 
                       WHEN 'Media' THEN 3 
                       ELSE 4 
                   END,
                   a.created_at DESC 
               LIMIT 100";
$resAlertas = $con->query($sqlAlertas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="60">
  <title>Alertas - DOTS</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }
    .container { max-width: 1400px; margin: 0 auto; }
    .header {
      background: rgba(255, 255, 255, 0.95);
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
    .header h1 { color: #667eea; font-size: 32px; }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 30px;
    }
    .stat-card {
      background: rgba(255, 255, 255, 0.95);
      padding: 25px;
      border-radius: 15px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
    }
    .stat-number { font-size: 36px; font-weight: 700; color: #667eea; margin-bottom: 5px; }
    .stat-label { font-size: 13px; color: #6c757d; text-transform: uppercase; }
    .filters {
      background: rgba(255, 255, 255, 0.95);
      padding: 20px 30px;
      border-radius: 20px;
      margin-bottom: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
    }
    .filter-group { display: flex; flex-direction: column; }
    .filter-group label { font-size: 13px; color: #6c757d; margin-bottom: 5px; font-weight: 600; }
    .filter-group select {
      padding: 10px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 14px;
    }
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .btn-secondary { background: white; color: #667eea; border: 2px solid #667eea; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); }
    .alerts-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    .alert-item {
      padding: 20px;
      border-radius: 15px;
      margin-bottom: 15px;
      display: flex;
      gap: 20px;
      align-items: flex-start;
      transition: all 0.3s ease;
      border-left: 4px solid;
    }
    .alert-item:hover { transform: translateX(5px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
    .alert-urgente { background: #fff5f5; border-left-color: #dc3545; }
    .alert-alta { background: #fff3cd; border-left-color: #ffc107; }
    .alert-media { background: #e3f2fd; border-left-color: #2196f3; }
    .alert-baja { background: #f8f9fa; border-left-color: #6c757d; }
    .alert-icon {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      flex-shrink: 0;
    }
    .alert-urgente .alert-icon { background: #dc3545; }
    .alert-alta .alert-icon { background: #ffc107; }
    .alert-media .alert-icon { background: #2196f3; }
    .alert-baja .alert-icon { background: #6c757d; }
    .alert-content { flex: 1; }
    .alert-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }
    .alert-title { font-size: 16px; font-weight: 700; color: #333; }
    .alert-time { font-size: 12px; color: #6c757d; }
    .alert-message { font-size: 14px; color: #555; margin-bottom: 10px; }
    .alert-meta {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      font-size: 13px;
      color: #6c757d;
    }
    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
    }
    .badge-pendiente { background: #fff3cd; color: #856404; }
    .badge-enviada { background: #d4edda; color: #155724; }
    .badge-leida { background: #cfe2ff; color: #084298; }
    .alert-actions {
      display: flex;
      gap: 10px;
      margin-top: 10px;
    }
    .btn-small {
      padding: 5px 12px;
      font-size: 12px;
      border-radius: 6px;
    }
    @media (max-width: 768px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .alert-item { flex-direction: column; }
    }
  </style>
</head>
<body>

<div class="container">
  <div class="header">
    <div>
      <h1>🔔 Centro de Alertas</h1>
      <?php if ($paciente_filtrado): ?>
        <p style="color: #6c757d; font-size: 14px;">
          Mostrando alertas de: <strong style="color: #667eea;"><?= htmlspecialchars($paciente_filtrado['nombre'] . ' ' . $paciente_filtrado['apellido']) ?></strong>
          <a href="alertas.php" style="color: #667eea; text-decoration: none; margin-left: 10px;">(Ver todas)</a>
        </p>
      <?php else: ?>
        <p style="color: #6c757d; font-size: 14px;">Gestión de notificaciones y seguimiento de pacientes</p>
      <?php endif; ?>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">⬅️ Volver al Dashboard</a>
  </div>

  <!-- Estadísticas -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-number"><?= $stats['total'] ?></div>
      <div class="stat-label">Total (7 días)</div>
    </div>
    <div class="stat-card">
      <div class="stat-number" style="color: #ffc107;"><?= $stats['pendientes'] ?></div>
      <div class="stat-label">Pendientes</div>
    </div>
    <div class="stat-card">
      <div class="stat-number" style="color: #28a745;"><?= $stats['enviadas'] ?></div>
      <div class="stat-label">Enviadas</div>
    </div>
    <div class="stat-card">
      <div class="stat-number" style="color: #dc3545;"><?= $stats['urgentes'] ?></div>
      <div class="stat-label">Urgentes</div>
    </div>
  </div>

  <!-- Filtros -->
  <form method="GET" class="filters">
    <?php if ($filtro_paciente > 0): ?>
      <input type="hidden" name="paciente_id" value="<?= $filtro_paciente ?>">
    <?php endif; ?>
    
    <div class="filter-group">
      <label>Tipo de Alerta</label>
      <select name="tipo" onchange="this.form.submit()">
        <option value="todos" <?= $filtro_tipo === 'todos' ? 'selected' : '' ?>>Todos</option>
        <option value="ConsultaProxima" <?= $filtro_tipo === 'ConsultaProxima' ? 'selected' : '' ?>>Consulta Próxima</option>
        <option value="ConsultaPerdida" <?= $filtro_tipo === 'ConsultaPerdida' ? 'selected' : '' ?>>Consulta Perdida</option>
        <option value="RecetaPendiente" <?= $filtro_tipo === 'RecetaPendiente' ? 'selected' : '' ?>>Receta Pendiente</option>
        <option value="LaboratorioPendiente" <?= $filtro_tipo === 'LaboratorioPendiente' ? 'selected' : '' ?>>Laboratorio</option>
      </select>
    </div>
    <div class="filter-group">
      <label>Estado</label>
      <select name="estado" onchange="this.form.submit()">
        <option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
        <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
        <option value="Enviada" <?= $filtro_estado === 'Enviada' ? 'selected' : '' ?>>Enviada</option>
        <option value="Leida" <?= $filtro_estado === 'Leida' ? 'selected' : '' ?>>Leída</option>
      </select>
    </div>
    <?php if ($filtro_tipo !== 'todos' || $filtro_estado !== 'todos' || $filtro_paciente > 0): ?>
      <div class="filter-group">
        <label>&nbsp;</label>
        <a href="alertas.php" class="btn btn-secondary">🔄 Limpiar Filtros</a>
      </div>
    <?php endif; ?>
  </form>

  <!-- Lista de Alertas -->
  <div class="alerts-container">
    <?php if ($resAlertas->num_rows > 0): ?>
      <?php while ($alerta = $resAlertas->fetch_assoc()): ?>
        <div class="alert-item alert-<?= strtolower($alerta['prioridad']) ?>">
          <div class="alert-icon">
            <?php
            $iconos = [
                'ConsultaProxima' => '📅',
                'ConsultaPerdida' => '⚠️',
                'RecetaPendiente' => '💊',
                'LaboratorioPendiente' => '🔬',
                'Seguimiento' => '📋',
                'Urgente' => '🚨'
            ];
            echo $iconos[$alerta['tipo']] ?? '🔔';
            ?>
          </div>
          <div class="alert-content">
            <div class="alert-header">
              <div class="alert-title"><?= htmlspecialchars($alerta['titulo']) ?></div>
              <div class="alert-time"><?= date('d/m/Y H:i', strtotime($alerta['created_at'])) ?></div>
            </div>
            <div class="alert-message"><?= htmlspecialchars($alerta['mensaje']) ?></div>
            <div class="alert-meta">
              <span>👤 <?= htmlspecialchars($alerta['nombre'] . ' ' . $alerta['apellido']) ?></span>
              <span>📌 <?= $alerta['tipo'] ?></span>
              <span class="badge badge-<?= strtolower($alerta['estado']) ?>"><?= $alerta['estado'] ?></span>
              <span style="color: <?= $alerta['prioridad'] === 'Urgente' ? '#dc3545' : ($alerta['prioridad'] === 'Alta' ? '#ffc107' : '#6c757d') ?>;">
                🔥 <?= $alerta['prioridad'] ?>
              </span>
            </div>
            <div class="alert-actions">
              <a href="ver_paciente.php?id=<?= $alerta['paciente_id'] ?>" class="btn btn-primary btn-small">Ver Paciente</a>
              <?php if ($alerta['estado'] !== 'Leida'): ?>
                <a href="alertas.php?leer=1&alerta_id=<?= $alerta['alerta_id'] ?>" class="btn btn-secondary btn-small">✅ Marcar como Leída</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
        <div style="font-size: 64px; margin-bottom: 20px;">🎉</div>
        <h3>No hay alertas que mostrar</h3>
        <p style="margin-top: 10px;">Todas las notificaciones están al día</p>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>