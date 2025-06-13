<?php
?>

<!-- Modal para gestión de usuarios -->
<div class="modal fade" id="modal-usuario" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user"></i> <span id="titulo-modal-usuario">Gestionar Usuario</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-usuario" novalidate>
                    <input type="hidden" id="usuario-id" name="id">
                    <input type="hidden" name="accion_crud" id="usuario-accion">
                    <input type="hidden" name="tipo_entidad" value="usuario">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="usuario-nombre-usuario" class="form-label">
                                    <i class="fas fa-user"></i> Nombre de Usuario *
                                </label>
                                <input type="text" class="form-control" id="usuario-nombre-usuario" 
                                       name="nombre_usuario" required maxlength="50">
                                <div class="invalid-feedback">
                                    El nombre de usuario es obligatorio
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="usuario-email" class="form-label">
                                    <i class="fas fa-envelope"></i> Email *
                                </label>
                                <input type="email" class="form-control" id="usuario-email" 
                                       name="email" required>
                                <div class="invalid-feedback">
                                    El email es obligatorio y debe ser válido
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="usuario-nombre-completo" class="form-label">
                                    <i class="fas fa-id-card"></i> Nombre Completo
                                </label>
                                <input type="text" class="form-control" id="usuario-nombre-completo" 
                                       name="nombre_completo" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="usuario-clave" class="form-label">
                                    <i class="fas fa-lock"></i> Contraseña
                                    <small class="text-muted">(dejar vacío para mantener actual)</small>
                                </label>
                                <input type="password" class="form-control" id="usuario-clave" 
                                       name="clave" minlength="6">
                                <div class="invalid-feedback">
                                    La contraseña debe tener al menos 6 caracteres
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="usuario-activo" class="form-label">
                                    <i class="fas fa-toggle-on"></i> Estado
                                </label>
                                <select class="form-select" id="usuario-activo" name="activo">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="usuario-avatar" class="form-label">
                                    <i class="fas fa-image"></i> URL Avatar
                                </label>
                                <input type="url" class="form-control" id="usuario-avatar" 
                                       name="avatar_url" placeholder="https://ejemplo.com/avatar.jpg">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="usuario-biografia" class="form-label">
                            <i class="fas fa-quote-left"></i> Biografía
                        </label>
                        <textarea class="form-control" id="usuario-biografia" name="biografia" 
                                  rows="3" maxlength="500"></textarea>
                        <small class="text-muted">Máximo 500 caracteres</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="guardarUsuario()">
                    <i class="fas fa-save"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para gestión de películas -->
<div class="modal fade" id="modal-pelicula" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-film"></i> <span id="titulo-modal-pelicula">Gestionar Película</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="form-pelicula" novalidate>
                    <input type="hidden" id="pelicula-id" name="id">
                    <input type="hidden" name="accion_crud" id="pelicula-accion">
                    <input type="hidden" name="tipo_entidad" value="pelicula">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="pelicula-titulo" class="form-label">
                                    <i class="fas fa-video"></i> Título *
                                </label>
                                <input type="text" class="form-control" id="pelicula-titulo" 
                                       name="titulo" required maxlength="200">
                                <div class="invalid-feedback">
                                    El título es obligatorio
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pelicula-ano" class="form-label">
                                    <i class="fas fa-calendar"></i> Año *
                                </label>
                                <input type="number" class="form-control" id="pelicula-ano" 
                                       name="ano_lanzamiento" required min="1895" max="2030">
                                <div class="invalid-feedback">
                                    El año debe estar entre 1895 y 2030
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pelicula-director" class="form-label">
                                    <i class="fas fa-user-tie"></i> Director *
                                </label>
                                <input type="text" class="form-control" id="pelicula-director" 
                                       name="director" required maxlength="100">
                                <div class="invalid-feedback">
                                    El director es obligatorio
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="pelicula-genero" class="form-label">
                                    <i class="fas fa-tags"></i> Género *
                                </label>
                                <select class="form-select" id="pelicula-genero" name="genero_id" required>
                                    <option value="">Seleccionar género</option>
                                    <option value="1">Acción</option>
                                    <option value="2">Drama</option>
                                    <option value="3">Comedia</option>
                                    <option value="4">Terror</option>
                                    <option value="5">Ciencia Ficción</option>
                                    <option value="6">Romance</option>
                                    <option value="7">Thriller</option>
                                    <option value="8">Aventura</option>
                                </select>
                                <div class="invalid-feedback">
                                    Debe seleccionar un género
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="pelicula-duracion" class="form-label">
                                    <i class="fas fa-clock"></i> Duración (min) *
                                </label>
                                <input type="number" class="form-control" id="pelicula-duracion" 
                                       name="duracion_minutos" required min="1" max="600">
                                <div class="invalid-feedback">
                                    La duración debe estar entre 1 y 600 minutos
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pelicula-sinopsis" class="form-label">
                            <i class="fas fa-align-left"></i> Sinopsis
                        </label>
                        <textarea class="form-control" id="pelicula-sinopsis" name="sinopsis" 
                                  rows="4" maxlength="1000"></textarea>
                        <small class="text-muted">Máximo 1000 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pelicula-imagen" class="form-label">
                            <i class="fas fa-image"></i> URL de la Imagen
                        </label>
                        <input type="url" class="form-control" id="pelicula-imagen" 
                               name="imagen_url" placeholder="https://ejemplo.com/poster.jpg">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="guardarPelicula()">
                    <i class="fas fa-save"></i> Guardar Película
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver reseña completa -->
<div class="modal fade" id="modal-resena" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-star"></i> Detalles de la Reseña
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="contenido-resena">
                    <!-- Aquí se cargará el contenido de la reseña -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cerrar
                </button>
                <button type="button" class="btn btn-danger" id="btn-eliminar-resena">
                    <i class="fas fa-trash"></i> Eliminar Reseña
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación para eliminaciones -->
<div class="modal fade" id="modal-confirmar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-warning"></i> Confirmar Acción
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="mensaje-confirmacion">¿Está seguro de realizar esta acción?</p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="button" class="btn btn-danger" id="btn-confirmar-accion">
                    <i class="fas fa-check"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// funciones para manejo de modales CRUD

