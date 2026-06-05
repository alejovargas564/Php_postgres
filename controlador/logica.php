<?php
// Reportar errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$mensaje = "";
$statusPg = false;
$statusMg = false;

$registrosPostgres = [];
$registrosMongo = [];

// --- 1. CONEXIÓN POSTGRESQL ---
try {
    $pgUri = getenv('DATABASE_URL');
    if (!$pgUri) {
        throw new Exception("La variable DATABASE_URL no existe en Render.");
    }
    
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
    
    // CONFIGURACIÓN CRUCIAL: Forzamos la desactivación del chequeo estricto de topología de red
    $options = [
        'connectTimeoutMS' => 10000,
        'serverSelectionTimeoutMS' => 10000,
        'tls' => true,
        'tlsAllowInvalidCertificates' => true // Evita bloqueos de certificados en el contenedor local
    ];
    
    $mongoClient = new MongoDB\Client($mongoUri, $options);
    
    // Seleccionar colección directamente sin listar bases de datos para evitar bloqueos de DNS preliminares
    $mongoCollection = $mongoClient->selectDatabase("estudiantes_db")->selectCollection("estudiantes");
    
    // Forzar una operación ligera para validar si realmente conectó
    $mongoCollection->findOne([]); 
    $statusMg = true;
} catch (Exception $e) {
    $mensaje .= "❌ Error Conexión MG: " . $e->getMessage() . "<br>";
}

// --- 3. PROCESAR INSERCIÓN (SI VIENE POR POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = isset($_POST['nom']) ? strip_tags(trim($_POST['nom'])) : '';
    $telefono = isset($_POST['tel']) ? strip_tags(trim($_POST['tel'])) : '';
    $detalles = isset($_POST['det']) ? strip_tags(trim($_POST['det'])) : '';

    if (!empty($nombre)) {
        // Guardar en Postgres
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO sugerencias (nombre, telefono, detalles) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $telefono, $detalles]);
                $statusPg = true;
            } catch (Exception $e) {
                $mensaje .= "❌ Error al Insertar PG: " . $e->getMessage() . "<br>";
            }
        }

        // Guardar en Mongo
        if ($statusMg && isset($mongoCollection)) {
            try {
                $mongoCollection->insertOne([
                    'nombre' => $nombre, 
                    'tel' => $telefono, 
                    'det' => $detalles,
                    'fecha' => date("Y-m-d H:i:s")
                ]);
            } catch (Exception $e) {
                $mensaje .= "❌ Error al Insertar MG: " . $e->getMessage() . "<br>";
            }
        }
    }
}

// --- 4. LEER REGISTROS PARA LAS TABLAS ---
// Leer de PostgreSQL
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT id, nombre, telefono, detalles FROM sugerencias ORDER BY id DESC");
        $registrosPostgres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $mensaje .= "❌ Error al Consultar PG: " . $e->getMessage() . "<br>";
    }
}

// Leer de MongoDB
if ($statusMg && isset($mongoCollection)) {
    try {
        $cursor = $mongoCollection->find([], ['limit' => 10, 'sort' => ['fecha' => -1]]);
        $registrosMongo = $cursor->toArray();
    } catch (Exception $e) {
        $mensaje .= "❌ Error al Consultar MG: " . $e->getMessage() . "<br>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualización de Registros</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .table-container { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Panel de Almacenamiento Colectivo</h2>
        <a href="../index.html" class="btn btn-outline-primary">← Volver al Formulario</a>
    </div>

    <?php if (!empty($mensaje) && !$statusMg): ?>
        <div class="alert alert-danger shadow-sm mb-4">
            <strong>Estado de las Conexiones:</strong><br>
            <div class="mt-2 small"><?php echo $mensaje; ?></div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-11 col-xl-6 mx-auto">
            <div class="table-container">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                    <h4 class="text-primary mb-0">PostgreSQL (Relacional)</h4>
                    <span class="badge bg-success">Activo</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Teléfono</th>
                                <th>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($registrosPostgres)): ?>
                                <?php foreach ($registrosPostgres as $row): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['id']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($row['telefono']); ?></td>
                                        <td><?php echo htmlspecialchars($row['detalles']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No hay registros en la tabla.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-11 col-xl-6 mx-auto">
            <div class="table-container">
                <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
                    <h4 class="text-success mb-0">MongoDB Atlas (NoSQL)</h4>
                    <span class="badge bg-<?php echo $statusMg ? 'success' : 'danger'; ?>">
                        <?php echo $statusMg ? 'Sincronizado' : 'Desconectado'; ?>
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-success">
                            <tr>
                                <th>Nombre</th>
                                <th>Teléfono</th>
                                <th>Detalles</th>
                                <th>Fecha Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($statusMg && !empty($registrosMongo)): ?>
                                <?php foreach ($registrosMongo as $doc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['nombre'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($doc['tel'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($doc['det'] ?? ''); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($doc['fecha'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        <small><?php echo $statusMg ? 'Conectado. No hay documentos guardados aún.' : 'Esperando sincronización de servicio...'; ?></small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
