<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

class EstadisticasController {
    private $db;

    public function __construct() {
        $this->db = new AdminDatabase();
    }

    // obtiene todas las estadisticas para el dashboard
    public function getEstadisticasGenerales() {
        try {
            // query grande para traer todos los conteos de una vez
            $sql = "SELECT 
                        (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_usuarios,
                        (SELECT COUNT(*) FROM peliculas WHERE activo = 1) as total_peliculas,
                        (SELECT COUNT(*) FROM resenas WHERE activo = 1) as total_resenas,
                        (SELECT COUNT(*) FROM generos WHERE activo = 1) as total_generos,
                        (SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 1 MONTH)) as usuarios_nuevos_mes,
                        (SELECT COALESCE(AVG(puntuacion), 0) FROM resenas WHERE activo = 1) as puntuacion_promedio,
                        (SELECT COALESCE(SUM(likes), 0) FROM resenas WHERE activo = 1) as total_likes";

            $stats = $this->db->fetchOne($sql);

            // top peliculas con mejor puntuacion
            $topPeliculas = $this->db->fetchAll("
                SELECT p.titulo, p.director, p.ano_lanzamiento,
                       COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
                       COUNT(r.id) as total_resenas
                FROM peliculas p
                LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = 1
                WHERE p.activo = 1
                GROUP BY p.id
                HAVING total_resenas > 0
                ORDER BY puntuacion_promedio DESC, total_resenas DESC
                LIMIT 5
            ");

            // actividad de hoy sumando registros de todas las tablas
            $actividadHoy = $this->db->fetchOne("
                SELECT COUNT(*) as actividad_hoy
                FROM (
                    SELECT fecha_registro as fecha FROM usuarios WHERE DATE(fecha_registro) = CURDATE()
                    UNION ALL
                    SELECT fecha_creacion as fecha FROM peliculas WHERE DATE(fecha_creacion) = CURDATE()
                    UNION ALL
                    SELECT fecha_resena as fecha FROM resenas WHERE DATE(fecha_resena) = CURDATE()
                ) actividad
            ");

            echo json_encode([
                'success' => true,
                'data' => [
                    'totales' => $stats,
                    'top_peliculas' => $topPeliculas,
                    'actividad_hoy' => $actividadHoy['actividad_hoy']
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

$controller = new EstadisticasController();
$controller->getEstadisticasGenerales();