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

// Obtener gabinete
$sqlGabinete = "SELECT nombre, sensor_temp_id, sensor_hum_id FROM gabinete WHERE gabinete_id = " . $paciente['gabinete_id'];
$resGabinete = $con->query($sqlGabinete);
$gabinete = $resGabinete->fetch_assoc();

// Obtener historial de mediciones para gráficas
$sqlMediciones = "SELECT peso, saturacion, DATE_FORMAT(fecha_medicion, '%d/%m') as fecha 
                  FROM historial_mediciones 
                  WHERE paciente_id = $paciente_id 
                  ORDER BY fecha_medicion ASC 
                  LIMIT 20";
$resMediciones = $con->query($sqlMediciones);
$mediciones = [];
while($m = $resMediciones->fetch_assoc()) {
    $mediciones[] = $m;
}

// Obtener consultas
$sqlConsultas = "SELECT c.*, m.nombre as medico_nombre 
                 FROM consultas c 
                 LEFT JOIN mepersonel m ON c.me_personel_id = m.id 
                 WHERE c.paciente_id = $paciente_id 
                 ORDER BY c.fecha_consulta DESC 
                 LIMIT 10";
$resConsultas = $con->query($sqlConsultas);

// Obtener recetas pendientes
$sqlRecetas = "SELECT r.*, m.nombre as medico_nombre 
               FROM recetas r 
               LEFT JOIN mepersonel m ON r.me_personel_id = m.id 
               WHERE r.paciente_id = $paciente_id 
               ORDER BY r.fecha_emision DESC 
               LIMIT 10";
$resRecetas = $con->query($sqlRecetas);

// Obtener alertas
$sqlAlertas = "SELECT * FROM alertas_paciente 
               WHERE paciente_id = $paciente_id 
               ORDER BY created_at DESC 
               LIMIT 10";
$resAlertas = $con->query($sqlAlertas);

// **NUEVO: Obtener estadísticas de dispensaciones**
$sqlDispensaciones = "SELECT 
    COUNT(DISTINCT c.consulta_id) as total_consultas,
    COUNT(DISTINCT CASE WHEN c.es_dotsbox = 1 THEN c.consulta_id END) as dispensaciones_dotsbox,
    COUNT(DISTINCT CASE WHEN c.es_dotsbox = 0 THEN c.consulta_id END) as consultas_medico,
    COUNT(DISTINCT ld.dispensacion_id) as total_dispensaciones_log,
    MAX(ld.timestamp) as ultima_dispensacion
    FROM patient p
    LEFT JOIN consultas c ON p.paciente_id = c.paciente_id
    LEFT JOIN log_dispensaciones ld ON p.paciente_id = ld.paciente_id
    WHERE p.paciente_id = $paciente_id
    GROUP BY p.paciente_id";
$resDisp = $con->query($sqlDispensaciones);
$dispensaciones = $resDisp->fetch_assoc();

