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
        header("Location: dashboard.php");
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
  <title>Login DOTS</title>
  <style>
    body {
      background-color: #f4f4f4;
      font-family: Arial, sans-serif;
    }
    .login-container {
      width: 300px;
      padding: 30px;
      background-color: white;
      margin: 100px auto;
      border-radius: 10px;
      box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #071f44;
    }
    input[type=text], input[type=password] {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      box-sizing: border-box;
      border: 1px solid #ccc;
      border-radius: 5px;
    }
    input[type=submit] {
      background-color: #071f44;
      color: white;
      padding: 10px;
      width: 100%;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    input[type=submit]:hover {
      background-color: #0a2d6a;
    }
    .error {
      color: red;
      text-align: center;
      font-size: 14px;
    }
  </style>
</head>
<body>

<div class="login-container">
  <h2>Acceso DOTS</h2>
  <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
  <form method="POST">
    <label for="usuario">Usuario</label>
    <input type="text" name="usuario" id="usuario" required>

    <label for="contrasena">Contraseña</label>
    <input type="password" name="contrasena" id="contrasena" required>

    <input type="submit" name="login" value="Iniciar Sesión">
  </form>
</div>
<script>
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
