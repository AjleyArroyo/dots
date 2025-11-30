<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: login.php");
    exit();
}
include("dbconnection.php");

// Cargar datos de gabinetes
$sqlGabinetes = "SELECT * FROM gabinete ORDER BY gabinete_id ASC LIMIT 6";
$resGabinetes = $con->query($sqlGabinetes);
$gabinetes = [];
while ($row = $resGabinetes->fetch_assoc()) {
    $gabinetes[] = $row;
}

// Si no hay gabinetes, crear datos de ejemplo
if (empty($gabinetes)) {
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
  <!-- PWA Meta Tags -->
  <meta name="theme-color" content="#667eea">
  <meta name="description" content="Sistema DOTS de gestión hospitalaria">
  <link rel="manifest" href="manifest.json">
  
  <!-- iOS Meta Tags -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="DOTS">
  <link rel="apple-touch-icon" href="icon-192.png">
  
  <title>Dashboard DOTS - Sistema Hospitalario</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
      transition: transform 0.3s ease;
      left: 0;
      top: 0;
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

    .connection-status {
      position: fixed;
      top: 20px;
      left: 280px;
      background: #28a745;
      color: white;
      padding: 8px 15px;
      border-radius: 20px;
      font-size: 12px;
      z-index: 1002;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }

    .connection-status.offline {
      background: #dc3545;
    }

    .status-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: white;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }

    /* === GRID DE GABINETES === */
    .gabinetes-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
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
      transition: transform 0.3s ease;
    }

    .sensor-box:hover {
      transform: scale(1.05);
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

    .menu-toggle {
      display: none;
      position: fixed;
      top: 20px;
      left: 20px;
      background: rgba(255, 255, 255, 0.95);
      border: none;
      padding: 10px 15px;
      border-radius: 10px;
      cursor: pointer;
      z-index: 1001;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }

    .menu-toggle span {
      display: block;
      width: 25px;
      height: 3px;
      background: #667eea;
      margin: 5px 0;
      transition: all 0.3s ease;
    }

    @media (max-width: 1200px) {
      .gabinetes-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 768px) {
      .menu-toggle {
        display: block;
      }

      .sidebar-left {
        transform: translateX(-100%);
        width: 280px;
      }

      .sidebar-left.visible {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0;
        padding: 80px 15px 20px 15px;
      }

      .connection-status {
        left: 70px;
        top: 25px;
        font-size: 11px;
        padding: 6px 12px;
      }
    }

    @keyframes updateFlash {
      0%, 100% { background-color: rgba(255, 255, 255, 0.95); }
      50% { background-color: rgba(102, 126, 234, 0.2); }
    }

    .updating {
      animation: updateFlash 1s ease;
    }
  </style>
</head>
<body>

<div class="container">
  <button class="menu-toggle" id="menuToggle">
    <span></span>
    <span></span>
    <span></span>
  </button>

  <div class="connection-status" id="connectionStatus">
    <div class="status-dot"></div>
    <span>En línea</span>
  </div>

  <div class="sidebar-left" id="sidebarLeft">
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
      <div class="task-item">
        <strong>15 Nov</strong><br>
        Recoger medicamento
      </div>
      <div class="task-item completed">
        <strong>16 Nov</strong><br>
        Pepito recogió medicamento
      </div>
    </div>

    <button class="logout-btn" onclick="location.href='logout.php'">🚪 Cerrar Sesión</button>
  </div>

  <div class="main-content">
    <div class="header">
      <h1>Dashboard de Control</h1>
      <p>Monitoreo en tiempo real de gabinetes y sensores</p>
    </div>

    <div class="gabinetes-grid" id="gabinetesGrid">
      <?php foreach ($gabinetes as $gab): ?>
      <div class="gabinete-card" id="gabinete-<?php echo $gab['gabinete_id']; ?>">
        <div class="gabinete-header">
          <h3>🏢 <?php echo htmlspecialchars($gab['nombre']); ?></h3>
        </div>

        <div class="sensors-row">
          <div class="sensor-box sensor-temp">
            <div class="sensor-value" id="temp-<?php echo $gab['gabinete_id']; ?>">
              <?php echo $gab['sensor_temp_id']; ?>°C
            </div>
            <div class="sensor-label">Temperatura</div>
          </div>
          <div class="sensor-box sensor-hum">
            <div class="sensor-value" id="hum-<?php echo $gab['gabinete_id']; ?>">
              <?php echo $gab['sensor_hum_id']; ?>%
            </div>
            <div class="sensor-label">Humedad</div>
          </div>
        </div>

        <div class="controls-row">
          <form method="POST" action="actualizar_gabinete.php" style="flex: 1;">
            <input type="hidden" name="gabinete_id" value="<?php echo $gab['gabinete_id']; ?>">
            <input type="hidden" name="accion" value="fan">
            <button type="submit" class="control-btn <?php echo $gab['fan_estado'] ? 'fan-on' : 'fan-off'; ?>" id="fan-btn-<?php echo $gab['gabinete_id']; ?>">
              <?php echo $gab['fan_estado'] ? '🌀 Ventilador ON' : '⭕ Ventilador OFF'; ?>
            </button>
          </form>
          
          <form method="POST" action="actualizar_gabinete.php" style="flex: 1;">
            <input type="hidden" name="gabinete_id" value="<?php echo $gab['gabinete_id']; ?>">
            <input type="hidden" name="accion" value="uv">
            <button type="submit" class="control-btn <?php echo $gab['uv_estado'] ? 'uv-on' : 'uv-off'; ?>" id="uv-btn-<?php echo $gab['gabinete_id']; ?>">
              <?php echo $gab['uv_estado'] ? '💡 Luz UV ON' : '⚫ Luz UV OFF'; ?>
            </button>
          </form>
        </div>

        <div class="chart-controls">
          <button class="interval-btn active" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '1min', this)">1 min</button>
