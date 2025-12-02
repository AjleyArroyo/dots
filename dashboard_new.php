<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: index.php");
    exit();
}
include("dbconnection.php");

// ============================================
// CARGAR DATOS DE GABINETES DESDE LA BD
// ============================================
// Debes tener una tabla 'gabinete' con estos campos:
// gabinete_id, nombre, sensor_temp_id, sensor_hum_id, fan_estado, uv_estado
$sqlGabinetes = "SELECT * FROM gabinete ORDER BY gabinete_id ASC LIMIT 6";
$resGabinetes = $con->query($sqlGabinetes);
$gabinetes = [];
while ($row = $resGabinetes->fetch_assoc()) {
    $gabinetes[] = $row;
}

// ============================================
// CARGAR DATOS PARA CALENDARIO (TAREAS)
// ============================================
// Debes tener una tabla 'calendario_tareas' con campos:
// tarea_id, fecha, descripcion, completada (0 o 1), tipo (ej: 'recoger_medicamento', 'dispensacion', etc)
$sqlTareas = "SELECT * FROM calendario_tareas WHERE fecha >= CURDATE() ORDER BY fecha ASC LIMIT 10";
// Ejemplo de query, ajusta según tu estructura
// $resTareas = $con->query($sqlTareas);

// ============================================
// CARGAR ALERTAS/NOTIFICACIONES PUSH
// ============================================
// Debes tener una tabla 'alertas_notificaciones' con campos:
// alerta_id, fecha_hora, mensaje, tipo, leida (0 o 1)
$sqlAlertas = "SELECT * FROM alertas_notificaciones ORDER BY fecha_hora DESC LIMIT 20";
// $resAlertas = $con->query($sqlAlertas);

