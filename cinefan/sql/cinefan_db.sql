-- ===============================================
-- 🎬 CINEFAN - BASE DE DATOS COMPLETA
-- ===============================================

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS cinefan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cinefan_db;

-- ===============================================
-- TABLAS PRINCIPALES
-- ===============================================

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre_usuario VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_ultimo_acceso TIMESTAMP NULL,
    activo BOOLEAN DEFAULT TRUE,
    avatar_url VARCHAR(500) NULL,
    biografia TEXT NULL,
    INDEX idx_usuario (nombre_usuario),
    INDEX idx_email (email),
    INDEX idx_activo (activo)
);

-- Tabla de géneros
CREATE TABLE generos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT NULL,
    color_hex VARCHAR(7) DEFAULT '#6c757d',
    activo BOOLEAN DEFAULT TRUE
);

-- Tabla de películas
CREATE TABLE peliculas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(200) NOT NULL,
    director VARCHAR(100) NOT NULL,
    ano_lanzamiento INT NOT NULL,
    duracion_minutos INT NOT NULL,
    genero_id INT NOT NULL,
    sinopsis TEXT NULL,
    imagen_url VARCHAR(500) NULL,
    id_usuario_creador INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (genero_id) REFERENCES generos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario_creador) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_titulo (titulo),
    INDEX idx_director (director),
    INDEX idx_ano (ano_lanzamiento),
    INDEX idx_genero (genero_id),
    INDEX idx_usuario_creador (id_usuario_creador),
    INDEX idx_activo (activo)
);

-- Tabla de reseñas
CREATE TABLE resenas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    id_pelicula INT NOT NULL,
    puntuacion INT NOT NULL CHECK (puntuacion >= 1 AND puntuacion <= 5),
    titulo VARCHAR(200) NULL,
    texto_resena TEXT NOT NULL,
    fecha_resena TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    likes INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    es_spoiler BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_pelicula) REFERENCES peliculas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_pelicula (id_usuario, id_pelicula),
    INDEX idx_usuario (id_usuario),
    INDEX idx_pelicula (id_pelicula),
    INDEX idx_puntuacion (puntuacion),
    INDEX idx_fecha (fecha_resena),
    INDEX idx_likes (likes),
    INDEX idx_activo (activo)
);

-- Tabla de películas favoritas
CREATE TABLE favoritos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    id_pelicula INT NOT NULL,
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_pelicula) REFERENCES peliculas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorito (id_usuario, id_pelicula),
    INDEX idx_usuario (id_usuario),
    INDEX idx_pelicula (id_pelicula)
);

-- Tabla de seguimientos entre usuarios
CREATE TABLE seguimientos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_seguidor INT NOT NULL,
    id_seguido INT NOT NULL,
    fecha_seguimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (id_seguidor) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_seguido) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_seguimiento (id_seguidor, id_seguido),
    CHECK (id_seguidor != id_seguido),
    INDEX idx_seguidor (id_seguidor),
    INDEX idx_seguido (id_seguido)
);

-- Tabla de likes en reseñas
CREATE TABLE likes_resenas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    id_resena INT NOT NULL,
    fecha_like TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (id_resena) REFERENCES resenas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (id_usuario, id_resena),
    INDEX idx_usuario (id_usuario),
    INDEX idx_resena (id_resena)
);

-- Tabla de administradores del panel web
CREATE TABLE administradores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    nivel_acceso ENUM('admin', 'moderador', 'solo_lectura') DEFAULT 'moderador',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL,
    activo BOOLEAN DEFAULT TRUE,
    INDEX idx_usuario (usuario),
    INDEX idx_nivel (nivel_acceso)
);

-- ===============================================
-- INSERTAR GÉNEROS PREDEFINIDOS
-- ===============================================

