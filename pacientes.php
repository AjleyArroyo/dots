<?php
session_start();
if (!isset($_SESSION['me_id'])) {
    header("Location: login.php");
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

$result = $con->query("SELECT * FROM patient ORDER BY nombre ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pacientes - DOTS</title>
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
      padding: 20px;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
    }

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
      font-size: 32px;
    }

    .header p {
      color: #6c757d;
      font-size: 14px;
    }

    .actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

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

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .btn-secondary {
      background: white;
      color: #667eea;
      border: 2px solid #667eea;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .table-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }

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

    th:first-child {
      border-radius: 10px 0 0 0;
    }

    th:last-child {
      border-radius: 0 10px 0 0;
    }

    td {
      padding: 15px;
      border-bottom: 1px solid #eee;
      font-size: 14px;
      color: #333;
    }

    tr:hover {
      background-color: #f8f9fa;
    }

    tr:last-child td:first-child {
      border-radius: 0 0 0 10px;
    }

    tr:last-child td:last-child {
      border-radius: 0 0 10px 0;
    }

    .action-buttons {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
    }

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

    .btn-view {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      color: white;
    }

    .btn-edit {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
    }

    .btn-delete {
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
      color: white;
    }

    .btn-small:hover {
      transform: translateY(-2px);
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }

    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
      background: #e3f2fd;
      color: #1976d2;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #6c757d;
    }

    .empty-state svg {
      width: 120px;
      height: 120px;
      margin-bottom: 20px;
      opacity: 0.5;
    }

    @media (max-width: 768px) {
      .header {
        flex-direction: column;
        align-items: flex-start;
      }

      .header h1 {
        font-size: 24px;
      }

      .actions {
        width: 100%;
        flex-direction: column;
      }

      .btn {
        width: 100%;
        justify-content: center;
      }

      table {
        font-size: 12px;
      }

      th, td {
        padding: 10px 5px;
      }

      .action-buttons {
        flex-direction: column;
      }

      .btn-small {
        width: 100%;
        justify-content: center;
      }
    }

    /* Animación de carga */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .table-container {
      animation: fadeIn 0.5s ease;
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
      <a href="dashboard.php" class="btn btn-secondary">
        ⬅️ Volver al Dashboard
      </a>
      <a href="nuevo_paciente.php" class="btn btn-primary">
        ➕ Nuevo Paciente
      </a>
    </div>
  </div>

  <div class="table-container">
    <?php if ($result->num_rows > 0): ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre Completo</th>
            <th>Contacto</th>
            <th>Gabinete</th>
            <th>Huella</th>
            <th>Emergencias</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td><span class="badge">#<?= $row['paciente_id'] ?></span></td>
              <td>
                <strong><?= htmlspecialchars($row['nombre'] . ' ' . $row['apellido']) ?></strong>
              </td>
              <td>
                📧 <?= htmlspecialchars($row['correo']) ?><br>
                📱 <?= htmlspecialchars($row['telefono']) ?>
              </td>
              <td>
                <span class="badge">🏢 Gab. <?= $row['gabinete_id'] ?></span>
              </td>
              <td>
                <span class="badge">👆 ID: <?= $row['huella_id'] ?></span>
              </td>
              <td style="font-size: 12px;">
                1️⃣ <?= htmlspecialchars($row['emergencia1']) ?><br>
                2️⃣ <?= htmlspecialchars($row['emergencia2']) ?>
              </td>
              <td>
                <div class="action-buttons">
                  <a class="btn-small btn-view" href="ver_paciente.php?id=<?= $row['paciente_id'] ?>">
                    👁️ Ver
                  </a>
                  <a class="btn-small btn-edit" href="editar_paciente.php?id=<?= $row['paciente_id'] ?>">
                    ✏️ Editar
                  </a>
                  <a class="btn-small btn-delete" href="pacientes.php?delete_id=<?= $row['paciente_id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar a <?= htmlspecialchars($row['nombre']) ?>?')">
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
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
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