// Preparar datos para gráficas
$labels = [];
$pesos = [];
$saturaciones = [];
foreach($mediciones as $med) {
    $labels[] = $med['fecha'];
    $pesos[] = $med['peso'];
    $saturaciones[] = $med['saturacion'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="300">
  <title>Perfil del Paciente - DOTS</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }

    .container { max-width: 1600px; margin: 0 auto; }

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
    .header p { color: #6c757d; font-size: 14px; }

    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }

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

    .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .btn-secondary { background: white; color: #667eea; border: 2px solid #667eea; }
    .btn-success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
    .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); }

    .content-grid {
      display: grid;
      grid-template-columns: 350px 1fr;
      gap: 25px;
      margin-bottom: 25px;
    }

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

    .health-indicator {
      text-align: center;
      padding: 15px;
      border-radius: 15px;
      margin-bottom: 20px;
      font-weight: 700;
      font-size: 16px;
    }

    .health-mejorando { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
    .health-estable { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
    .health-empeorando { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
    .health-nuevo { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }

    .info-group {
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }

    .info-group:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

    .info-label {
      font-size: 12px;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 5px;
    }

    .info-value { font-size: 16px; color: #333; font-weight: 600; }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
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

    .stat-number { font-size: 36px; font-weight: 700; margin-bottom: 5px; }
    .stat-label { font-size: 12px; opacity: 0.9; text-transform: uppercase; }

    /* NUEVO: Estilos para contador de dispensaciones */
    .dispensaciones-card {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
      padding: 25px;
      border-radius: 20px;
      margin-bottom: 25px;
      box-shadow: 0 10px 30px rgba(240, 147, 251, 0.4);
    }

    .dispensaciones-card h3 {
      font-size: 18px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      opacity: 0.95;
    }

    .disp-stats-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
    }

    .disp-stat-item {
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      padding: 15px;
      border-radius: 12px;
      text-align: center;
    }

    .disp-stat-number {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .disp-stat-label {
      font-size: 11px;
      opacity: 0.9;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .ultima-dispensacion {
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid rgba(255, 255, 255, 0.3);
      font-size: 13px;
      text-align: center;
      opacity: 0.9;
    }

    .chart-container {
      position: relative;
      height: 300px;
      background: white;
      border-radius: 10px;
      padding: 15px;
    }

    .tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
      border-bottom: 2px solid #e0e0e0;
    }

    .tab {
      padding: 12px 20px;
      background: transparent;
      border: none;
      border-bottom: 3px solid transparent;
      cursor: pointer;
      font-weight: 600;
      color: #6c757d;
      transition: all 0.3s ease;
    }

    .tab.active {
      color: #667eea;
      border-bottom-color: #667eea;
    }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th {
      background: #f8f9fa;
      padding: 12px;
      text-align: left;
      font-weight: 600;
      font-size: 13px;
      color: #667eea;
      border-bottom: 2px solid #e0e0e0;
    }

    td {
      padding: 12px;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }

    tr:hover { background: #f8f9fa; }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
    }

    .badge-pendiente { background: #fff3cd; color: #856404; }
    .badge-realizada { background: #d4edda; color: #155724; }
    .badge-cancelada { background: #f8d7da; color: #721c24; }
    .badge-noasistio { background: #f8d7da; color: #721c24; }
    .badge-dotsbox { background: #e3f2fd; color: #1976d2; }

    @media (max-width: 1200px) {
      .content-grid { grid-template-columns: 1fr; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
      .stats-grid { grid-template-columns: 1fr; }
      .disp-stats-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="container">
  <div class="header">
    <div>
      <h1>👤 Perfil del Paciente</h1>
      <p>Información detallada, historial médico y seguimiento</p>
    </div>
    <div class="header-actions">
      <a href="pacientes.php" class="btn btn-secondary">⬅️ Volver</a>
      <a href="alertas.php?paciente_id=<?= $paciente_id ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
        🔔 Ver Alertas
        <?php
        $sqlCountAlertas = "SELECT COUNT(*) as total FROM alertas_paciente WHERE paciente_id = $paciente_id AND estado = 'Pendiente'";
        $resCountAlertas = $con->query($sqlCountAlertas);
        $countAlertas = $resCountAlertas->fetch_assoc()['total'];
        if ($countAlertas > 0) {
            echo '<span style="background: white; color: #f5576c; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 5px;">' . $countAlertas . '</span>';
        }
        ?>
      </a>
      <a href="editar_paciente.php?id=<?= $paciente_id ?>" class="btn btn-primary">✏️ Editar</a>
      <a href="nueva_consulta.php?paciente_id=<?= $paciente_id ?>" class="btn btn-success">➕ Nueva Consulta</a>
    </div>
  </div>

  <div class="content-grid">
    <!-- Información del Paciente -->
    <div class="card">
      <div class="patient-avatar">
        <?= strtoupper(substr($paciente['nombre'], 0, 1) . substr($paciente['apellido'], 0, 1)) ?>
      </div>
      
      <h2 style="text-align: center; margin-bottom: 20px;">
        <?= htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']) ?>
      </h2>

      <div class="health-indicator health-<?= strtolower($paciente['estado_salud']) ?>">
        <?php
        $iconos = [
            'Mejorando' => '📈 MEJORANDO',
            'Estable' => '➡️ ESTABLE',
            'Empeorando' => '📉 EMPEORANDO',
            'Nuevo' => '🆕 PACIENTE NUEVO'
        ];
        echo $iconos[$paciente['estado_salud']] ?? '❓ SIN EVALUAR';
        ?>
      </div>

      <div class="info-group">
        <div class="info-label">📧 Correo</div>
        <div class="info-value"><?= htmlspecialchars($paciente['correo']) ?: 'No registrado' ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">📱 Teléfono</div>
        <div class="info-value"><?= htmlspecialchars($paciente['telefono']) ?: 'No registrado' ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">🏢 Gabinete</div>
        <div class="info-value">Gabinete #<?= $paciente['gabinete_id'] ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">👆 ID Huella</div>
        <div class="info-value">#<?= $paciente['huella_id'] ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">🚨 Emergencia 1</div>
        <div class="info-value" style="font-size: 14px;"><?= htmlspecialchars($paciente['emergencia1']) ?: 'No registrado' ?></div>
      </div>

      <div class="info-group">
        <div class="info-label">🚨 Emergencia 2</div>
        <div class="info-value" style="font-size: 14px;"><?= htmlspecialchars($paciente['emergencia2']) ?: 'No registrado' ?></div>
      </div>
    </div>

    <!-- Contenido Principal -->
    <div>
      <!-- Mediciones Actuales -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-number">⚖️ <?= $paciente['peso_actual'] ?? '--' ?></div>
          <div class="stat-label">Peso Actual (kg)</div>
        </div>
        <div class="stat-card">
          <div class="stat-number">🫀 <?= $paciente['saturacion_actual'] ?? '--' ?></div>
          <div class="stat-label">Saturación Actual (%)</div>
        </div>
      </div>

      <!-- NUEVO: Card de Dispensaciones -->
      <div class="dispensaciones-card">
        <h3>💊 Historial de Dispensaciones</h3>
        <div class="disp-stats-grid">
          <div class="disp-stat-item">
            <div class="disp-stat-number"><?= $dispensaciones['total_consultas'] ?? 0 ?></div>
            <div class="disp-stat-label">Total Consultas</div>
          </div>
          <div class="disp-stat-item">
            <div class="disp-stat-number"><?= $dispensaciones['dispensaciones_dotsbox'] ?? 0 ?></div>
            <div class="disp-stat-label">Dispensaciones DotsBox</div>
          </div>
          <div class="disp-stat-item">
            <div class="disp-stat-number"><?= $dispensaciones['consultas_medico'] ?? 0 ?></div>
            <div class="disp-stat-label">Consultas Médico</div>
          </div>
          <div class="disp-stat-item">
            <div class="disp-stat-number"><?= $dispensaciones['total_dispensaciones_log'] ?? 0 ?></div>
            <div class="disp-stat-label">Dispensaciones Log</div>
          </div>
        </div>
        <?php if ($dispensaciones['ultima_dispensacion']): ?>
        <div class="ultima-dispensacion">
          🕐 Última dispensación: <?= date('d/m/Y H:i', strtotime($dispensaciones['ultima_dispensacion'])) ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Gráficas -->
      <div class="card">
        <h2>📊 Evolución del Paciente</h2>
        
        <div style="margin-bottom: 30px;">
          <h3 style="color: #667eea; font-size: 16px; margin-bottom: 15px;">Peso (kg)</h3>
          <div class="chart-container">
            <canvas id="chartPeso"></canvas>
          </div>
        </div>

        <div>
          <h3 style="color: #667eea; font-size: 16px; margin-bottom: 15px;">Saturación (%)</h3>
          <div class="chart-container">
            <canvas id="chartSaturacion"></canvas>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="card">
        <div class="tabs">
          <button class="tab active" onclick="openTab(event, 'consultas')">📋 Consultas</button>
          <button class="tab" onclick="openTab(event, 'recetas')">💊 Recetas</button>
          <button class="tab" onclick="openTab(event, 'alertas')">🔔 Alertas</button>
        </div>

        <!-- Tab Consultas -->
        <div id="consultas" class="tab-content active">
          <?php if ($resConsultas->num_rows > 0): ?>
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Médico</th>
                <th>Peso</th>
                <th>Saturación</th>
                <th>Estado</th>
                <th>Tipo</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while($c = $resConsultas->fetch_assoc()): ?>
              <tr>
                <td><?= date('d/m/Y H:i', strtotime($c['fecha_consulta'])) ?></td>
                <td><?= htmlspecialchars($c['medico_nombre']) ?></td>
                <td><?= $c['peso'] ?? '-' ?> kg</td>
                <td><?= $c['saturacion'] ?? '-' ?>%</td>
                <td><span class="badge badge-<?= strtolower($c['estado']) ?>"><?= $c['estado'] ?></span></td>
                <td>
                  <?php if ($c['es_dotsbox'] == 1): ?>
                    <span class="badge badge-dotsbox">🤖 DotsBox</span>
                  <?php else: ?>
                    <span class="badge" style="background: #e8f5e9; color: #2e7d32;">👨‍⚕️ Médico</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="ver_consulta.php?id=<?= $c['consulta_id'] ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Ver</a>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php else: ?>
          <p style="text-align: center; color: #6c757d; padding: 40px;">No hay consultas registradas</p>
          <?php endif; ?>
        </div>

        <!-- Tab Recetas -->
        <div id="recetas" class="tab-content">
          <?php if ($resRecetas->num_rows > 0): ?>
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Descripción</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while($r = $resRecetas->fetch_assoc()): ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($r['fecha_emision'])) ?></td>
                <td><?= $r['tipo'] ?></td>
                <td><?= htmlspecialchars(substr($r['descripcion'], 0, 50)) ?>...</td>
                <td><span class="badge badge-pendiente"><?= $r['estado'] ?></span></td>
                <td>
                  <a href="ver_receta.php?id=<?= $r['receta_id'] ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">Ver</a>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php else: ?>
          <p style="text-align: center; color: #6c757d; padding: 40px;">No hay recetas registradas</p>
          <?php endif; ?>
        </div>

        <!-- Tab Alertas -->
        <div id="alertas" class="tab-content">
          <?php if ($resAlertas->num_rows > 0): ?>
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Mensaje</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php while($a = $resAlertas->fetch_assoc()): ?>
              <tr>
                <td><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></td>
                <td><?= $a['tipo'] ?></td>
                <td><?= htmlspecialchars($a['mensaje']) ?></td>
                <td><span class="badge badge-pendiente"><?= $a['estado'] ?></span></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php else: ?>
          <p style="text-align: center; color: #6c757d; padding: 40px;">No hay alertas registradas</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Tabs
function openTab(evt, tabName) {
  var i, tabcontent, tabs;
  tabcontent = document.getElementsByClassName("tab-content");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
    tabcontent[i].classList.remove("active");
  }
  tabs = document.getElementsByClassName("tab");
  for (i = 0; i < tabs.length; i++) {
    tabs[i].classList.remove("active");
  }
  document.getElementById(tabName).style.display = "block";
  document.getElementById(tabName).classList.add("active");
  evt.currentTarget.classList.add("active");
}

// Gráfica de Peso
var ctxPeso = document.getElementById('chartPeso').getContext('2d');
new Chart(ctxPeso, {
  type: 'line',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [{
      label: 'Peso (kg)',
      data: <?= json_encode($pesos) ?>,
      borderColor: '#667eea',
      backgroundColor: 'rgba(102, 126, 234, 0.1)',
      tension: 0.4,
      fill: true
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false
  }
});

// Gráfica de Saturación
var ctxSat = document.getElementById('chartSaturacion').getContext('2d');
new Chart(ctxSat, {
  type: 'line',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [{
      label: 'Saturación (%)',
      data: <?= json_encode($saturaciones) ?>,
      borderColor: '#f5576c',
      backgroundColor: 'rgba(245, 87, 108, 0.1)',
      tension: 0.4,
      fill: true
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        min: 80,
        max: 100
      }
    }
  }
});
</script>

</body>
</html>