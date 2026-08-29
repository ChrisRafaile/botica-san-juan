<?php
session_start();
include 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png'];
    $filename = $_FILES['foto_perfil']['name'];
    $filetype = pathinfo($filename, PATHINFO_EXTENSION);

    if (in_array($filetype, $allowed)) {
        // Obtiene la información del usuario para eliminar la foto anterior
        $sql = "SELECT foto_perfil FROM usuarios WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Elimina la foto anterior si existe
        if ($user['foto_perfil']) {
            $old_file = "../uploads/" . $user['foto_perfil'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        // Guarda la nueva foto
        $new_filename = "perfil_" . $usuario_id . "." . $filetype;
        $destination = "../uploads/" . $new_filename;
        if (move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $destination)) {
            $sql = "UPDATE usuarios SET foto_perfil = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $new_filename, $usuario_id);
            if ($stmt->execute()) {
                header("Location: profile.php");
                exit();
            } else {
                echo "Error al actualizar la base de datos.";
            }
            $stmt->close();
        } else {
            echo "Error al mover el archivo.";
        }
    } else {
        echo "Tipo de archivo no permitido. Solo se permiten archivos JPG, JPEG y PNG.";
    }
} else {
    echo "Error al subir el archivo.";
}
$conn->close();
?>
