<?php
$conn = new mysqli("localhost", "root", "", "dots");
if ($conn->connect_error) {
    die("❌ Conexión fallida: " . $conn->connect_error);
}

$result = $conn->query("SELECT * FROM usuarios");

echo "<h2>Usuarios Registrados</h2>";
echo "<table border='1' cellpadding='8'><tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Apellido</th>
        <th>ID Huella</th>
        <th>Gabinete</th>
        <th>Fecha</th>
      </tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>
            <td>{$row['id']}</td>
            <td>{$row['nombre']}</td>
            <td>{$row['apellido']}</td>
            <td>{$row['idhuella']}</td>
            <td>{$row['gabinete']}</td>
            <td>{$row['fecha_registro']}</td>
          </tr>";
}

echo "</table>";

$conn->close();
?>