<button class="interval-btn" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '5min', this)">5 min</button>
<button class="interval-btn" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '15min', this)">15 min</button>
<button class="interval-btn" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '30min', this)">30 min</button>
<button class="interval-btn" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '1hour', this)">1 hora</button>
<button class="interval-btn" onclick="updateChart(<?php echo $gab['gabinete_id']; ?>, '3hours', this)">3 horas</button>
        </div>

        <div class="chart-container">
          <canvas id="chart-<?php echo $gab['gabinete_id']; ?>"></canvas>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
// Variables globales
var charts = {};
var REFRESH_INTERVAL = 30000; // 30 segundos
var updateTimer = null;

console.log('🚀 Iniciando dashboard...');

// Inicializar gráficos
function initCharts() {
  <?php foreach ($gabinetes as $gab): ?>
  (function() {
    var ctx = document.getElementById('chart-<?php echo $gab['gabinete_id']; ?>');
    if (!ctx) return;
    
    charts[<?php echo $gab['gabinete_id']; ?>] = new Chart(ctx.getContext('2d'), {
      type: 'line',
      data: {
        labels: [], // Horas
        datasets: [{
          label: 'Temperatura (°C)',
          data: [],
          borderColor: '#f5576c',
          backgroundColor: 'rgba(245, 87, 108, 0.1)',
          tension: 0.4,
          fill: true,
          borderWidth: 2
        }, {
          label: 'Humedad (%)',
          data: [],
          borderColor: '#4facfe',
          backgroundColor: 'rgba(79, 172, 254, 0.1)',
          tension: 0.4,
          fill: true,
          borderWidth: 2
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
          x: {
            title: {
              display: true,
              text: 'Hora'
            }
          },
          y: {
            beginAtZero: false,
            min: 0,
            max: 100,
            title: {
              display: true,
              text: 'Valor'
            }
          }
        }
      }
    });
    
    console.log('✅ Gráfico inicializado: Gabinete <?php echo $gab['gabinete_id']; ?>');
    
    // Cargar datos iniciales
    updateChart(<?php echo $gab['gabinete_id']; ?>, '15min', null);
  })();
  <?php endforeach; ?>
}

// Actualizar gráfico específico
function updateChart(gabineteId, interval, button) {
  if (button) {
    var parent = button.parentElement;
    var buttons = parent.querySelectorAll('.interval-btn');
    buttons.forEach(b => b.classList.remove('active'));
    button.classList.add('active');
  }

  console.log('📊 Actualizando gráfico Gabinete ' + gabineteId + ', Intervalo: ' + interval);

  fetch('get_temperature_history.php?gabinete_id=' + gabineteId + '&interval=' + interval)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        var chart = charts[gabineteId];
        if (chart) {
          // Convertir a números para evitar problemas de Chart.js
          chart.data.labels = data.labels;
          chart.data.datasets[0].data = data.temperatures.map(Number);
          chart.data.datasets[1].data = data.humidities.map(Number);

          // Forzar actualización
          chart.update('none');

          console.log('✅ Gráfico actualizado: Gabinete ' + gabineteId);
        }
      } else {
        console.error('❌ Error en datos del gráfico:', data.error);
      }
    })
    .catch(error => {
      console.error('❌ Error al cargar gráfico:', error);
    });
}