INSERT INTO generos (nombre, descripcion, color_hex) VALUES
('Acción', 'Películas con secuencias de acción, combates y aventura', '#dc3545'),
('Aventura', 'Películas de exploración y viajes emocionantes', '#fd7e14'),
('Comedia', 'Películas diseñadas para hacer reír y entretener', '#ffc107'),
('Drama', 'Películas que exploran temas serios y emocionales', '#6f42c1'),
('Terror', 'Películas diseñadas para asustar y crear suspense', '#1f2937'),
('Ciencia Ficción', 'Películas con elementos futuristas y tecnológicos', '#06b6d4'),
('Romance', 'Películas centradas en relaciones amorosas', '#ec4899'),
('Thriller', 'Películas de suspense y tensión psicológica', '#6b7280'),
('Animación', 'Películas creadas mediante técnicas de animación', '#10b981'),
('Documental', 'Películas que documentan la realidad', '#92400e'),
('Musical', 'Películas que incorporan música y canto', '#7c3aed'),
('Western', 'Películas ambientadas en el oeste americano', '#a16207'),
('Fantasía', 'Películas con elementos mágicos y fantásticos', '#9333ea');

-- ===============================================
-- DATOS DE EJEMPLO
-- ===============================================

-- Usuarios de ejemplo
INSERT INTO usuarios (nombre_usuario, email, password, nombre_completo, biografia) VALUES
('angel_admin', 'angel@cinefan.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ángel González', 'Desarrollador y amante del cine. Fundador de CineFan.'),
('maria_cine', 'maria@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'María López', 'Crítica de cine y fanática de los thrillers.'),
('carlos_film', 'carlos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos Martín', 'Coleccionista de películas clásicas.'),
('ana_reviews', 'ana@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana Rodríguez', 'Experta en cine independiente y documentales.');

-- Películas de ejemplo
INSERT INTO peliculas (titulo, director, ano_lanzamiento, duracion_minutos, genero_id, sinopsis, imagen_url, id_usuario_creador) VALUES
('Oppenheimer', 'Christopher Nolan', 2023, 180, 4, 'La historia del físico J. Robert Oppenheimer y el desarrollo de la bomba atómica.', 'https://image.tmdb.org/t/p/w500/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg', 1),
('Barbie', 'Greta Gerwig', 2023, 114, 3, 'Barbie vive en Barbieland donde todo es perfecto y rosa. Un día decide aventurarse al mundo real.', 'https://image.tmdb.org/t/p/w500/iuFNMS8U5cb6xfzi51Dbkovj7vM.jpg', 2),
('Spider-Man: Across the Spider-Verse', 'Joaquim Dos Santos', 2023, 140, 9, 'Miles Morales se embarca en una aventura épica a través del multiverso.', 'https://image.tmdb.org/t/p/w500/8Vt6mWEReuy4Of61Lnj5Xj704m8.jpg', 1),
('John Wick: Chapter 4', 'Chad Stahelski', 2023, 169, 1, 'John Wick descubre un camino para derrotar a la Mesa Directiva.', 'https://image.tmdb.org/t/p/w500/vZloFAK7NmvMGKE7VkF5UHaz0I.jpg', 3),
('The Menu', 'Mark Mylod', 2022, 107, 8, 'Una pareja viaja a una isla remota para cenar en un restaurante exclusivo.', 'https://image.tmdb.org/t/p/w500/56v2KjBlU4XaOv9rVYEQypROD7P.jpg', 4),
('Top Gun: Maverick', 'Joseph Kosinski', 2022, 131, 1, 'Pete "Maverick" Mitchell sigue siendo un piloto de élite de la Marina.', 'https://image.tmdb.org/t/p/w500/62HCnUTziyWcpDaBO2i1DX17ljH.jpg', 2);

-- Reseñas de ejemplo
INSERT INTO resenas (id_usuario, id_pelicula, puntuacion, titulo, texto_resena) VALUES
(1, 1, 5, 'Obra maestra cinematográfica', 'Nolan vuelve a demostrar por qué es uno de los mejores directores actuales. Una película compleja, visualmente impresionante y con actuaciones soberbias.'),
(2, 1, 4, 'Excelente pero densa', 'Una película brillante que requiere atención total. La cinematografía es espectacular aunque a veces se siente un poco larga.'),
(3, 2, 4, 'Diversión garantizada', 'Una sorpresa total. Greta Gerwig logra crear algo único con Barbie. Divertida, inteligente y con mucho corazón.'),
(4, 2, 5, 'Genial en todos los aspectos', 'No esperaba tanto de una película de Barbie. Es brillante, divertida y tiene mucho que decir sobre la sociedad actual.'),
(1, 3, 5, 'Animación revolucionaria', 'Visualmente impresionante. La animación es revolucionaria y la historia mantiene el nivel de la primera película.'),
(2, 4, 3, 'Buena acción, poca novedad', 'John Wick sigue siendo espectacular en las escenas de acción, pero la trama se siente repetitiva en esta cuarta entrega.'),
(3, 5, 4, 'Thriller psicológico efectivo', 'Una película que te mantiene en tensión todo el tiempo. Excelente atmósfera y un final inesperado.'),
(4, 6, 5, 'Nostalgia y adrenalina perfectas', 'Tom Cruise demuestra que sigue siendo el rey de las películas de acción. Una secuela que supera a la original.');

-- Favoritos de ejemplo
INSERT INTO favoritos (id_usuario, id_pelicula) VALUES
(1, 1), (1, 3), (1, 6),
(2, 1), (2, 2), (2, 5),
(3, 2), (3, 4), (3, 6),
(4, 2), (4, 3), (4, 5);

-- Seguimientos de ejemplo
INSERT INTO seguimientos (id_seguidor, id_seguido) VALUES
(1, 2), (1, 3), (1, 4),
(2, 1), (2, 4),
(3, 1), (3, 2),
(4, 1), (4, 2), (4, 3);

-- Administrador por defecto (password: admin123)
INSERT INTO administradores (usuario, password, nombre_completo, email, nivel_acceso) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador CineFan', 'admin@cinefan.com', 'admin');

-- ===============================================
-- VISTAS ÚTILES
-- ===============================================

-- Vista de películas con información completa
CREATE OR REPLACE VIEW vista_peliculas_completas AS
SELECT 
    p.id,
    p.titulo,
    p.director,
    p.ano_lanzamiento,
    p.duracion_minutos,
    g.nombre as genero,
    p.sinopsis,
    p.imagen_url,
    u.nombre_usuario as creador,
    p.fecha_creacion,
    COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio,
    COUNT(r.id) as total_resenas,
    COUNT(f.id) as total_favoritos
FROM peliculas p
LEFT JOIN generos g ON p.genero_id = g.id
LEFT JOIN usuarios u ON p.id_usuario_creador = u.id
LEFT JOIN resenas r ON p.id = r.id_pelicula AND r.activo = TRUE
LEFT JOIN favoritos f ON p.id = f.id_pelicula
WHERE p.activo = TRUE
GROUP BY p.id, p.titulo, p.director, p.ano_lanzamiento, p.duracion_minutos, 
         g.nombre, p.sinopsis, p.imagen_url, u.nombre_usuario, p.fecha_creacion;

-- Vista del feed de reseñas
CREATE OR REPLACE VIEW vista_feed_resenas AS
SELECT 
    r.id,
    r.puntuacion,
    r.titulo as titulo_resena,
    r.texto_resena,
    r.fecha_resena,
    r.likes,
    u.nombre_usuario,
    u.nombre_completo,
    p.titulo as titulo_pelicula,
    p.director,
    p.ano_lanzamiento,
    p.imagen_url as imagen_pelicula,
    g.nombre as genero
FROM resenas r
INNER JOIN usuarios u ON r.id_usuario = u.id
INNER JOIN peliculas p ON r.id_pelicula = p.id
INNER JOIN generos g ON p.genero_id = g.id
WHERE r.activo = TRUE AND u.activo = TRUE AND p.activo = TRUE
ORDER BY r.fecha_resena DESC;

-- ===============================================
-- PROCEDIMIENTOS ALMACENADOS
-- ===============================================

DELIMITER //

-- Procedimiento para obtener estadísticas de usuario
CREATE PROCEDURE GetUsuarioEstadisticas(IN usuario_id INT)
BEGIN
    SELECT 
        COUNT(DISTINCT p.id) as total_peliculas,
        COUNT(DISTINCT r.id) as total_resenas,
        COUNT(DISTINCT f.id) as total_favoritos,
        COUNT(DISTINCT s1.id) as total_siguiendo,
        COUNT(DISTINCT s2.id) as total_seguidores,
        COALESCE(AVG(r.puntuacion), 0) as puntuacion_promedio
    FROM usuarios u
    LEFT JOIN peliculas p ON u.id = p.id_usuario_creador AND p.activo = TRUE
    LEFT JOIN resenas r ON u.id = r.id_usuario AND r.activo = TRUE
    LEFT JOIN favoritos f ON u.id = f.id_usuario
    LEFT JOIN seguimientos s1 ON u.id = s1.id_seguidor AND s1.activo = TRUE
    LEFT JOIN seguimientos s2 ON u.id = s2.id_seguido AND s2.activo = TRUE
    WHERE u.id = usuario_id AND u.activo = TRUE;
END //

-- Procedimiento para actualizar contadores de likes
CREATE PROCEDURE ActualizarLikesResena(IN resena_id INT)
BEGIN
    UPDATE resenas 
    SET likes = (
        SELECT COUNT(*) 
        FROM likes_resenas 
        WHERE id_resena = resena_id
    )
    WHERE id = resena_id;
END //

-- Procedimiento para obtener feed personalizado
CREATE PROCEDURE GetFeedPersonalizado(IN usuario_id INT, IN limite INT, IN offset_val INT)
BEGIN
    SELECT 
        r.id,
        r.puntuacion,
        r.titulo as titulo_resena,
        r.texto_resena,
        r.fecha_resena,
        r.likes,
        u.nombre_usuario,
        u.nombre_completo,
        p.titulo as titulo_pelicula,
        p.director,
        p.ano_lanzamiento,
        p.imagen_url as imagen_pelicula,
        g.nombre as genero,
        CASE WHEN lr.id IS NOT NULL THEN TRUE ELSE FALSE END as usuario_dio_like
    FROM resenas r
    INNER JOIN usuarios u ON r.id_usuario = u.id
    INNER JOIN peliculas p ON r.id_pelicula = p.id
    INNER JOIN generos g ON p.genero_id = g.id
    LEFT JOIN seguimientos s ON r.id_usuario = s.id_seguido AND s.id_seguidor = usuario_id
    LEFT JOIN likes_resenas lr ON r.id = lr.id_resena AND lr.id_usuario = usuario_id
    WHERE r.activo = TRUE AND u.activo = TRUE AND p.activo = TRUE
    AND (s.id IS NOT NULL OR r.id_usuario = usuario_id)
    ORDER BY r.fecha_resena DESC
    LIMIT limite OFFSET offset_val;
END //

DELIMITER ;

-- ===============================================
-- TRIGGERS
-- ===============================================

DELIMITER //

-- Trigger para actualizar fecha de último acceso
CREATE TRIGGER actualizar_ultimo_acceso 
BEFORE UPDATE ON usuarios 
FOR EACH ROW
BEGIN
    IF NEW.fecha_ultimo_acceso != OLD.fecha_ultimo_acceso THEN
        SET NEW.fecha_ultimo_acceso = CURRENT_TIMESTAMP;
    END IF;
END //

-- Trigger para mantener integridad en likes
CREATE TRIGGER after_insert_like
AFTER INSERT ON likes_resenas
FOR EACH ROW
BEGIN
    CALL ActualizarLikesResena(NEW.id_resena);
END //

CREATE TRIGGER after_delete_like
AFTER DELETE ON likes_resenas
FOR EACH ROW
BEGIN
    CALL ActualizarLikesResena(OLD.id_resena);
END //

DELIMITER ;

-- ===============================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ===============================================

CREATE INDEX idx_resenas_fecha_puntuacion ON resenas(fecha_resena DESC, puntuacion DESC);
CREATE INDEX idx_peliculas_ano_genero ON peliculas(ano_lanzamiento, genero_id);
CREATE INDEX idx_usuarios_fecha_registro ON usuarios(fecha_registro);
CREATE INDEX idx_favoritos_fecha ON favoritos(fecha_agregado);

-- ===============================================
-- CONFIGURACIÓN FINAL
-- ===============================================

-- Habilitar el modo de verificación de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- Mensaje de confirmación
SELECT 'Base de datos CineFan creada exitosamente!' as mensaje;