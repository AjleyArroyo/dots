<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: index.php");
    exit();
}
include("dbconnection.php");

// Eliminar paciente
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $con->query("DELETE FROM patient WHERE paciente_id = $delete_id");
    header("Location: pacientes.php");
    exit();
}

// Obtener pacientes con contador de alertas
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM alertas_paciente WHERE paciente_id = p.paciente_id AND estado = 'Pendiente') as alertas_pendientes,
        (SELECT COUNT(*) FROM consultas WHERE paciente_id = p.paciente_id AND estado = 'Programada' AND fecha_consulta > NOW()) as consultas_proximas
        FROM patient p 
        ORDER BY p.nombre ASC";
$result = $con->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="300">
  <title>Pacientes - DOTS</title>
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
    .header h1 { color: #667eea; font-size: 32px; }
    .header p { color: #6c757d; font-size: 14px; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn {
      padding: 12px 24px;
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
    .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .btn-secondary { background: white; color: #667eea; border: 2px solid #667eea; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); }
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow-x: auto;
    }
    table { width: 100%; border-collapse: separate; border-spacing: 0; }
    th {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 15px;
      text-align: left;
      font-weight: 600;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    th:first-child { border-radius: 10px 0 0 0; }
    th:last-child { border-radius: 0 10px 0 0; }
    td { padding: 15px; border-bottom: 1px solid #eee; font-size: 14px; color: #333; }
    tr:hover { background-color: #f8f9fa; }
    .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
    .btn-small {
      padding: 6px 12px;
      font-size: 12px;
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
    .btn-view { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
    .btn-edit { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
    .btn-delete { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
    .btn-alerts { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; position: relative; }
    .btn-small:hover { transform: translateY(-2px); box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2); }
    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
    }
    .badge-mejorando { background: #d4edda; color: #155724; }
    .badge-estable { background: #d1ecf1; color: #0c5460; }
    .badge-empeorando { background: #f8d7da; color: #721c24; }
    .badge-nuevo { background: #e3f2fd; color: #1976d2; }
    .alert-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: #dc3545;
      color: white;
      border-radius: 50%;
      width: 18px;
      height: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: 700;
    }
    .consulta-badge {
      background: #28a745;
      color: white;
      padding: 3px 8px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 600;
      margin-left: 5px;
    }
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }
    @media (max-width: 768px) {
      .header { flex-direction: column; align-items: flex-start; }
      .header h1 { font-size: 24px; }
      .actions { width: 100%; flex-direction: column; }
      .btn { width: 100%; justify-content: center; }
      table { font-size: 12px; }
      th, td { padding: 10px 5px; }
      .action-buttons { flex-direction: column; }
      .btn-small { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>

<div class="container">
  <div class="header">
    <div>
      <h1>👥 Gestión de Pacientes</h1>
      <p>Administra la información de todos los pacientes registrados</p>
    </div>
    <div class="actions">
      <a href="dashboard.php" class="btn btn-secondary">⬅️ Volver al Dashboard</a>
      <a href="alertas.php" class="btn btn-secondary">🔔 Ver Todas las Alertas</a>
      <a href="nuevo_paciente.php" class="btn btn-primary">➕ Nuevo Paciente</a>
    </div>
  </div>

  <div class="table-container">
    <?php if ($result->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre Completo</th>
            <th>Estado de Salud</th>
            <th>Peso / Saturación</th>
            <th>Gabinete</th>
            <th>Próximas Consultas</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><span class="badge" style="background: #e3f2fd; color: #1976d2;">#<?= $row['paciente_id'] ?></span></td>
              <td>
                <strong><?= htmlspecialchars($row['nombre'] . ' ' . $row['apellido']) ?></strong>
                <?php if ($row['alertas_pendientes'] > 0): ?>
                  <span class="badge" style="background: #dc3545; color: white;">
                    🔔 <?= $row['alertas_pendientes'] ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                $estado = $row['estado_salud'];
                $badge_class = 'badge-' . strtolower($estado);
                $iconos = [
                    'Mejorando' => '📈',
                    'Estable' => '➡️',
                    'Empeorando' => '📉',
                    'Nuevo' => '🆕'
                ];
                ?>
                <span class="badge <?= $badge_class ?>">
                  <?= $iconos[$estado] ?? '❓' ?> <?= $estado ?>
                </span>
              </td>
              <td>
                <div style="font-size: 13px;">
                  ⚖️ <?= $row['peso_actual'] ? $row['peso_actual'] . ' kg' : 'N/A' ?><br>
                  🫀 <?= $row['saturacion_actual'] ? $row['saturacion_actual'] . '%' : 'N/A' ?>
                </div>
              </td>
              <td>
                <span class="badge" style="background: #e3f2fd; color: #1976d2;">
                  🏢 Gab. <?= $row['gabinete_id'] ?>
                </span>
              </td>
              <td>
                <?php if ($row['consultas_proximas'] > 0): ?>
                  <span class="consulta-badge">
                    📅 <?= $row['consultas_proximas'] ?> programada(s)
                  </span>
                <?php else: ?>
                  <span style="color: #6c757d; font-size: 13px;">Sin consultas</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="action-buttons">
                  <a class="btn-small btn-view" href="ver_paciente.php?id=<?= $row['paciente_id'] ?>">
                    👁️ Ver
                  </a>
                  <a class="btn-small btn-alerts" href="alertas.php?paciente_id=<?= $row['paciente_id'] ?>">
                    🔔 Alertas
                    <?php if ($row['alertas_pendientes'] > 0): ?>
                      <span class="alert-badge"><?= $row['alertas_pendientes'] ?></span>
                    <?php endif; ?>
                  </a>
                  <a class="btn-small btn-edit" href="editar_paciente.php?id=<?= $row['paciente_id'] ?>">
                    ✏️ Editar
                  </a>
                  <a class="btn-small btn-delete" href="pacientes.php?delete_id=<?= $row['paciente_id'] ?>" 
                     onclick="return confirm('¿Seguro que deseas eliminar a <?= htmlspecialchars($row['nombre']) ?>?')">
                    🗑️ Eliminar
                  </a>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state">
        <div style="font-size: 64px; margin-bottom: 20px;">👥</div>
        <h3>No hay pacientes registrados</h3>
        <p style="margin-top: 10px;">Comienza registrando tu primer paciente</p>
        <a href="nuevo_paciente.php" class="btn btn-primary" style="margin-top: 20px;">
          ➕ Registrar Primer Paciente
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>