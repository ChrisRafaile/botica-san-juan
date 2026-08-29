<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';
$usuario_id = $_SESSION['usuario_id'];

// Obtiene los pedidos del usuario
$sql = "SELECT p.id, p.fecha, p.total, d.producto_id, pr.nombre, d.cantidad, d.precio 
        FROM pedidos p 
        LEFT JOIN pedido_detalles d ON p.id = d.pedido_id
        LEFT JOIN productos pr ON d.producto_id = pr.id
        WHERE p.usuario_id = ?
        ORDER BY p.id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

$pedido_data = [];
while ($row = $result->fetch_assoc()) {
    $pedido_id = $row['id'];
    if (!isset($pedido_data[$pedido_id])) {
        $pedido_data[$pedido_id] = [
            'id' => $pedido_id,
            'fecha' => $row['fecha'],
            'total' => $row['total'],
            'detalles' => []
        ];
    }
    if ($row['producto_id']) {
        $pedido_data[$pedido_id]['detalles'][] = [
            'nombre' => $row['nombre'],
            'cantidad' => $row['cantidad'],
            'precio' => $row['precio']
        ];
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Botica San Juan</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #6f86d6, #48c6ef);
            padding: 20px;
            color: white;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #2196F3;
            padding: 10px;
        }
        .navbar .logo {
            height: 50px;
        }
        .navbar .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            background-color: #1e88e5;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .navbar .btn:hover {
            background-color: #1565c0;
        }
        .dashboard-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            color: black;
        }
        .pedido {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .pedido p {
            margin: 5px 0;
        }
        .pedido ul {
            list-style-type: none;
            padding: 0;
        }
        .pedido ul li {
            margin: 5px 0;
        }
        .chart-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <div class="navbar">
            <img src="../images/logo botica.jpg" alt="Logo de la Botica San Juan" class="logo">
            <a href="ver_consultas.php" class="btn">Ver Consultas</a>
        </div>
    </header>
    <div class="dashboard-container">
        <h1>Bienvenido al Dashboard</h1>
        <p>Esta página es solo para usuarios autenticados.</p>
        <h2>Historial de Pedidos</h2>
        <?php foreach ($pedido_data as $pedido): ?>
            <div class="pedido">
                <p>Pedido ID: <?php echo htmlspecialchars($pedido['id']); ?></p>
                <p>Fecha: <?php echo htmlspecialchars($pedido['fecha']); ?></p>
                <p>Total: S/<?php echo number_format($pedido['total'], 2); ?></p>
                <p><strong>Detalles del Pedido:</strong></p>
                <?php if (!empty($pedido['detalles'])): ?>
                    <ul>
                        <?php foreach ($pedido['detalles'] as $detalle): ?>
                            <li>Producto: <?php echo htmlspecialchars($detalle['nombre']); ?> - Cantidad: <?php echo $detalle['cantidad']; ?> - Precio: S/<?php echo number_format($detalle['precio'], 2); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No hay detalles disponibles para este pedido.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="chart-container">
        <canvas id="pedidoChart"></canvas>
    </div>
    <script>
        const ctx = document.getElementById('pedidoChart').getContext('2d');
        const pedidoData = <?php echo json_encode(array_values($pedido_data)); ?>;
        
        const labels = pedidoData.map(p => `Pedido ID: ${p.id} - ${p.fecha}`);
        const data = {
            labels: labels,
            datasets: [{
                label: 'Total Pagado (S/)',
                data: pedidoData.map(p => p.total),
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        };
        
        const config = {
            type: 'bar',
            data: data,
            options: {
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 90,
                            minRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'S/' + value;
                            }
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Historial de Pagos'
                    }
                }
            }
        };
        
        const pedidoChart = new Chart(ctx, config);
    </script>
</body>
</html>
