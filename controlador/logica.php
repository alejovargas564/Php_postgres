<?php
// Reportar errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$mensaje = "";
$statusPg = false;
$statusMg = false;

// --- 1. CONEXIÓN POSTGRESQL ---
try {
    $pgUri = getenv('DATABASE_URL');
    if (!$pgUri) {
        throw new Exception("La variable DATABASE_URL no existe en Render.");
    }
    
    // Convertir la URL de Render al formato DSN que exige el driver PDO de PHP
    $dbopts = parse_url($pgUri);
    if (!$dbopts || !isset($dbopts["host"])) {
        throw new Exception("El formato de DATABASE_URL es inválido.");
    }
    
    $dsn = "pgsql:host=" . $dbopts["host"] . ";port=" . ($dbopts["port"] ?? 5432) . ";dbname=" . ltrim($dbopts["path"], '/') . ";sslmode=require";
    
    $pdo = new PDO($dsn, $dbopts["user"], $dbopts["pass"]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    $mensaje .= "❌ Error Conexión PG: " . $e->getMessage() . "<br>";
}

// --- 2. CONEXIÓN MONGODB ---
try {
    $mongoUri = getenv('MONGODB_URI');
    if (!$mongoUri) {
        throw new Exception("La variable MONGODB_URI no existe en Render.");
    }
    $mongoClient = new MongoDB\Client($mongoUri);
    // Verificar conexión real
    $mongoClient->listDatabases(); 
    $mongoCollection = $mongoClient->selectDatabase("estudiantes_db")->selectCollection("estudiantes");
} catch (Exception $e) {
    $mensaje .= "❌ Error Conexión MG: " . $e->getMessage() . "<br>";
}

// --- 3. PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = isset($_POST['nom']) ? strip_tags(trim($_POST['nom'])) : '';
    $telefono = isset($_POST['tel']) ? strip_tags(trim($_POST['tel'])) : '';
    $detalles = isset($_POST['det']) ? strip_tags(trim($_POST['det'])) : '';

    // A. Guardar en Postgres
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sugerencias (nombre, telefono, detalles) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $telefono, $detalles]);
            $statusPg = true;
        } catch (Exception $e) {
            $mensaje .= "❌ Error al Insertar PG: " . $e->getMessage() . "<br>";
        }
    }

    // B. Guardar en Mongo
    if (isset($mongoCollection)) {
        try {
            $mongoCollection->insertOne([
                'nombre' => $nombre, 
                'tel' => $telefono, 
                'det' => $detalles,
                'fecha' => date("Y-m-d H:i:s")
            ]);
            $statusMg = true;
        } catch (Exception $e) {
            $mensaje .= "❌ Error al Insertar MG: " . $e->getMessage() . "<br>";
        }
    }

    // --- 4. RESULTADO FINAL ---
    if ($statusPg && $statusMg) {
        $resultadoFinal = "✅ ¡ÉXITO! Guardado en ambos soportes correctamente.";
    } else {
        $resultadoFinal = "⚠ GUARDADO PARCIAL:<br>";
        $resultadoFinal .= "PostgreSQL: " . ($statusPg ? "✅ OK" : "❌ FALLÓ") . "<br>";
        $resultadoFinal .= "MongoDB: " . ($statusMg ? "✅ OK" : "❌ FALLÓ") . "<br>";
    }
} else {
    $resultadoFinal = "No se recibieron datos del formulario.";
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultado del Registro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <div class="card shadow">
        <div class="card-body text-center">
            <h2 class="card-title"><?php echo $resultadoFinal; ?></h2>
            <div class="alert alert-light text-start border mt-4">
                <strong>Detalle de Errores (si los hay):</strong><br>
                <?php echo $mensaje ?: "Ninguno. Todo operó según las conexiones disponibles."; ?>
            </div>
            <hr>
            <a href="../index.html" class="btn btn-primary">Volver al Formulario</a>
        </div>
    </div>
</body>
</html>
