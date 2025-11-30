<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Huella y Visualización</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 20px;
        }
        h2 { color: #b50000; margin-bottom: 20px; }
        table {
            margin: 0 auto;
            border-collapse: collapse;
            width: 90%;
            background-color: #444;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ccc;
            text-align: center;
        }
        th { background-color: #b50000; }
        button {
            background-color: #b50000;
            color: white;
            font-size: 16px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px;
        }
        button:hover { background-color: #7a0000; }
        .form-container {
            background-color: #555;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: inline-block;
        }
        input[type="text"], input[type="number"] {
            width: 200px;
            padding: 10px;
            margin: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #fff;
            color: #333;
        }
        form {
            display: inline-block;
            text-align: left;
            background-color: #444;
            padding: 20px;
            border-radius: 8px;
        }
        .table-container {
            background-color: #444;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        iframe {
            border: none;
            width: 90%;
            height: 100px;
            margin-top: 10px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

    <h2>📝 Registrar Usuario y Huella</h2>
    <div class="form-container">
        <form action="" method="get">
            <label>Nombre:</label><br>
            <input type="text" name="nombre" required><br><br>

            <label>Apellido:</label><br>
            <input type="text" name="apellido" required><br><br>

            <label>ID de Huella (ej. 4):</label><br>
            <input type="number" name="id" required><br><br>

            <input type="submit" value="Enviar al ESP32">
        </form>
    </div>

    <?php
    if (isset($_GET['nombre']) && isset($_GET['apellido']) && isset($_GET['id'])) {
        $nombre = urlencode($_GET['nombre']);
        $apellido = urlencode($_GET['apellido']);
        $id = intval($_GET['id']);

        $esp_ip = "192.168.248.112"; // AJUSTA esta IP según la del ESP32

        $url = "http://$esp_ip/enroll?nombre=$nombre&apellido=$apellido&id=$id";

        echo "<p>🔄 Enviando a ESP32: <a href='$url' target='_blank'>$url</a></p>";
        echo "<iframe src='$url'></iframe>";
    }
    ?>

    <div class="table-container">
        <h2>💡 Control del Relé (GPIO 15)</h2>
        <button onclick="toggleRelay('on')">Encender Relé</button>
        <button onclick="toggleRelay('off')">Apagar Relé</button>
        <p id="estado_rele">Estado actual: desconocido</p>
    </div>

    <div class="table-container">
        <h2>👤 Usuarios Registrados</h2>
        <?php
        $conn = new mysqli("localhost", "root", "", "dots");
        if ($conn->connect_error) {
            die("❌ Error de conexión: " . $conn->connect_error);
        }

        $result = $conn->query("SELECT * FROM usuarios ORDER BY id DESC");
        echo "<table><tr>
                <th>ID</th><th>Nombre</th><th>Apellido</th><th>ID Huella</th><th>Gabinete</th><th>Fecha</th>
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
        ?>
    </div>

    <div class="table-container">
        <h2>🌡 Registros de Temperatura y Humedad</h2>
        <?php
        $result2 = $conn->query("SELECT * FROM registro ORDER BY id DESC LIMIT 20");
        echo "<table><tr>
                <th>ID</th><th>Temp (°C)</th><th>Humedad (%)</th><th>ID Huella</th><th>Gabinete</th><th>Fecha</th>
              </tr>";
        while ($row = $result2->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['temperatura']}</td>
                    <td>{$row['humedad']}</td>
                    <td>{$row['idhuella']}</td>
                    <td>{$row['ngabinete']}</td>
                    <td>{$row['disphorafecha']}</td>
                  </tr>";
        }
        $conn->close();
        echo "</table>";
        ?>
    </div>

    <script>
        function toggleRelay(state) {
            const esp_ip = "192.168.248.112"; // IP del ESP32
            fetch(`http://${esp_ip}/toggle_relay?state=${state}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById("estado_rele").innerText = "Estado actual: " + data;
                })
                .catch(error => {
                    document.getElementById("estado_rele").innerText = "⚠️ Error de conexión con el ESP32";
                });
        }
    </script>

</body>
</html>
