<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client as MongoClient;

$nom = trim($_POST['nom'] ?? '');
$tel = trim($_POST['tel'] ?? '');
$det = trim($_POST['det'] ?? '');

$pg_ok    = false;
$mongo_ok = false;
$mongo_id = null;

try {
    $pdo = new PDO(
        'pgsql:host=dpg-d8f392l8nd3s73fhgea0-a.oregon-postgres.render.com;dbname=sena_hsm6',
        'sena_hsm6_user',
        '1mxkuruzMoo6F6jLrkq73HqrPnTmCafx'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Agregar columna mongo_id si no existe
    $pdo->exec("ALTER TABLE aprendices ADD COLUMN IF NOT EXISTS mongo_id VARCHAR(60) DEFAULT NULL");

    // ── MongoDB Atlas ─────────────────────────────────────────
    $mongo_uri = getenv('MONGO_URI');
    if ($mongo_uri) {
        try {
            $mongo  = new MongoClient($mongo_uri);
            $col    = $mongo->estudiantes_db->estudiantes;
            $result = $col->insertOne([
                'nombre'   => $nom,
                'telefono' => $tel,
                'detalles' => $det,
                'fecha'    => new MongoDB\BSON\UTCDateTime(),
            ]);
            $mongo_id = (string) $result->getInsertedId();
            $mongo_ok = true;
        } catch (Exception $e) {
            // Mongo falló, continuamos solo con Postgres
        }
    }

    // ── Insertar en PostgreSQL ────────────────────────────────
    $stmt = $pdo->prepare(
        "INSERT INTO aprendices (nombre, telefono, detalles, mongo_id) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$nom, $tel, $det, $mongo_id]);
    $pg_ok = true;

    // ── Consultar tabla completa ──────────────────────────────
    $rows = $pdo->query("SELECT * FROM aprendices ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $pdo  = null;

} catch (PDOException $e) {
    die("<p style='color:red'>Error PostgreSQL: " . $e->getMessage() . "</p>");
}

// ── Mensaje de estado ─────────────────────────────────────────
if ($pg_ok && $mongo_ok) {
    $color = 'green';
    $msg   = '✓ Registro guardado en PostgreSQL y respaldado en MongoDB Atlas';
} else {
    $color = 'darkorange';
    $msg   = '⚠ Guardado en PostgreSQL. MongoDB no disponible o sin configurar.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <title>Resultado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    body { background: #0d0f14; color: #e8eaf0; font-family: 'Segoe UI', sans-serif; padding: 2rem; }
    .badge-pg  { background: rgba(74,222,128,.15); color: #4ade80; border: 1px solid rgba(74,222,128,.3); }
    .badge-mg  { background: rgba(34,211,238,.15); color: #22d3ee; border: 1px solid rgba(34,211,238,.3); }
    .badge-no  { background: rgba(248,113,113,.15); color: #f87171; border: 1px solid rgba(248,113,113,.3); }
    table { border-collapse: collapse; width: 100%; }
    th { background: #1e2130; color: #7c8099; font-size: .78rem; text-transform: uppercase; letter-spacing:.06em; padding: .6rem .9rem; border-bottom: 1px solid #2a2d38; }
    td { padding: .6rem .9rem; border-bottom: 1px solid #1e2130; font-size: .9rem; }
    tr:hover td { background: rgba(255,255,255,.02); }
    .card-dark { background: #161920; border: 1px solid #2a2d38; border-radius: 12px; overflow: hidden; }
    .chip { display:inline-flex; align-items:center; gap:4px; font-size:.72rem; padding:.2rem .55rem; border-radius:999px; font-weight:500; }
  </style>
</head>
<body>

<div style="max-width:780px; margin:0 auto;">

  <div style="background:<?= $color == 'green' ? 'rgba(74,222,128,.1)' : 'rgba(251,146,60,.1)' ?>;
              border:1px solid <?= $color == 'green' ? 'rgba(74,222,128,.3)' : 'rgba(251,146,60,.3)' ?>;
              color:<?= $color == 'green' ? '#86efac' : '#fdba74' ?>;
              border-radius:10px; padding:.85rem 1.1rem; margin-bottom:1.5rem; font-size:.92rem;">
    <?= $msg ?>
  </div>

  <div style="display:flex; gap:.75rem; margin-bottom:1.5rem;">
    <span class="chip badge-pg">● PostgreSQL <?= $pg_ok ? '✓' : '✗' ?></span>
    <span class="chip <?= $mongo_ok ? 'badge-mg' : 'badge-no' ?>">● MongoDB Atlas <?= $mongo_ok ? '✓' : '—' ?></span>
  </div>

  <div class="card-dark">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Nombre</th><th>Teléfono</th><th>Detalles</th><th>MongoDB</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $f): ?>
        <tr>
          <td><?= $f['id'] ?></td>
          <td><?= htmlspecialchars($f['nombre']) ?></td>
          <td><?= htmlspecialchars($f['telefono']) ?></td>
          <td><?= htmlspecialchars($f['detalles']) ?></td>
          <td>
            <?php if (!empty($f['mongo_id'])): ?>
              <span class="chip badge-mg">✓ <?= substr($f['mongo_id'], -6) ?></span>
            <?php else: ?>
              <span class="chip badge-no">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div style="margin-top:1.2rem;">
    <a href="../index.html" style="color:#4ade80; font-size:.88rem;">← Volver al formulario</a>
  </div>

</div>
</body>
</html>
