<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar la URI desde la variable de entorno de Render
$mongoUri = getenv('MONGODB_URI');

try {
    // Si no encuentra la variable de entorno, fallará con un mensaje claro
    if (!$mongoUri) {
        throw new Exception("La variable MONGODB_URI no está configurada en el servidor.");
    }

    $mongoClient = new MongoDB\Client($mongoUri);
    
    // Especificamos la base de datos que mencionaste en tu cadena
    $dbName = "estudiantes_db"; 
    $mongoCollection = $mongoClient->selectDatabase($dbName)->selectCollection('estudiantes');
    
    // Prueba rápida de conexión
    $mongoClient->listDatabases(); 

} catch (Exception $e) {
    // Esto te dirá exactamente qué falla: si es el DNS (SRV) o la contraseña
    error_log("Error de conexión MongoDB: " . $e->getMessage());
    $errorConexionMongo = $e->getMessage();
}

// ... Resto de tu lógica de guardado (Postgres y Mongo) ...
