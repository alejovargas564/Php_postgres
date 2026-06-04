<?php
require_once __DIR__ . '/../vendor/autoload.php';

// --- CONFIGURACIÓN DE POSTGRESQL (PDO) ---
$host = "tu_host_de_render";
$port = "5432";
$dbname = "tu_nombre_db";
$user = "tu_usuario";
$password = "tu_password";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Error en PostgreSQL: " . $e->getMessage());
}

// --- CONFIGURACIÓN DE MONGODB (Driver Oficial) ---
// NOTA: Usa la cadena que empieza por mongodb:// (versión 3.6+ en Atlas)
$mongoUri = "mongodb://usuario:password@shard01.mongodb.net:27017,shard02.mongodb.net:27017/?ssl=true&replicaSet=atlas-xxx&authSource=admin";

try {
    $mongoClient = new MongoDB\Client($mongoUri);
    $mongoCollection = $mongoClient->selectDatabase('academia')->selectCollection('estudiantes');
} catch (Exception $e) {
    $mongoError = $e->getMessage();
}

// --- LÓGICA DE OPERACIONES ---

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar'])) {
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];

    $guardadoPostgres = false;
    $guardadoMongo = false;

    // 1. Guardar en PostgreSQL
    try {
        $stmt = $pdo->prepare("INSERT INTO estudiantes (nombre, correo) VALUES (?, ?)");
        $stmt->execute([$nombre, $correo]);
        $guardadoPostgres = true;
    } catch (PDOException $e) {
        $mensaje .= "❌ Error en PostgreSQL: " . $e->getMessage() . " ";
    }

    // 2. Guardar en MongoDB
    try {
        if (isset($mongoCollection)) {
            $resultado = $mongoCollection->insertOne([
                'nombre' => $nombre,
                'correo' => $correo,
                'fecha_registro' => new MongoDB\BSON\UTCDateTime()
            ]);
            if ($resultado->getInsertedCount() > 0) {
                $guardadoMongo = true;
            }
        }
    } catch (Exception $e) {
        $mensaje .= "❌ Error en MongoDB: " . $e->getMessage();
    }

    // 3. Verificación de doble soporte
    if ($guardadoPostgres && $guardadoMongo) {
        $mensaje = "✅ Estudiante registrado exitosamente en AMBOS soportes (PostgreSQL y MongoDB).";
    } elseif ($guardadoPostgres) {
        $mensaje = "⚠ Guardado solo en PostgreSQL. Error en MongoDB.";
    }
}

// --- CONSULTA DE DATOS ---

// Consultar PostgreSQL
$estudiantesPg = $pdo->query("SELECT * FROM estudiantes")->fetchAll(PDO::FETCH_ASSOC);

// Consultar MongoDB
$estudiantesMg = [];
try {
    if (isset($mongoCollection)) {
        $estudiantesMg = $mongoCollection->find()->toArray();
    }
} catch (Exception $e) {
    // Error silencioso para la vista
}
?>
