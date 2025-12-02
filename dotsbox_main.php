<?php
session_start();

// Verificar que sea usuario DotsBox
if (!isset($_SESSION['me_id']) || !isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'dotsbox') {
    header("Location: index.php");
    exit();
}

include("dbconnection.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DotsBox - Sistema de Dispensación Automática</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .container {
      width: 100%;
      max-width: 900px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 30px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      overflow: hidden;
    }
    
    .header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 40px;
      text-align: center;
      position: relative;
    }
    
    .logout-btn {
      position: absolute;
      top: 20px;
      right: 20px;
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: 2px solid rgba(255, 255, 255, 0.5);
      padding: 8px 16px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
    }
    
    .logout-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }
    
    .logo {
      font-size: 80px;
      margin-bottom: 15px;
      animation: float 3s ease-in-out infinite;
    }
    
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    
    .header h1 {
      font-size: 36px;
      margin-bottom: 10px;
    }
    
    .header p {
      font-size: 16px;
      opacity: 0.9;
    }
    
    .content {
      padding: 50px;
    }
    
    .screen {
      display: none;
      animation: fadeIn 0.5s ease;
    }
    
    .screen.active {
      display: block;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    /* Pantalla de Inicio */
    .welcome-screen {
      text-align: center;
    }
    
    .fingerprint-icon {
      width: 200px;
      height: 200px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 100px;
      margin: 0 auto 30px;
      animation: pulse 2s ease-in-out infinite;
      box-shadow: 0 10px 40px rgba(102, 126, 234, 0.4);
      cursor: pointer;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    
    .instruction {
      font-size: 24px;
      color: #333;
      margin-bottom: 15px;
      font-weight: 600;
    }
    
    .sub-instruction {
      font-size: 16px;
      color: #6c757d;
      margin-bottom: 30px;
    }
    
    .btn {
      padding: 15px 40px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      font-size: 18px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }
    
    .btn-secondary {
      background: white;
      color: #667eea;
      border: 2px solid #667eea;
    }
    
    /* Pantalla de Paciente Identificado */
    .patient-info {
      background: #f8f9fa;
      border-radius: 20px;
      padding: 30px;
      margin-bottom: 30px;
    }
    
    .patient-header {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 25px;
    }
    
    .patient-avatar {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 36px;
      color: white;
      flex-shrink: 0;
    }
    
    .patient-name {
      font-size: 28px;
      color: #333;
      font-weight: 700;
    }
    
    .patient-id {
      font-size: 14px;
      color: #6c757d;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      margin-bottom: 25px;
    }
    
    .stat-card {
      background: white;
      padding: 20px;
      border-radius: 15px;
      text-align: center;
      border-left: 4px solid #667eea;
    }
    
    .stat-value {
      font-size: 32px;
      font-weight: 700;
      color: #667eea;
      margin-bottom: 5px;
    }
    
    .stat-label {
      font-size: 13px;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .alert-box {
      background: #fff3cd;
      border: 2px solid #ffc107;
      border-radius: 15px;
      padding: 15px 20px;
      display: flex;
      align-items: center;
      gap: 15px;
      margin-top: 20px;
    }
    
    .alert-icon {
      font-size: 32px;
    }
    
    .alert-text {
      font-size: 14px;
      color: #856404;
      font-weight: 600;
    }
    
    /* Pantalla de Mediciones */
    .measurement-container {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .measurement-icon {
      width: 150px;
      height: 150px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 70px;
      color: white;
      margin: 0 auto 20px;
      animation: rotate 2s linear infinite;
    }
    
    @keyframes rotate {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    
    .measurement-status {
      font-size: 20px;
      color: #333;
      font-weight: 600;
      margin-bottom: 10px;
    }
    
    .measurement-value-display {
      font-size: 48px;
      color: #667eea;
      font-weight: 700;
      margin: 20px 0;
    }
    
    .progress-bar {
      width: 100%;
      height: 8px;
      background: #e0e0e0;
      border-radius: 10px;
      overflow: hidden;
      margin-top: 20px;
    }
    
    .progress-fill {
      height: 100%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      width: 0%;
      transition: width 0.3s ease;
      border-radius: 10px;
    }
    
    /* Pantalla de Finalización */
    .success-screen {
      text-align: center;
    }
    
    .success-icon {
      width: 150px;
      height: 150px;
      background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 80px;
      color: white;
      margin: 0 auto 30px;
      animation: checkmark 0.6s ease;
    }
    
    @keyframes checkmark {
      0% { transform: scale(0); }
      50% { transform: scale(1.2); }
      100% { transform: scale(1); }
    }
    
    .success-title {
      font-size: 32px;
      color: #333;
      font-weight: 700;
      margin-bottom: 15px;
    }
    
    .success-message {
      font-size: 18px;
      color: #6c757d;
      margin-bottom: 30px;
    }
    
    .summary-card {
      background: #f8f9fa;
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 30px;
      text-align: left;
    }
    
    .summary-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid #e0e0e0;
    }
    
    .summary-row:last-child {
      border-bottom: none;
    }
    
    .summary-label {
      color: #6c757d;
      font-size: 14px;
    }
    
    .summary-value {
      color: #333;
      font-weight: 600;
      font-size: 14px;
    }
    
    .dispense-box {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
      padding: 30px;
      border-radius: 20px;
      margin-bottom: 30px;
      text-align: center;
    }
    
    .dispense-box h3 {
      font-size: 24px;
      margin-bottom: 15px;
    }
    
    .dispense-animation {
      font-size: 60px;
      animation: bounce 1s ease infinite;
    }
    
    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    
    .button-group {
      display: flex;
      gap: 15px;
      justify-content: center;
    }
    
    @media (max-width: 768px) {
      .content {
        padding: 30px 20px;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .patient-header {
        flex-direction: column;
        text-align: center;
      }
      
      .button-group {
        flex-direction: column;
      }
      
      .btn {
        width: 100%;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <div class="header">
    <a href="logout_php.php" class="logout-btn">🚪 Salir</a>
    <div class="logo">🏥</div>
    <h1>DotsBox</h1>
    <p>Sistema de Control y Dispensación Automática</p>
  </div>

  <div class="content">
    <!-- PANTALLA 1: Bienvenida -->
    <div id="screen-welcome" class="screen active">
      <div class="welcome-screen">
        <div class="fingerprint-icon" onclick="leerHuella()">👆</div>
        <div class="instruction">Bienvenido a DotsBox</div>
        <div class="sub-instruction">
          Por favor, coloque su huella digital en el sensor para identificarse
        </div>
        <button class="btn" onclick="leerHuella()">🔍 Iniciar Identificación</button>
      </div>
    </div>

    <!-- PANTALLA 2: Paciente Identificado -->
    <div id="screen-patient" class="screen">
      <div class="patient-info">
        <div class="patient-header">
          <div class="patient-avatar" id="patientAvatar">👤</div>
          <div>
            <div class="patient-name" id="patientName">Cargando...</div>
            <div class="patient-id">ID: <span id="patientId">---</span> | Huella: <span id="fingerprintId">---</span></div>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-value" id="lastWeight">--</div>
            <div class="stat-label">⚖️ Último Peso (kg)</div>
          </div>
          <div class="stat-card">
            <div class="stat-value" id="lastSaturation">--</div>
            <div class="stat-label">🫀 Última Saturación (%)</div>
          </div>
          <div class="stat-card">
            <div class="stat-value" id="dispensationCount">--</div>
            <div class="stat-label">💊 Dispensaciones</div>
          </div>
        </div>

        <div id="pendingLabAlert" style="display: none;" class="alert-box">
          <div class="alert-icon">📋</div>
          <div class="alert-text" id="labAlertText">Tiene laboratorios pendientes</div>
        </div>
      </div>

      <div style="text-align: center;">
        <p style="font-size: 18px; color: #333; margin-bottom: 20px; font-weight: 600;">
          Procedemos a actualizar sus mediciones
        </p>
        <button class="btn" onclick="iniciarMediciones()">▶️ Continuar con Mediciones</button>
      </div>
    </div>

    <!-- PANTALLA 3: Midiendo Peso -->
    <div id="screen-weight" class="screen">
      <div class="measurement-container">
        <div class="measurement-icon">⚖️</div>
        <div class="measurement-status">Leyendo su peso...</div>
        <div class="sub-instruction">Por favor, manténgase sobre la báscula</div>
        <div class="measurement-value-display" id="weightValue">-- kg</div>
        <div class="progress-bar">
          <div class="progress-fill" id="weightProgress"></div>
        </div>
      </div>
    </div>

    <!-- PANTALLA 4: Midiendo Saturación -->
    <div id="screen-saturation" class="screen">
      <div class="measurement-container">
        <div class="measurement-icon">🫀</div>
        <div class="measurement-status">Leyendo su saturación de oxígeno...</div>
        <div class="sub-instruction">Coloque su dedo en el sensor</div>
        <div class="measurement-value-display" id="saturationValue">-- %</div>
        <div class="progress-bar">
          <div class="progress-fill" id="saturationProgress"></div>
        </div>
      </div>
    </div>

    <!-- PANTALLA 5: Procesando y Guardando -->
    <div id="screen-processing" class="screen">
      <div class="measurement-container">
        <div class="measurement-icon">💾</div>
        <div class="measurement-status">Procesando sus datos...</div>
        <div class="sub-instruction">Guardando consulta en el sistema</div>
        <div class="progress-bar">
          <div class="progress-fill" id="processingProgress"></div>
        </div>
      </div>
    </div>

    <!-- PANTALLA 6: Finalización y Dispensación -->
    <div id="screen-success" class="screen">
      <div class="success-screen">
        <div class="success-icon">✅</div>
        <div class="success-title">¡Consulta Registrada!</div>
        <div class="success-message">Sus mediciones han sido guardadas correctamente</div>

        <div class="summary-card">
          <div class="summary-row">
            <span class="summary-label">Paciente:</span>
            <span class="summary-value" id="summaryName">---</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">Peso Registrado:</span>
            <span class="summary-value" id="summaryWeight">-- kg</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">Saturación:</span>
            <span class="summary-value" id="summarySaturation">-- %</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">Fecha y Hora:</span>
            <span class="summary-value" id="summaryDate">---</span>
          </div>
        </div>

        <div class="dispense-box">
          <h3>💊 Dispensando Medicamento</h3>
          <div class="dispense-animation">⬇️</div>
          <p style="margin-top: 15px;">Puede recoger su medicamento en el compartimento inferior</p>
        </div>

        <div class="button-group">
          <button class="btn" onclick="reiniciar()">🔄 Nueva Dispensación</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// CONFIGURACIÓN: URLs del ESP32
const ESP32_HUELLA_URL = 'http://192.168.1.100/leer_huella';
const ESP32_PESO_URL = 'http://192.168.1.100/leer_peso';
const ESP32_SATURACION_URL = 'http://192.168.1.100/leer_saturacion';
const ESP32_DISPENSAR_URL = 'http://192.168.1.100/dispensar_medicamento';

// Variables globales
let pacienteActual = null;
let pesoRegistrado = null;
let saturacionRegistrada = null;

// Funciones de navegación
function mostrarPantalla(screenId) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(screenId).classList.add('active');
}

// PASO 1: Leer huella y buscar paciente
function leerHuella() {
  console.log('🔍 Leyendo huella...');
  
  // Llamar al ESP32 para leer huella
  fetch(ESP32_HUELLA_URL)
    .then(r => r.json())
    .then(data => {
      if (data.huella_id) {
        buscarPaciente(data.huella_id);
      } else {
        alert('❌ No se pudo leer la huella. Intente nuevamente.');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      // Para pruebas, usar ID fijo (COMENTAR EN PRODUCCIÓN)
      buscarPaciente(3); // ID de prueba
    });
}

// Buscar paciente por huella
function buscarPaciente(huellaId) {
  fetch(`dotsbox_buscar_paciente.php?huella_id=${huellaId}`)
    .then(r => r.json())
    .then(data => {
      if (data.success && data.paciente) {
        pacienteActual = data.paciente;
        mostrarInfoPaciente();
        mostrarPantalla('screen-patient');
      } else {
        alert('❌ Paciente no encontrado. Verifique su huella.');
        reiniciar();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Error al buscar paciente');
      reiniciar();
    });
}

// Mostrar información del paciente
function mostrarInfoPaciente() {
  const p = pacienteActual;
  const iniciales = p.nombre.charAt(0) + p.apellido.charAt(0);
  
  document.getElementById('patientAvatar').textContent = iniciales;
  document.getElementById('patientName').textContent = `${p.nombre} ${p.apellido}`;
  document.getElementById('patientId').textContent = p.paciente_id;
  document.getElementById('fingerprintId').textContent = p.huella_id;
  document.getElementById('lastWeight').textContent = p.peso_actual || '--';
  document.getElementById('lastSaturation').textContent = p.saturacion_actual || '--';
  document.getElementById('dispensationCount').textContent = p.dispensaciones || '0';
  
  // Mostrar alerta de laboratorios pendientes
  if (p.labs_pendientes && p.labs_pendientes > 0) {
    document.getElementById('labAlertText').textContent = 
      `Tiene ${p.labs_pendientes} laboratorio(s) pendiente(s)`;
    document.getElementById('pendingLabAlert').style.display = 'flex';
  }
}

// PASO 2: Iniciar mediciones
function iniciarMediciones() {
  mostrarPantalla('screen-weight');
  medirPeso();
}

// Medir peso
function medirPeso() {
  let progress = 0;
  const progressBar = document.getElementById('weightProgress');
  
  const interval = setInterval(() => {
    progress += 10;
    progressBar.style.width = progress + '%';
    
    if (progress >= 100) {
      clearInterval(interval);
      // Llamar al ESP32
      fetch(ESP32_PESO_URL)
        .then(r => r.json())
        .then(data => {
          if (data.peso) {
            pesoRegistrado = data.peso;
            document.getElementById('weightValue').textContent = data.peso + ' kg';
            setTimeout(() => {
              mostrarPantalla('screen-saturation');
              medirSaturacion();
            }, 1500);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          // Valor de prueba (COMENTAR EN PRODUCCIÓN)
          pesoRegistrado = (Math.random() * 20 + 60).toFixed(1);
          document.getElementById('weightValue').textContent = pesoRegistrado + ' kg';
          setTimeout(() => {
            mostrarPantalla('screen-saturation');
            medirSaturacion();
          }, 1500);
        });
    }
  }, 200);
}

// Medir saturación
function medirSaturacion() {
  let progress = 0;
  const progressBar = document.getElementById('saturationProgress');
  
  const interval = setInterval(() => {
    progress += 10;
    progressBar.style.width = progress + '%';
    
    if (progress >= 100) {
      clearInterval(interval);
      // Llamar al ESP32
      fetch(ESP32_SATURACION_URL)
        .then(r => r.json())
        .then(data => {
          if (data.saturacion) {
            saturacionRegistrada = data.saturacion;
            document.getElementById('saturationValue').textContent = data.saturacion + ' %';
            setTimeout(guardarConsulta, 1500);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          // Valor de prueba (COMENTAR EN PRODUCCIÓN)
          saturacionRegistrada = Math.floor(Math.random() * 10 + 90);
          document.getElementById('saturationValue').textContent = saturacionRegistrada + ' %';
          setTimeout(guardarConsulta, 1500);
        });
    }
  }, 200);
}

// PASO 3: Guardar consulta
function guardarConsulta() {
  mostrarPantalla('screen-processing');
  
  let progress = 0;
  const progressBar = document.getElementById('processingProgress');
  
  const interval = setInterval(() => {
    progress += 20;
    progressBar.style.width = progress + '%';
    
    if (progress >= 100) {
      clearInterval(interval);
      
      // Enviar datos al servidor
      const formData = new FormData();
      formData.append('paciente_id', pacienteActual.paciente_id);
      formData.append('peso', pesoRegistrado);
      formData.append('saturacion', saturacionRegistrada);
      formData.append('huella_id', pacienteActual.huella_id);
      
      fetch('dotsbox_guardar_consulta.php', {
        method: 'POST',
        body: formData
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          mostrarResumenFinal();
          dispensarMedicamento();
        } else {
          alert('Error al guardar: ' + data.error);
          reiniciar();
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión');
        reiniciar();
      });
    }
  }, 300);
}

// Mostrar resumen final
function mostrarResumenFinal() {
  document.getElementById('summaryName').textContent = 
    `${pacienteActual.nombre} ${pacienteActual.apellido}`;
  document.getElementById('summaryWeight').textContent = pesoRegistrado + ' kg';
  document.getElementById('summarySaturation').textContent = saturacionRegistrada + ' %';
  document.getElementById('summaryDate').textContent = 
    new Date().toLocaleString('es-ES');
  
  mostrarPantalla('screen-success');
}

// PASO 4: Dispensar medicamento
function dispensarMedicamento() {
  fetch(ESP32_DISPENSAR_URL)
    .then(r => r.json())
    .then(data => {
      console.log('💊 Medicamento dispensado:', data);
    })
    .catch(error => {
      console.error('Error al dispensar:', error);
    });
}

// Reiniciar el sistema
function reiniciar() {
  pacienteActual = null;
  pesoRegistrado = null;
  saturacionRegistrada = null;
  mostrarPantalla('screen-welcome');
}

// Inicializar
console.log('✅ DotsBox System Ready');
</script>

</body>
</html>
