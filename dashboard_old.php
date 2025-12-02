<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: index.php");
    exit();
}
include("dbconnection.php");

$sql = "SELECT * FROM gabinete LIMIT 1";
$res = $con->query($sql);
$gabinete = $res->fetch_assoc();

$temperatura = $gabinete['sensor_temp_id'];
$humedad = $gabinete['sensor_hum_id'];
$fan_estado = $gabinete['fan_estado'];
$uv_estado = $gabinete['uv_estado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard DOTS</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background-color: #f5f5f5;
      display: flex;
    }

    /* === SIDEBAR IZQUIERDA === */
    .left-sidebar {
      width: 220px;
      background-color: #071f44;
      color: white;
      height: 100vh;
      padding: 20px 10px;
      display: flex;
      flex-direction: column;
      align-items: center;
      position: fixed;
      left: 0;
      top: 0;
      z-index: 999;
    }

    .left-sidebar h3 {
      color: white;
      margin-bottom: 30px;
      font-size: 18px;
    }

    .left-sidebar a {
      text-decoration: none;
      color: white;
      background-color: #0a2d6a;
      width: 90%;
      text-align: center;
      padding: 10px;
      margin-bottom: 10px;
      border-radius: 5px;
      display: block;
    }

    .left-sidebar a:hover {
      background-color: #0e3b8e;
    }

    .calendar-box {
      background-color: white;
      color: #333;
      font-size: 14px;
      width: 90%;
      margin-bottom: 15px;
      border-radius: 5px;
      padding: 10px;
      height: 130px;
      overflow: auto;
    }

    .main-content {
      margin-left: 240px;
      flex: 1;
      padding: 20px;
    }

    .logout {
      margin-top: auto;
    }

    h2 {
      color: #071f44;
      text-align: center;
      margin-bottom: 30px;
    }

    .circles-container {
      display: flex;
      justify-content: center;
      gap: 40px;
      flex-wrap: wrap;
    }

    .circle-group {
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 180px;
    }

    .circle {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      font-size: 22px;
      color: white;
      font-weight: bold;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      transition: background-color 0.3s, color 0.3s;
    }

    .temp-circle { background-color: #e84118; }
    .hum-circle { background-color: #00a8ff; }
    .fan-circle {
      background-color: <?php echo $fan_estado ? 'white' : 'black'; ?>;
      color: <?php echo $fan_estado ? 'black' : 'white'; ?>;
      border: 2px solid #333;
    }
    .uv-circle {
      background-color: <?php echo $uv_estado ? '#f1c40f' : '#2c3e50'; ?>;
      color: <?php echo $uv_estado ? 'black' : 'white'; ?>;
      border: 2px solid #999;
    }

    .label {
      margin-top: 10px;
      font-size: 16px;
    }

    .btn {
      margin-top: 10px;
      width: 150px;
      padding: 8px 15px;
      background-color: #071f44;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .btn:hover {
      background-color: #0a2d6a;
    }

    /* === SIDEBAR DERECHA DESLIZANTE === */
    .sidebar {
      position: fixed;
      top: 0;
      right: 0;
      width: 0;
      height: 100vh;
      overflow-x: hidden;
      overflow-y: auto;
      background-color: #fff;
      border-left: 2px solid #ccc;
      transition: width 0.3s ease;
      z-index: 1000;
      padding: 0 0 0 20px;
    }

    .sidebar.visible {
      width: 330px;
      padding: 20px;
    }

    .sidebar h3 {
      color: #071f44;
      margin-bottom: 10px;
    }

    .paciente {
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 1px solid #eee;
    }

    .dispensacion {
      background-color: #f7f9fa;
      padding: 6px 10px;
      margin: 4px 0;
      border-left: 4px solid #00a8ff;
      font-size: 14px;
      color: #333;
    }

    .sidebar-hover-zone {
      position: fixed;
      top: 0;
      right: 0;
      width: 20px;
      height: 100vh;
      z-index: 1100;
    }
  </style>
</head>
<body>

<!-- SIDEBAR IZQUIERDA -->
<div class="left-sidebar">
  <h3>DOTS Menu</h3>
  <a href="pacientes.php">Pacientes</a>
  <div class="calendar-box">
    <strong>Calendario</strong><br>
    <em>(Reservado)</em>
  </div>
  <a href="#">Alertas</a>
  <a href="logout.php" class="logout">Cerrar sesión</a>
</div>

<!-- CONTENIDO PRINCIPAL -->
<div class="main-content">
  <h2>Dashboard DOTS – Gabinete <?php echo $gabinete['nombre']; ?></h2>

  <div class="circles-container">
    <div class="circle-group">
      <div class="circle temp-circle"><?php echo $temperatura; ?>°C</div>
      <div class="label">Temperatura</div>
    </div>
    <div class="circle-group">
      <div class="circle hum-circle"><?php echo $humedad; ?>%</div>
      <div class="label">Humedad</div>
    </div>
    <div class="circle-group">
      <div class="circle fan-circle"><?php echo $fan_estado ? 'ON' : 'OFF'; ?></div>
      <div class="label">Ventilador</div>
      <form method="POST" action="actualizar_gabinete.php">
        <input type="hidden" name="gabinete_id" value="<?php echo $gabinete['gabinete_id']; ?>">
        <input type="hidden" name="accion" value="fan">
        <input class="btn" type="submit" value="<?php echo $fan_estado ? 'Apagar' : 'Encender'; ?> ventilador">
      </form>
    </div>
    <div class="circle-group">
      <div class="circle uv-circle"><?php echo $uv_estado ? 'ON' : 'OFF'; ?></div>
      <div class="label">Luz UV</div>
      <form method="POST" action="actualizar_gabinete.php">
        <input type="hidden" name="gabinete_id" value="<?php echo $gabinete['gabinete_id']; ?>">
        <input type="hidden" name="accion" value="uv">
        <input class="btn" type="submit" value="<?php echo $uv_estado ? 'Apagar' : 'Encender'; ?> luz UV">
      </form>
    </div>
  </div>
</div>

<!-- SIDEBAR DERECHA -->
<div class="sidebar-hover-zone" id="hoverZone"></div>
<div class="sidebar" id="sidebar">
  <h3>Próximas dispensaciones</h3>
  <?php
  $resPacientes = $con->query("SELECT * FROM patient");
  while ($p = $resPacientes->fetch_assoc()):
  ?>
    <div class="paciente">
      <strong><?php echo $p['nombre'].' '.$p['apellido']; ?></strong>
      <?php
      $pid = $p['paciente_id'];
      $resDisp = $con->query(
        "SELECT d.*, m.nombre AS medicamentonombre
         FROM dispensacionprogramada d
         JOIN medicine m ON d.medicamento_id = m.medicine_id
         WHERE d.paciente_id=$pid AND d.estado='Pending' AND d.fecha>=CURDATE()
         ORDER BY d.fecha ASC, d.hora ASC
         LIMIT 3"
      );
      if ($resDisp->num_rows > 0) {
        while ($d = $resDisp->fetch_assoc()) {
          echo "<div class='dispensacion'>{$d['fecha']} {$d['hora']} — {$d['medicamentonombre']} ({$d['dosis']})</div>";
        }
      } else {
        echo "<div class='dispensacion'>Sin próximas dispensaciones</div>";
      }
      ?>
    </div>
  <?php endwhile; ?>
</div>

<script>
  const sidebar = document.getElementById("sidebar");
  const hoverZone = document.getElementById("hoverZone");

  hoverZone.addEventListener("mouseenter", () => {
    sidebar.classList.add("visible");
  });

  sidebar.addEventListener("mouseleave", () => {
    sidebar.classList.remove("visible");
  });
</script>

</body>
</html>