// Actualizar datos de gabinetes
function updateGabinetesData() {
  console.log('🔄 Actualizando datos de gabinetes...');
  
  fetch('get_gabinetes_data.php')
    .then(response => response.json())
    .then(data => {
      if (data.success && data.gabinetes) {
        console.log('✅ Datos recibidos: ' + data.gabinetes.length + ' gabinetes');
        
        data.gabinetes.forEach(gab => {
          var tempEl = document.getElementById('temp-' + gab.gabinete_id);
          if (tempEl) tempEl.textContent = gab.temperatura + '°C';

          var humEl = document.getElementById('hum-' + gab.gabinete_id);
          if (humEl) humEl.textContent = gab.humedad + '%';

          var fanBtn = document.getElementById('fan-btn-' + gab.gabinete_id);
          if (fanBtn) {
            fanBtn.className = 'control-btn ' + (gab.fan_estado ? 'fan-on' : 'fan-off');
            fanBtn.innerHTML = gab.fan_estado ? '🌀 Ventilador ON' : '⭕ Ventilador OFF';
          }

          var uvBtn = document.getElementById('uv-btn-' + gab.gabinete_id);
          if (uvBtn) {
            uvBtn.className = 'control-btn ' + (gab.uv_estado ? 'uv-on' : 'uv-off');
            uvBtn.innerHTML = gab.uv_estado ? '💡 Luz UV ON' : '⚫ Luz UV OFF';
          }
        });
        
        updateConnectionStatus(true);
      }
    })
    .catch(error => {
      console.error('❌ Error actualizando datos:', error);
      updateConnectionStatus(false);
    });
}

// Estado de conexión
function updateConnectionStatus(isOnline) {
  var statusEl = document.getElementById('connectionStatus');
  if (!statusEl) return;
  statusEl.className = isOnline ? 'connection-status' : 'connection-status offline';
  statusEl.innerHTML = '<div class="status-dot"></div><span>' + (isOnline ? 'En línea' : 'Sin conexión') + '</span>';
}

// Toggle menú móvil
document.getElementById('menuToggle').addEventListener('click', function() {
  document.getElementById('sidebarLeft').classList.toggle('visible');
});

// Inicializar al cargar la página
window.addEventListener('load', function() {
  console.log('✅ Página cargada');
  initCharts();

  // Primera actualización de datos
  setTimeout(updateGabinetesData, 2000);

  // Actualización periódica
  updateTimer = setInterval(updateGabinetesData, REFRESH_INTERVAL);
  console.log('⏱️ Actualización automática cada ' + (REFRESH_INTERVAL / 1000) + ' segundos');
});
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('service-worker.js')
      .then(function(registration) {
        console.log('✅ Service Worker registrado:', registration);
      })
      .catch(function(error) {
        console.log('❌ Error al registrar Service Worker:', error);
      });
  });
}
</script>


</body>
</html>