// mostrar modal de usuario
function mostrarModalUsuario(accion, id = null) {
    const modal = new bootstrap.Modal(document.getElementById('modal-usuario'));
    const titulo = document.getElementById('titulo-modal-usuario');
    const form = document.getElementById('form-usuario');
    
    // limpiar formulario
    form.reset();
    form.classList.remove('was-validated');
    
    if (accion === 'crear') {
        titulo.textContent = 'Crear Nuevo Usuario';
        document.getElementById('usuario-accion').value = 'crear';
        document.getElementById('usuario-clave').required = true;
        
        // foco en el primer campo
        modal._element.addEventListener('shown.bs.modal', function () {
            document.getElementById('usuario-nombre-usuario').focus();
        }, { once: true });
        
    } else if (accion === 'editar' && id) {
        titulo.textContent = 'Editar Usuario';
        document.getElementById('usuario-accion').value = 'editar';
        document.getElementById('usuario-id').value = id;
        document.getElementById('usuario-clave').required = false;
        
        // cargar datos del usuario
        cargarDatosUsuario(id);
    }
    
    modal.show();
}

// cargar datos del usuario para edicion
function cargarDatosUsuario(id) {
    // aqui hariamos una peticion para obtener los datos del usuario
    fetch(`?accion=ajax&punto=usuario_detalle&id=${id}`)
        .then(respuesta => respuesta.json())
        .then(datos => {
            if (datos.exito) {
                const usuario = datos.datos;
                document.getElementById('usuario-nombre-usuario').value = usuario.nombre_usuario || '';
                document.getElementById('usuario-email').value = usuario.email || '';
                document.getElementById('usuario-nombre-completo').value = usuario.nombre_completo || '';
                document.getElementById('usuario-activo').value = usuario.activo ? '1' : '0';
                document.getElementById('usuario-avatar').value = usuario.avatar_url || '';
                document.getElementById('usuario-biografia').value = usuario.biografia || '';
            }
        })
        .catch(error => {
            console.error('Error cargando usuario:', error);
            mostrarAlerta('Error cargando los datos del usuario', 'danger');
        });
}

// guardar usuario
function guardarUsuario() {
    const form = document.getElementById('form-usuario');
    
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(form);
    
    // mostrar spinner en el botón
    const btnGuardar = event.target;
    const textoOriginal = btnGuardar.innerHTML;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    btnGuardar.disabled = true;
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(respuesta => respuesta.text())
    .then(resultado => {
        // cerrar modal
        bootstrap.Modal.getInstance(document.getElementById('modal-usuario')).hide();
        
        // mostrar mensaje de exito
        mostrarAlerta('Usuario guardado correctamente', 'success');
        
        // recargar tabla
        cargarUsuarios();
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarAlerta('Error guardando el usuario', 'danger');
    })
    .finally(() => {
        // restaurar boton
        btnGuardar.innerHTML = textoOriginal;
        btnGuardar.disabled = false;
    });
}

