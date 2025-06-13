<?php
/**
 * Configuración CORS para CineFan API
 * Permite peticiones desde la aplicación Android y navegadores web
 */

// Headers CORS básicos
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Max-Age: 86400'); // 24 horas

// Manejar peticiones preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Headers adicionales de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Header de tipo de contenido por defecto
header('Content-Type: application/json; charset=utf-8');
?>