<?php
session_start();
include("dbconnection.php");

if (isset($_POST['login'])) {
    $usuario = $_POST['usuario'];
    $contrasena = $_POST['contrasena'];

    $sql = "SELECT * FROM mepersonel WHERE usuario = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row && password_verify($contrasena, $row['contrasena'])) {
        $_SESSION['me_id'] = $row['id'];
        $_SESSION['me_nombre'] = $row['nombre'];
        $_SESSION['tipo_usuario'] = $row['tipo_usuario'];
        
        // Redirigir según tipo de usuario
        if ($row['tipo_usuario'] === 'dotsbox') {
            header("Location: dotsbox_main.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    } else {
        $error = "Credenciales incorrectas";
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
  <link rel="manifest" href="./manifest.json">

  
  <!-- iOS Meta Tags -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="DOTS">
  <link rel="apple-touch-icon" href="icon-192.png">
  <title>Login DOTS</title>
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
    
    .login-container {
      width: 100%;
      max-width: 400px;
      padding: 40px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideIn 0.5s ease;
    }
    
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .logo {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .logo-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 40px;
      margin: 0 auto 15px;
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }
    
    h2 {
      text-align: center;
      color: #667eea;
      font-size: 28px;
      margin-bottom: 10px;
    }
    
    .subtitle {
      text-align: center;
      color: #6c757d;
      font-size: 14px;
      margin-bottom: 30px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      color: #333;
      font-weight: 600;
      margin-bottom: 8px;
      font-size: 14px;
    }
    
    input[type=text], 
    input[type=password] {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 15px;
      transition: all 0.3s ease;
      font-family: inherit;
    }
    
    input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    input[type=submit] {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 14px;
      width: 100%;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      margin-top: 10px;
      transition: all 0.3s ease;
    }
    
    input[type=submit]:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }
    
    input[type=submit]:active {
      transform: translateY(0);
    }
    
    .error {
      background: #ffe6e6;
      color: #c41e3a;
      padding: 12px 16px;
      border-radius: 10px;
      text-align: center;
      font-size: 14px;
      margin-bottom: 20px;
      border-left: 4px solid #c41e3a;
      animation: shake 0.5s ease;
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-10px); }
      75% { transform: translateX(10px); }
    }
    
    .quick-access {
      margin-top: 25px;
      padding-top: 25px;
      border-top: 2px solid #e0e0e0;
    }
    
    .quick-access h4 {
      color: #6c757d;
      font-size: 13px;
      text-align: center;
      margin-bottom: 15px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .quick-buttons {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    
    .quick-btn {
      padding: 10px 15px;
      background: white;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      color: #667eea;
      transition: all 0.3s ease;
      text-align: center;
    }
    
    .quick-btn:hover {
      background: #f8f9fa;
      border-color: #667eea;
      transform: translateY(-2px);
    }
    
  .footer {
    position: fixed;
    bottom: 10px;
    left: 0;
    width: 100%;
    text-align: center;
    color: rgba(255, 255, 255, 0.9);
    font-size: 13px;
  }
    
    @media (max-width: 480px) {
      .login-container {
        padding: 30px 25px;
      }
      
      h2 {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>

<div class="login-container">
  <div class="logo">
    <div class="logo-icon">🏥</div>
    <h2>Sistema DOTS</h2>
    <p class="subtitle">Control y Dispensación Automática</p>
  </div>
  
  <?php if (isset($error)): ?>
    <div class="error">⚠️ <?= $error ?></div>
  <?php endif; ?>
  
  <form method="POST">
    <div class="form-group">
      <label for="usuario">Usuario</label>
      <input type="text" name="usuario" id="usuario" required autocomplete="username">
    </div>

    <div class="form-group">
      <label for="contrasena">Contraseña</label>
      <input type="password" name="contrasena" id="contrasena" required autocomplete="current-password">
    </div>

    <input type="submit" name="login" value="Iniciar Sesión">
  </form>
  
  <div class="quick-access">
    <h4>Acceso Rápido</h4>
    <div class="quick-buttons">
      <button class="quick-btn" onclick="fillCredentials('oponix', '1234')">
        👨‍⚕️ Médico
      </button>
      <button class="quick-btn" onclick="fillCredentials('dotsbox', '1234')">
        🤖 DotsBox
      </button>
    </div>
  </div>
</div>

<div class="footer">
  © 2025 Sistema DOTS - Alvaro - Todos los derechos reservados
</div>

<script>
function fillCredentials(user, pass) {
  document.getElementById('usuario').value = user;
  document.getElementById('contrasena').value = pass;
}

if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker
      .register('./service-worker.js')   // ← ESTA ES LA RUTA CORRECTA
      .then(reg => console.log('SW registrado', reg))
      .catch(err => console.log('Error SW', err));
  });
}
</script>

</body>
</html>