// mostrar modal de pelicula
function mostrarModalPelicula(accion, id = null) {
    const modal = new bootstrap.Modal(document.getElementById('modal-pelicula'));
    const titulo = document.getElementById('titulo-modal-pelicula');
    const form = document.getElementById('form-pelicula');
    
    // limpiar formulario
    form.reset();
    form.classList.remove('was-validated');
    
    if (accion === 'crear') {
        titulo.textContent = 'Crear Nueva Película';
        document.getElementById('pelicula-accion').value = 'crear';
        
        // foco en el primer campo
        modal._element.addEventListener('shown.bs.modal', function () {
            document.getElementById('pelicula-titulo').focus();
        }, { once: true });
        
    } else if (accion === 'editar' && id) {
        titulo.textContent = 'Editar Película';
        document.getElementById('pelicula-accion').value = 'editar';
        document.getElementById('pelicula-id').value = id;
        
        // cargar datos de la pelicula
        cargarDatosPelicula(id);
    }
    
    modal.show();
}

// cargar datos de pelicula para edicion
function cargarDatosPelicula(id) {
    fetch(`?accion=ajax&punto=pelicula_detalle&id=${id}`)
        .then(respuesta => respuesta.json())
        .then(datos => {
            if (datos.exito) {
                const pelicula = datos.datos;
                document.getElementById('pelicula-titulo').value = pelicula.titulo || '';
                document.getElementById('pelicula-director').value = pelicula.director || '';
                document.getElementById('pelicula-ano').value = pelicula.ano_lanzamiento || '';
                document.getElementById('pelicula-genero').value = pelicula.genero_id || '';
                document.getElementById('pelicula-duracion').value = pelicula.duracion_minutos || '';
                document.getElementById('pelicula-sinopsis').value = pelicula.sinopsis || '';
                document.getElementById('pelicula-imagen').value = pelicula.imagen_url || '';
            }
        })
        .catch(error => {
            console.error('Error cargando pelicula:', error);
            mostrarAlerta('Error cargando los datos de la película', 'danger');
        });
}

// guardar pelicula
function guardarPelicula() {
    const form = document.getElementById('form-pelicula');
    
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(form);
    
    // mostrar spinner
    const btnGuardar = event.target;
    const textoOriginal = btnGuardar.innerHTML;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    btnGuardar.disabled = true;
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(respuesta => respuesta.text())
    .then(resultado => {
        // cerrar modal
        bootstrap.Modal.getInstance(document.getElementById('modal-pelicula')).hide();
        
        // mostrar mensaje
        mostrarAlerta('Película guardada correctamente', 'success');
        
        // recargar tabla
        cargarPeliculas();
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarAlerta('Error guardando la película', 'danger');
    })
    .finally(() => {
        btnGuardar.innerHTML = textoOriginal;
        btnGuardar.disabled = false;
    });
}

