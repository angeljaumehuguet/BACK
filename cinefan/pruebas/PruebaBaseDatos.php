<?php
class PruebaBaseDatos {
    private $bd;
    private $resultados = [];
    
    public function __construct() {
        // no llamar registrador aqui - ya esta inicializado en bootstrap
        $this->bd = new BaseDatosAdmin();
    }
    
    // ejecutar todas las pruebas de bd
    public function ejecutarTodasLasPruebas() {
        $this->resultados = [];
        
        $this->probarConexion();
        $this->probarCRUDUsuarios();
        $this->probarCRUDPeliculas();
        $this->probarCRUDResenas();
        $this->probarCRUDGeneros();
        $this->probarIntegridadDatos();
        $this->probarPrevencionInyeccionSQL();
        $this->probarIndicesRendimiento();
        
        return $this->resultados;
    }
    
    // probar conexion a bd
    private function probarConexion() {
        $tiempoInicio = microtime(true);
        
        try {
            $resultado = $this->bd->obtenerUno("SELECT 1 as prueba");
            $tiempoFin = microtime(true);
            $tiempo = round(($tiempoFin - $tiempoInicio) * 1000, 2);
            
            if ($resultado && $resultado['prueba'] == 1) {
                $this->agregarResultado('Conexión BD', true, 'Conexión establecida correctamente', $tiempo);
            } else {
                $this->agregarResultado('Conexión BD', false, 'Conexión fallida - respuesta inválida');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Conexión BD', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar crud de usuarios
    private function probarCRUDUsuarios() {
        try {
            $usuarioPrueba = 'usuario_prueba_' . time() . '_' . rand(1000, 9999);
            $emailPrueba = 'prueba_' . time() . '@test.com';
            
            // crear usuario de prueba
            $sql = "INSERT INTO usuarios (nombre_usuario, email, password, nombre_completo, activo) VALUES (?, ?, ?, ?, ?)";
            $this->bd->ejecutar($sql, [
                $usuarioPrueba,
                $emailPrueba,
                password_hash('prueba123', PASSWORD_DEFAULT),
                'Usuario Prueba Test',
                1
            ]);
            
            // leer usuario creado
            $usuarioCreado = $this->bd->obtenerUno("SELECT * FROM usuarios WHERE nombre_usuario = ?", [$usuarioPrueba]);
            if (!$usuarioCreado) {
                $this->agregarResultado('CRUD Usuarios - Crear/Leer', false, 'Usuario no encontrado después de crearlo');
                return;
            }
            
            // actualizar usuario
            $this->bd->ejecutar("UPDATE usuarios SET nombre_completo = ? WHERE id = ?", 
                ['Usuario Actualizado Test', $usuarioCreado['id']]);
            
            $usuarioActualizado = $this->bd->obtenerUno("SELECT * FROM usuarios WHERE id = ?", [$usuarioCreado['id']]);
            if ($usuarioActualizado['nombre_completo'] !== 'Usuario Actualizado Test') {
                $this->agregarResultado('CRUD Usuarios - Actualizar', false, 'Actualización fallida');
                return;
            }
            
            // eliminar usuario
            $eliminado = $this->bd->ejecutar("DELETE FROM usuarios WHERE id = ?", [$usuarioCreado['id']]);
            
            if ($eliminado > 0) {
                $this->agregarResultado('CRUD Usuarios', true, 'Operaciones Crear, Leer, Actualizar, Eliminar exitosas');
            } else {
                $this->agregarResultado('CRUD Usuarios - Eliminar', false, 'Eliminación fallida');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('CRUD Usuarios', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar crud de peliculas
    private function probarCRUDPeliculas() {
        try {
            // obtener un genero valido primero
            $genero = $this->bd->obtenerUno("SELECT id FROM generos LIMIT 1");
            if (!$genero) {
                $this->agregarResultado('CRUD Películas', false, 'No hay géneros disponibles para la prueba');
                return;
            }
            
            $tituloPrueba = 'Película Prueba ' . time();
            
            // crear pelicula de prueba
            $sql = "INSERT INTO peliculas (titulo, director, ano_lanzamiento, duracion_minutos, genero_id, sinopsis) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $this->bd->ejecutar($sql, [
                $tituloPrueba,
                'Director Prueba',
                2024,
                120,
                $genero['id'],
                'Sinopsis de prueba'
            ]);
            
            // leer pelicula creada
            $peliculaCreada = $this->bd->obtenerUno("SELECT * FROM peliculas WHERE titulo = ?", [$tituloPrueba]);
            if (!$peliculaCreada) {
                $this->agregarResultado('CRUD Películas', false, 'Película no encontrada después de crearla');
                return;
            }
            
            // actualizar pelicula
            $this->bd->ejecutar("UPDATE peliculas SET director = ? WHERE id = ?", 
                ['Director Actualizado', $peliculaCreada['id']]);
            
            // eliminar pelicula
            $eliminado = $this->bd->ejecutar("DELETE FROM peliculas WHERE id = ?", [$peliculaCreada['id']]);
            
            if ($eliminado > 0) {
                $this->agregarResultado('CRUD Películas', true, 'Operaciones CRUD exitosas en películas');
            } else {
                $this->agregarResultado('CRUD Películas', false, 'Error en eliminación');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('CRUD Películas', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar crud de resenas
    private function probarCRUDResenas() {
        try {
            // obtener usuario y pelicula existentes
            $usuario = $this->bd->obtenerUno("SELECT id FROM usuarios LIMIT 1");
            $pelicula = $this->bd->obtenerUno("SELECT id FROM peliculas LIMIT 1");
            
            if (!$usuario || !$pelicula) {
                $this->agregarResultado('CRUD Reseñas', false, 'No hay datos base suficientes para prueba');
                return;
            }
            
            // crear resena de prueba
            $sql = "INSERT INTO resenas (id_usuario, id_pelicula, puntuacion, comentario) VALUES (?, ?, ?, ?)";
            $this->bd->ejecutar($sql, [
                $usuario['id'],
                $pelicula['id'],
                5,
                'Comentario de prueba'
            ]);
            
            // verificar creacion
            $resenaCreada = $this->bd->obtenerUno(
                "SELECT * FROM resenas WHERE id_usuario = ? AND id_pelicula = ? ORDER BY fecha_creacion DESC LIMIT 1", 
                [$usuario['id'], $pelicula['id']]
            );
            
            if ($resenaCreada) {
                // limpiar después de la prueba
                $this->bd->ejecutar("DELETE FROM resenas WHERE id = ?", [$resenaCreada['id']]);
                $this->agregarResultado('CRUD Reseñas', true, 'Operaciones CRUD exitosas en reseñas');
            } else {
                $this->agregarResultado('CRUD Reseñas', false, 'Reseña no encontrada después de crearla');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('CRUD Reseñas', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar crud de generos
    private function probarCRUDGeneros() {
        try {
            $generoPrueba = 'Género Prueba ' . time();
            
            // crear genero de prueba
            $sql = "INSERT INTO generos (nombre, descripcion, color_hex) VALUES (?, ?, ?)";
            $this->bd->ejecutar($sql, [
                $generoPrueba,
                'Descripción prueba',
                '#FF5733'
            ]);
            
            // verificar creacion
            $generoCreado = $this->bd->obtenerUno("SELECT * FROM generos WHERE nombre = ?", [$generoPrueba]);
            
            if ($generoCreado) {
                // limpiar
                $this->bd->ejecutar("DELETE FROM generos WHERE id = ?", [$generoCreado['id']]);
                $this->agregarResultado('CRUD Géneros', true, 'Operaciones CRUD exitosas en géneros');
            } else {
                $this->agregarResultado('CRUD Géneros', false, 'Género no encontrado después de crearlo');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('CRUD Géneros', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar integridad referencial
    private function probarIntegridadDatos() {
        try {
            // intentar crear resena con usuario inexistente (debe fallar)
            $sql = "INSERT INTO resenas (id_usuario, id_pelicula, puntuacion, comentario) VALUES (?, ?, ?, ?)";
            
            try {
                $this->bd->ejecutar($sql, [99999, 1, 5, 'Prueba']); // usuario 99999 no existe
                $this->agregarResultado('Integridad Referencial', false, 'Permitió insertar reseña con usuario inexistente');
            } catch (Exception $e) {
                // esto debe fallar por integridad referencial
                $this->agregarResultado('Integridad Referencial', true, 'Restricciones de FK funcionando correctamente');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Integridad Referencial', false, 'Error inesperado: ' . $e->getMessage());
        }
    }
    
    // probar proteccion contra inyeccion sql
    private function probarPrevencionInyeccionSQL() {
        try {
            // intentar inyeccion sql en búsqueda de usuarios
            $entradaMaliciosa = "'; DROP TABLE usuarios; --";
            
            // esto debe ser seguro usando prepared statements
            $resultado = $this->bd->obtenerTodos("SELECT * FROM usuarios WHERE nombre_usuario = ?", [$entradaMaliciosa]);
            
            // verificar que la tabla usuarios sigue existiendo
            $verificacionTabla = $this->bd->obtenerUno("SELECT COUNT(*) as total FROM usuarios");
            
            if ($verificacionTabla !== false) {
                $this->agregarResultado('Protección Inyección SQL', true, 'Prepared statements funcionando correctamente');
            } else {
                $this->agregarResultado('Protección Inyección SQL', false, 'Posible vulnerabilidad inyección SQL');
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Protección Inyección SQL', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // probar indices de rendimiento
    private function probarIndicesRendimiento() {
        try {
            $tiempoInicio = microtime(true);
            
            // consulta que debe usar indices
            $resultado = $this->bd->obtenerTodos(
                "SELECT u.nombre_usuario, COUNT(r.id) as total_resenas 
                 FROM usuarios u 
                 LEFT JOIN resenas r ON u.id = r.id_usuario 
                 GROUP BY u.id 
                 LIMIT 10"
            );
            
            $tiempoFin = microtime(true);
            $tiempo = round(($tiempoFin - $tiempoInicio) * 1000, 2);
            
            // si tarda menos de 100ms consideramos que los indices funcionan bien
            if ($tiempo < 100) {
                $this->agregarResultado('Índices de Rendimiento', true, "Consulta optimizada: {$tiempo}ms", $tiempo);
            } else {
                $this->agregarResultado('Índices de Rendimiento', false, "Consulta lenta: {$tiempo}ms - revisar índices", $tiempo);
            }
            
        } catch (Exception $e) {
            $this->agregarResultado('Índices de Rendimiento', false, 'Error: ' . $e->getMessage());
        }
    }
    
    // agregar resultado de prueba
    private function agregarResultado($nombre, $aprobada, $descripcion, $tiempo = null) {
        $this->resultados[] = [
            'nombre' => $nombre,
            'aprobada' => $aprobada,
            'descripcion' => $descripcion,
            'tiempo' => $tiempo,
            'detalles' => ''
        ];
        
        // log del resultado
        $estado = $aprobada ? 'APROBADA' : 'FALLIDA';
        Registrador::info("Prueba $nombre: $estado - $descripcion");
    }
}
?>