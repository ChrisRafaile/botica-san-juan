<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../php/login.php");
    exit();
}

include '../php/config.php';

$usuario_id = $_SESSION['usuario_id'];
$total = isset($_SESSION['total']) ? $_SESSION['total'] : 0; // Obtiene el total desde la sesión

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Botica San Juan</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: linear-gradient(135deg, #6f86d6, #48c6ef);
            font-family: 'Roboto', sans-serif;
        }
        .checkout-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }
        .checkout-container h2 {
            margin-bottom: 1rem;
            color: #333;
        }
        .checkout-container label {
            margin-bottom: 0.5rem;
            color: #333;
            display: block;
        }
        .checkout-container input {
            padding: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
            width: 100%;
        }
        .checkout-container button {
            padding: 0.5rem;
            border: none;
            border-radius: 5px;
            background-color: #1e88e5;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
        }
        .checkout-container button:hover {
            background-color: #1565c0;
        }
        .total-amount {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-align: center;
        }
        .card-type {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1rem;
        }
        .card-type img {
            width: 40px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <h2>Checkout</h2>
        <form action="confirmation.php" method="POST">
            <label for="address">Dirección de envío:</label>
            <input type="text" id="address" name="address" required>

            <label for="card_number">Número de tarjeta:</label>
            <input type="text" id="card_number" name="card_number" required oninput="detectCardType(this.value)">

            <div class="card-type">
                <img id="card_type_image" src="" alt="Card Type">
            </div>

            <label for="cvv">CVV:</label>
            <input type="text" id="cvv" name="cvv" required>

            <label for="expiry_date">Fecha de expiración (MM/AA):</label>
            <input type="month" id="expiry_date" name="expiry_date" required>

            <div class="total-amount">Total a pagar: S/<?php echo number_format($total, 2); ?></div>

            <button type="submit" onclick="return confirm('¿Está seguro de que desea proceder con el pago?')">Confirmar Pago</button>
        </form>
    </div>
    <script>
        function detectCardType(number) {
            const cardTypeImage = document.getElementById('card_type_image');
            if (/^4/.test(number)) {
                cardTypeImage.src = '../images/footer-visa.svg';
            } else if (/^5[1-5]/.test(number)) {
                cardTypeImage.src = '../images/footer-mastercard.svg';
            } else if (/^3[47]/.test(number)) {
                cardTypeImage.src = '../images/footer-amex.svg';
            } else if (/^6/.test(number)) {
                cardTypeImage.src = '../images/footer-oh.svg';
            } else {
                cardTypeImage.src = '';
            }
        }
    </script>
</body>
</html>
