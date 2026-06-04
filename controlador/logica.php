<?php
// ── DEBUG TEMPORAL ────────────────────────────────────────────
$mongo_uri = getenv('MONGO_URI') ?: $_ENV['MONGO_URI'] ?? $_SERVER['MONGO_URI'] ?? '';
echo "<pre>";
echo "MONGO_URI via getenv: " . var_export(getenv('MONGO_URI'), true) . "\n";
echo "MONGO_URI via _ENV: "   . var_export($_ENV['MONGO_URI'] ?? 'vacío', true) . "\n";
echo "MONGO_URI via _SERVER: ". var_export($_SERVER['MONGO_URI'] ?? 'vacío', true) . "\n";
echo "</pre>";
die();
?>
