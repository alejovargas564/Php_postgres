<?php
require_once __DIR__ . '/../vendor/autoload.php';

$mensaje = "";
$statusPg = false;
$statusMg = false;

// 1. Conexión MongoDB (dentro de try/catch para que no mate el script)
try {
    $mongoUri = getenv('MONGODB_URI'); // Verifica que en Render se llame igual
    if (!$mongoUri) throw new Exception("Falta MONGODB_URI");

    $mongoClient = new MongoDB\Client($mongoUri);
    $mongoCollection = $mongoClient->selectDatabase("estudiantes_db")->selectCollection("estudiantes");
} catch (Exception $e) {
    $errorMg = $e->getMessage();
}

// 2. Conexión PostgreSQL (Render inyecta DATABASE_URL automáticamente)
try {
    $pgUri = getenv('DATABASE_URL');
    $pdo = new PDO($pgUri);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    $errorPg = $e->getMessage();
}

// 3. Procesar Formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nom'] ?? '';
    $telefono = $_POST['tel'] ?? '';
    $detalles = $_POST['det'] ?? '';

    // Guardar en Postgres
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("INSERT INTO sugerencias (nombre, telefono, detalles) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $telefono, $detalles]);
            $statusPg = true;
        }
    } catch (Exception $e) { $mensaje .= "Error PG: " . $e->getMessage(); }

    // Guardar en Mongo
    try {
        if (isset($mongoCollection)) {
            $mongoCollection->insertOne(['nombre' => $nombre, 'tel' => $telefono, 'det' => $detalles]);
            $statusMg = true;
        }
    } catch (Exception $e) { $mensaje .= " Error MG: " . $e->getMessage(); }

    // Mensaje de éxito dual
    if ($statusPg && $statusMg) $mensaje = "✅ Guardado en ambos soportes.";
    else $mensaje = "⚠ Guardado parcial. PG: " . ($statusPg?'OK':'Fail') . " | MG: " . ($statusMg?'OK':'Fail');
}

// Mostrar resultado simple para evitar pantalla blanca
echo "<h1>$mensaje</h1><a href='../index.html'>Volver</a>";