// ver resena completa
function verResenaCompleta(id) {
    const modal = new bootstrap.Modal(document.getElementById('modal-resena'));
    const contenido = document.getElementById('contenido-resena');
    
    // mostrar loading
    contenido.innerHTML = '<div class="text-center p-4"><div class="spinner-border"></div><p class="mt-2">Cargando reseña...</p></div>';
    
    // cargar datos de la resena
    fetch(`?accion=ajax&punto=resena_detalle&id=${id}`)
        .then(respuesta => respuesta.json())
        .then(datos => {
            if (datos.exito) {
                const resena = datos.datos;
                contenido.innerHTML = `
                    <div class="row">
                        <div class="col-md-4">
                            <img src="${resena.pelicula_imagen || 'assets/pelicula-default.jpg'}" 
                                 class="img-fluid rounded" alt="${resena.pelicula_titulo}">
                        </div>
                        <div class="col-md-8">
                            <h5>${resena.pelicula_titulo}</h5>
                            <p class="text-muted">Dirigida por ${resena.director}</p>
                            <hr>
                            <div class="d-flex align-items-center mb-3">
                                <img src="${resena.avatar_url || 'assets/avatar-default.png'}" 
                                     class="rounded-circle me-2" width="40" height="40">
                                <div>
                                    <strong>${resena.nombre_usuario}</strong><br>
                                    <small class="text-muted">${formatearFecha(resena.fecha_resena)}</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="puntuacion-estrellas fs-5">
                                    ${'★'.repeat(resena.puntuacion)}${'☆'.repeat(5-resena.puntuacion)}
                                </div>
                                <strong>${resena.puntuacion}/5 estrellas</strong>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h6><i class="fas fa-quote-left"></i> ${resena.titulo || 'Reseña sin título'}</h6>
                    <p class="mb-3">${resena.texto_resena || 'No hay contenido de reseña'}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-success">
                                <i class="fas fa-thumbs-up"></i> ${resena.likes || 0} likes
                            </span>
                            ${resena.es_spoiler ? '<span class="badge bg-warning ms-2">⚠️ Contiene spoilers</span>' : ''}
                        </div>
                        <small class="text-muted">ID: ${resena.id}</small>
                    </div>
                `;
                
                // configurar boton eliminar
                document.getElementById('btn-eliminar-resena').onclick = function() {
                    confirmarEliminacion('resena', id, `¿Está seguro de eliminar esta reseña de ${resena.nombre_usuario}?`);
                };
            } else {
                contenido.innerHTML = '<div class="alert alert-danger">Error cargando la reseña</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contenido.innerHTML = '<div class="alert alert-danger">Error cargando la reseña</div>';
        });
    
    modal.show();
}

// confirmar eliminacion
function confirmarEliminacion(tipo, id, mensaje) {
    const modal = new bootstrap.Modal(document.getElementById('modal-confirmar'));
    const mensajeElement = document.getElementById('mensaje-confirmacion');
    const btnConfirmar = document.getElementById('btn-confirmar-accion');
    
    mensajeElement.textContent = mensaje;
    
    // configurar accion de confirmacion
    btnConfirmar.onclick = function() {
        ejecutarEliminacion(tipo, id);
        bootstrap.Modal.getInstance(document.getElementById('modal-confirmar')).hide();
    };
    
    modal.show();
}

// ejecutar eliminacion
function ejecutarEliminacion(tipo, id) {
    const formData = new FormData();
    formData.append('accion_crud', 'eliminar');
    formData.append('tipo_entidad', tipo);
    formData.append('id', id);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(respuesta => respuesta.text())
    .then(resultado => {
        mostrarAlerta(`${capitalizar(tipo)} eliminado correctamente`, 'success');
        
        // recargar tabla correspondiente
        switch(tipo) {
            case 'usuario':
                cargarUsuarios();
                break;
            case 'pelicula':
                cargarPeliculas();
                break;
            case 'resena':
                cargarResenas();
                // cerrar modal de resena si esta abierto
                const modalResena = bootstrap.Modal.getInstance(document.getElementById('modal-resena'));
                if (modalResena) modalResena.hide();
                break;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarAlerta(`Error eliminando ${tipo}`, 'danger');
    });
}

// cambiar estado de usuario (activar/desactivar)
function cambiarEstadoUsuario(id, nuevoEstado) {
    const accion = nuevoEstado ? 'activar' : 'desactivar';
    const mensaje = `¿Está seguro de ${accion} este usuario?`;
    
    if (confirm(mensaje)) {
        const formData = new FormData();
        formData.append('accion_crud', 'cambiar_estado');
        formData.append('tipo_entidad', 'usuario');
        formData.append('id', id);
        formData.append('nuevo_estado', nuevoEstado);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(respuesta => respuesta.text())
        .then(resultado => {
            mostrarAlerta(`Usuario ${accion}do correctamente`, 'success');
            cargarUsuarios();
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarAlerta(`Error ${accion}ndo usuario`, 'danger');
        });
    }
}

// funciones auxiliares para eliminaciones directas
function eliminarUsuario(id) {
    confirmarEliminacion('usuario', id, '¿Está seguro de eliminar este usuario? Se eliminarán también todas sus películas y reseñas.');
}

function eliminarPelicula(id) {
    confirmarEliminacion('pelicula', id, '¿Está seguro de eliminar esta película? Se eliminarán también todas sus reseñas.');
}

function eliminarResena(id) {
    confirmarEliminacion('resena', id, '¿Está seguro de eliminar esta reseña?');
}

// mostrar alertas
function mostrarAlerta(mensaje, tipo = 'info') {
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo} alert-dismissible fade show position-fixed`;
    alerta.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alerta.innerHTML = `
        ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alerta);
    
    // auto-eliminar despues de 5 segundos
    setTimeout(() => {
        if (alerta.parentNode) {
            alerta.remove();
        }
    }, 5000);
}

// validacion en tiempo real para formularios
document.addEventListener('DOMContentLoaded', function() {
    // validacion formulario usuario
    const formUsuario = document.getElementById('form-usuario');
    if (formUsuario) {
        formUsuario.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarUsuario();
        });
    }
    
    // validacion formulario pelicula
    const formPelicula = document.getElementById('form-pelicula');
    if (formPelicula) {
        formPelicula.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarPelicula();
        });
    }
});
</script>