// ============================================
// CARGAR DATOS DE TEMPERATURA HISTÓRICOS
// ============================================
// Para los gráficos, necesitas una tabla 'historial_temperatura' con campos:
// historial_id, gabinete_id, temperatura, humedad, fecha_hora
// Estos datos se cargarán dinámicamente con AJAX según el intervalo seleccionado
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard DOTS - Sistema Hospitalario</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    }

    .container {
      display: flex;
      min-height: 100vh;
    }

    /* === SIDEBAR IZQUIERDA === */
    .sidebar-left {
      width: 260px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
      padding: 20px;
      position: fixed;
      height: 100vh;
      overflow-y: auto;
      z-index: 1000;
    }

    .sidebar-left h2 {
      color: #667eea;
      margin-bottom: 30px;
      font-size: 24px;
      text-align: center;
      font-weight: 700;
    }

    .nav-menu a {
      display: block;
      padding: 12px 20px;
      margin-bottom: 10px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      text-decoration: none;
      border-radius: 10px;
      transition: all 0.3s ease;
      font-weight: 500;
    }

    .nav-menu a:hover {
      transform: translateX(5px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .nav-menu a.active {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .calendar-widget {
      background: white;
      border-radius: 15px;
      padding: 15px;
      margin: 20px 0;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .calendar-widget h3 {
      color: #667eea;
      margin-bottom: 15px;
      font-size: 16px;
    }

    .task-item {
      padding: 10px;
      background: #f8f9fa;
      border-radius: 8px;
      margin-bottom: 8px;
      font-size: 13px;
      border-left: 4px solid #667eea;
    }

    .task-item.completed {
      opacity: 0.6;
      text-decoration: line-through;
      border-left-color: #28a745;
    }

    .logout-btn {
      margin-top: auto;
      padding: 12px 20px;
      background: #dc3545;
      color: white;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      width: 100%;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .logout-btn:hover {
      background: #c82333;
      transform: translateY(-2px);
    }

    /* === CONTENIDO PRINCIPAL === */
    .main-content {
      margin-left: 260px;
      flex: 1;
      padding: 30px;
      overflow-y: auto;
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
      font-size: 32px;
      margin-bottom: 5px;
    }

    .header p {
      color: #6c757d;
      font-size: 14px;
    }

    /* === GRID DE GABINETES === */
    .gabinetes-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }

    .gabinete-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 25px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }

    .gabinete-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .gabinete-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .gabinete-header h3 {
      color: #667eea;
      font-size: 20px;
    }

    .sensors-row {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
    }

    .sensor-box {
      flex: 1;
      padding: 15px;
      border-radius: 15px;
      text-align: center;
      color: white;
      font-weight: 600;
    }

    .sensor-temp {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .sensor-hum {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .sensor-value {
      font-size: 28px;
      margin-bottom: 5px;
    }

    .sensor-label {
      font-size: 12px;
      opacity: 0.9;
    }

    .controls-row {
      display: flex;
      gap: 10px;
      margin-bottom: 20px;
    }

    .control-btn {
      flex: 1;
      padding: 10px 15px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      font-size: 13px;
    }

    .control-btn.fan-on {
      background: #28a745;
      color: white;
    }

    .control-btn.fan-off {
      background: #6c757d;
      color: white;
    }

    .control-btn.uv-on {
      background: #ffc107;
      color: #333;
    }

    .control-btn.uv-off {
      background: #6c757d;
      color: white;
    }

    .control-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    /* === GRÁFICO === */
    .chart-controls {
      display: flex;
      gap: 10px;
      margin-bottom: 15px;
      flex-wrap: wrap;
    }

    .interval-btn {
      padding: 8px 15px;
      border: 2px solid #667eea;
      background: white;
      color: #667eea;
      border-radius: 8px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .interval-btn:hover {
      background: #667eea;
      color: white;
    }

    .interval-btn.active {
      background: #667eea;
      color: white;
    }

    .chart-container {
      position: relative;
      height: 250px;
      background: white;
      border-radius: 10px;
      padding: 15px;
    }

    /* === SIDEBAR DERECHA (ALERTAS) === */
    .sidebar-right {
      position: fixed;
      top: 0;
      right: -400px;
      width: 400px;
      height: 100vh;
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      box-shadow: -2px 0 20px rgba(0, 0, 0, 0.1);
      transition: right 0.3s ease;
      z-index: 1001;
      overflow-y: auto;
      padding: 30px;
    }

    .sidebar-right.visible {
      right: 0;
    }

    .sidebar-right h3 {
      color: #667eea;
      margin-bottom: 20px;
      font-size: 24px;
    }

    .alert-item {
      background: white;
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 15px;
      border-left: 4px solid #667eea;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }

    .alert-item:hover {
      transform: translateX(-5px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .alert-item.warning {
      border-left-color: #ffc107;
    }

    .alert-item.danger {
      border-left-color: #dc3545;
    }

    .alert-item.success {
      border-left-color: #28a745;
    }

    .alert-time {
      font-size: 11px;
      color: #6c757d;
      margin-bottom: 5px;
    }

    .alert-message {
      font-size: 14px;
      color: #333;
    }

    .alert-toggle {
      position: fixed;
      top: 20px;
      right: 20px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: none;
      padding: 12px 20px;
      border-radius: 50px;
      cursor: pointer;
      font-weight: 600;
      color: #667eea;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      z-index: 999;
    }

    .alert-toggle:hover {
      transform: scale(1.05);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .badge {
      background: #dc3545;
      color: white;
      border-radius: 50%;
      padding: 2px 6px;
      font-size: 10px;
      margin-left: 5px;
    }

    /* Responsivo */
    @media (max-width: 1200px) {
      .gabinetes-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 768px) {
      .sidebar-left {
        width: 100%;
        position: relative;
        height: auto;
      }

      .main-content {
        margin-left: 0;
      }

      .sidebar-right {
        width: 100%;
        right: -100%;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <!-- SIDEBAR IZQUIERDA -->
  <div class="sidebar-left">
    <h2>🏥 DOTS System</h2>
    
    <nav class="nav-menu">
      <a href="dashboard.php" class="active">📊 Dashboard</a>
      <a href="pacientes.php">👥 Pacientes</a>
      <a href="calendario.php">📅 Calendario</a>
      <a href="alertas.php">🔔 Alertas</a>
      <a href="reportes.php">📈 Reportes</a>
    </nav>

    <div class="calendar-widget">
      <h3>📋 Tareas Próximas</h3>
      <!-- 
      ============================================
      CARGAR TAREAS DESDE LA BD
      ============================================
      Aquí debes hacer un loop de las tareas del calendario
      -->
      <?php
      // Ejemplo de cómo cargar las tareas (descomenta cuando tengas la tabla):
      /*
      $resTareas = $con->query("SELECT * FROM calendario_tareas WHERE fecha >= CURDATE() ORDER BY fecha ASC LIMIT 5");
      while ($tarea = $resTareas->fetch_assoc()):
      */
      ?>
      <!-- Datos de ejemplo - Reemplazar con datos reales -->
      <div class="task-item">
        <strong>15 Nov</strong><br>
        Recoger medicamento
      </div>
      <div class="task-item completed">
        <strong>16 Nov</strong><br>
        Pepito recogió medicamento
      </div>
      <div class="task-item">
        <strong>18 Nov</strong><br>
        Control de paciente Juan
      </div>
      <?php
      // endwhile;
      ?>
    </div>

    <button class="logout-btn" onclick="location.href='logout.php'">🚪 Cerrar Sesión</button>
  </div>

  <!-- CONTENIDO PRINCIPAL -->
  <div class="main-content">
    <div class="header">
      <h1>Dashboard de Control</h1>
      <p>Monitoreo en tiempo real de gabinetes y sensores</p>
    </div>

    <!-- BOTÓN FLOTANTE PARA ALERTAS -->
    <button class="alert-toggle" onclick="toggleAlerts()">
      🔔 Alertas <span class="badge">5</span>
    </button>

    <!-- GRID DE GABINETES -->
    <div class="gabinetes-grid">
      <?php
      // ============================================
      // LOOP DE GABINETES DESDE LA BD
      // ============================================
      // Si no tienes datos aún, puedes crear gabinetes de prueba
      if (empty($gabinetes)) {
        // Datos de ejemplo
        for ($i = 1; $i <= 6; $i++) {
          $gabinetes[] = [
            'gabinete_id' => $i,
            'nombre' => "Gabinete $i",
            'sensor_temp_id' => rand(18, 25),
            'sensor_hum_id' => rand(40, 60),
            'fan_estado' => rand(0, 1),
            'uv_estado' => rand(0, 1)
          ];
        }
      }

      foreach ($gabinetes as $gab):
      ?>
      <div class="gabinete-card">
        <div class="gabinete-header">
          <h3>🏢 <?php echo htmlspecialchars($gab['nombre']); ?></h3>
        </div>

        <!-- SENSORES -->
        <div class="sensors-row">
          <div class="sensor-box sensor-temp">
            <div class="sensor-value"><?php echo $gab['sensor_temp_id']; ?>°C</div>
            <div class="sensor-label">Temperatura</div>
          </div>
          <div class="sensor-box sensor-hum">
            <div class="sensor-value"><?php echo $gab['sensor_hum_id']; ?>%</div>
            <div class="sensor-label">Humedad</div>
          </div>
        </div>

        <!-- CONTROLES -->
        <div class="controls-row">
          <form method="POST" action="actualizar_gabinete.php" style="flex: 1;">
            <input type="hidden" name="gabinete_id" value="<?php echo $gab['gabinete_id']; ?>">
            <input type="hidden" name="accion" value="fan">
            <button type="submit" class="control-btn <?php echo $gab['fan_estado'] ? 'fan-on' : 'fan-off'; ?>">
              <?php echo $gab['fan_estado'] ? '🌀 Ventilador ON' : '⭕ Ventilador OFF'; ?>
            </button>
          </form>
          
          <form method="POST" action="actualizar_gabinete.php" style="flex: 1;">
            <input type="hidden" name="gabinete_id" value="<?php echo $gab['gabinete_id']; ?>">
            <input type="hidden" name="accion" value="uv">
            <button type="submit" class="control-btn <?php echo $gab['uv_estado'] ? 'uv-on' : 'uv-off'; ?>">
              <?php echo $gab['uv_estado'] ? '💡 Luz UV ON' : '⚫ Luz UV OFF'; ?>
            </button>
          </form>
        </div>

        <!-- CONTROLES DE INTERVALO DEL GRÁFICO -->
        <div class="chart-controls">
          <button class="interval-btn active" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '15min', this)">15 min</button>
          <button class="interval-btn" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '30min', this)">30 min</button>
          <button class="interval-btn" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '1hour', this)">1 hora</button>
          <button class="interval-btn" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '3hours', this)">3 horas</button>
          <button class="interval-btn" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '6hours', this)">6 horas</button>
        </div>

        <!-- GRÁFICO -->
        <div class="chart-container">
          <canvas id="chart-<?php echo $gab['gabinete_id']; ?>"></canvas>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- SIDEBAR DERECHA (ALERTAS) -->
  <div class="sidebar-right" id="alertsSidebar">
    <h3>🔔 Notificaciones y Alertas</h3>
    
    <!-- 
    ============================================
    CARGAR ALERTAS DESDE LA BD
    ============================================
    Aquí debes hacer un loop de las alertas/notificaciones push
    -->
    <?php
    // Ejemplo de cómo cargar alertas (descomenta cuando tengas la tabla):
    /*
    $resAlertas = $con->query("SELECT * FROM alertas_notificaciones ORDER BY fecha_hora DESC LIMIT 20");
    while ($alerta = $resAlertas->fetch_assoc()):
      $tipoClase = 'info'; // Puedes tener: info, warning, danger, success
      if ($alerta['tipo'] == 'warning') $tipoClase = 'warning';
      if ($alerta['tipo'] == 'error') $tipoClase = 'danger';
      if ($alerta['tipo'] == 'success') $tipoClase = 'success';
    */
    ?>
    <!-- Datos de ejemplo - Reemplazar con datos reales -->
    <div class="alert-item danger">
      <div class="alert-time">Hoy, 10:30 AM</div>
      <div class="alert-message">⚠️ Temperatura alta en Gabinete 3 (28°C)</div>
    </div>
    
    <div class="alert-item warning">
      <div class="alert-time">Hoy, 09:15 AM</div>
      <div class="alert-message">⚡ Ventilador Gabinete 5 desconectado automáticamente</div>
    </div>
    
    <div class="alert-item success">
      <div class="alert-time">Ayer, 6:45 PM</div>
      <div class="alert-message">✅ Medicamento dispensado a paciente María López</div>
    </div>
    
    <div class="alert-item">
      <div class="alert-time">Ayer, 4:20 PM</div>
      <div class="alert-message">📋 Nueva tarea programada: Control de stock</div>
    </div>
    
    <div class="alert-item warning">
      <div class="alert-time">Ayer, 2:10 PM</div>
      <div class="alert-message">💊 Stock bajo de Paracetamol (5 unidades)</div>
    </div>
    <?php
    // endwhile;
    ?>
  </div>
</div>

<script>
  // Objeto para almacenar las instancias de los gráficos
  const charts = {};

  // Inicializar gráficos para cada gabinete
  <?php foreach ($gabinetes as $gab): ?>
  (function() {
    const ctx = document.getElementById('chart-<?php echo $gab['gabinete_id']; ?>').getContext('2d');
    
    // ============================================
    // DATOS DE EJEMPLO PARA EL GRÁFICO
    // ============================================
    // En producción, estos datos deben cargarse desde la BD mediante AJAX
    // según el intervalo seleccionado
    const labels = ['10:00', '10:15', '10:30', '10:45', '11:00', '11:15'];
    const tempData = [<?php echo rand(20, 25); ?>, <?php echo rand(20, 25); ?>, <?php echo rand(20, 25); ?>, 
                      <?php echo rand(20, 25); ?>, <?php echo rand(20, 25); ?>, <?php echo rand(20, 25); ?>];
    
    charts[<?php echo $gab['gabinete_id']; ?>] = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Temperatura (°C)',
          data: tempData,
          borderColor: '#f5576c',
          backgroundColor: 'rgba(245, 87, 108, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: true,
            position: 'top'
          }
        },
        scales: {
          y: {
            beginAtZero: false,
            min: 15,
            max: 30
          }
        }
      }
    });
  })();
  <?php endforeach; ?>

  // Función para actualizar gráfico según intervalo seleccionado
  function updateChart(gabineteId, interval, button) {
    // Remover clase active de todos los botones del gabinete
    const parent = button.parentElement;
    parent.querySelectorAll('.interval-btn').forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');

    // ============================================
    // AQUÍ DEBES HACER UNA LLAMADA AJAX
    // ============================================
    // Para cargar los datos históricos desde la BD según el intervalo
    // Ejemplo de endpoint: get_temperature_data.php?gabinete_id=X&interval=15min
    
    /*
    fetch(`get_temperature_data.php?gabinete_id=${gabineteId}&interval=${interval}`)
      .then(response => response.json())
      .then(data => {
        // data debe contener: { labels: [...], temperatures: [...] }
        const chart = charts[gabineteId];
        chart.data.labels = data.labels;
        chart.data.datasets[0].data = data.temperatures;
        chart.update();
      })
      .catch(error => console.error('Error:', error));
    */

    // Por ahora, simular actualización con datos aleatorios
    const chart = charts[gabineteId];
    let newLabels = [];
    let newData = [];
    
    // Generar datos según el intervalo
    const intervals = {
      '15min': { count: 8, format: 'HH:MM' },
      '30min': { count: 8, format: 'HH:MM' },
      '1hour': { count: 12, format: 'HH:MM' },
      '3hours': { count: 12, format: 'HH:MM' },
      '6hours': { count: 24, format: 'HH:MM' }
    };
    
    for (let i = 0; i < intervals[interval].count; i++) {
      newLabels.push(`${10 + i}:00`);
      newData.push(Math.random() * 5 + 20);
    }
    
    chart.data.labels = newLabels;
    chart.data.datasets[0].data = newData;
    chart.update();
  }

  // Toggle sidebar de alertas
  function toggleAlerts() {
    const sidebar = document.getElementById('alertsSidebar');
    sidebar.classList.toggle('visible');
  }

  // Cerrar sidebar al hacer clic fuera
  document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('alertsSidebar');
    const button = document.querySelector('.alert-toggle');
    
    if (!sidebar.contains(event.target) && !button.contains(event.target)) {
      sidebar.classList.remove('visible');
    }
  });
</script>

</body>
</html>