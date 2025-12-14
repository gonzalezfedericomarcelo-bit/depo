<?php
// Archivo: includes/footer.php
// Propósito: Cierre HTML, Scripts Globales y MODALES AUTOMÁTICOS
?>
    </div> </div> 
    
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1060;">
    <div id="liveToast" class="toast align-items-center text-white bg-primary border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-bell me-2 fa-lg"></i> <strong id="toast-text">Nueva Notificación</strong>
                <div id="toast-content" class="mt-1 small opacity-75"></div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // 1. Sidebar Toggle (Móvil)
    document.addEventListener("DOMContentLoaded", function() {
        const toggle = document.getElementById('sidebarCollapse');
        const sidebar = document.getElementById('sidebar');
        if(toggle && sidebar) {
            toggle.addEventListener('click', () => sidebar.classList.toggle('d-none'));
        }
    });

    // 2. SISTEMA GLOBAL DE CONFIRMACIÓN (Intercepción de Formularios)
    // Esto hace que CUALQUIER botón de submit en TODO el sistema pregunte antes
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Si el formulario ya fue confirmado o es de búsqueda (GET), lo dejamos pasar
        if (form.getAttribute('data-confirmed') === 'true' || form.method === 'get') {
            return;
        }

        e.preventDefault(); // Detener envío inmediato

        // Detectar qué acción es (texto del botón)
        let actionBtn = form.querySelector('button[type="submit"]');
        let actionText = actionBtn ? actionBtn.innerText : 'Confirmar acción';
        
        // Colores según tipo de acción (palabras clave)
        let confirmColor = '#0f4c75'; // Azul default (actis)
        let iconType = 'question';

        if(actionText.toLowerCase().includes('rechazar') || actionText.toLowerCase().includes('eliminar') || actionText.toLowerCase().includes('borrar')) {
            confirmColor = '#dc3545'; // Rojo
            iconType = 'warning';
        } else if(actionText.toLowerCase().includes('aprobar') || actionText.toLowerCase().includes('autorizar') || actionText.toLowerCase().includes('guardar')) {
            confirmColor = '#198754'; // Verde
        }

        Swal.fire({
            title: '¿Estás seguro?',
            text: "Vas a realizar la acción: " + actionText,
            icon: iconType,
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                form.setAttribute('data-confirmed', 'true'); // Marcar para no volver a preguntar
                
                // Si el botón tenía un 'name' y 'value' (ej: name="accion" value="aprobar"), 
                // al hacer submit() por JS esos datos se pierden. Los inyectamos como hidden.
                if(actionBtn && actionBtn.name) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = actionBtn.name;
                    hiddenInput.value = actionBtn.value;
                    form.appendChild(hiddenInput);
                }
                
                // Mostrar feedback de carga
                Swal.fire({
                    title: 'Procesando...',
                    text: 'Por favor espere un momento.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading() }
                });

                form.submit(); // Enviar de verdad
            }
        });
    });

    // 3. Configuración de Audio
    const notifSound = new Audio('assets/sound/alert.mp3'); 

    // 4. SISTEMA DE NOTIFICACIONES INTELIGENTE (Tu código original)
    let lastCount = 0;
    let isFirstLoad = true; // Bandera para evitar bucle al recargar página

    function checkNotifications() {
        fetch('api_notificaciones.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('notif-badge');
                const list = document.getElementById('notif-list');
                const toastEl = document.getElementById('liveToast');
                
                // A. Actualizar Badge y Lista (Siempre)
                if (data.count > 0) {
                    if(badge) {
                        badge.innerText = data.count;
                        badge.style.display = 'inline-block';
                    }
                    
                    if(list) {
                        let htmlList = '<li><h6 class="dropdown-header">Pendientes ('+data.count+')</h6></li>';
                        data.items.forEach(item => {
                            // Construir link para marcar como leída al hacer clic
                            let urlDestino = encodeURIComponent(item.url_destino);
                            let linkMarcar = `marcar_notificacion.php?id=${item.id}&url=${urlDestino}`;
                            
                            htmlList += `<li><a class="dropdown-item small py-2 text-wrap border-bottom" href="${linkMarcar}">
                                            <i class="fas fa-circle text-primary me-2" style="font-size:0.5rem"></i>${item.mensaje}
                                         </a></li>`;
                        });
                        list.innerHTML = htmlList;
                    }
                } else {
                    if(badge) badge.style.display = 'none';
                    if(list) list.innerHTML = '<li><h6 class="dropdown-header">Notificaciones</h6></li><li class="text-center p-2 text-muted small">No tienes mensajes nuevos</li>';
                }

                // B. DISPARAR TOAST (Solo si NO es la primera carga Y hay más mensajes que antes)
                if (!isFirstLoad && data.count > lastCount) {
                    let latest = data.latest;
                    if (latest) {
                        const toastBody = toastEl.querySelector('.toast-body');
                        let urlDestino = encodeURIComponent(latest.url_destino);
                        let linkMarcar = `marcar_notificacion.php?id=${latest.id}&url=${urlDestino}`;

                        toastBody.innerHTML = `
                            <a href="${linkMarcar}" class="text-white text-decoration-none d-block">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-bell me-2"></i> 
                                    <strong>¡Nueva Novedad!</strong>
                                </div>
                                <div class="mt-1">${latest.mensaje}</div>
                                <div class="mt-2 small text-white-50 text-end">Clic para ver <i class="fas fa-arrow-right ms-1"></i></div>
                            </a>
                        `;
                        
                        const toast = new bootstrap.Toast(toastEl, { delay: 8000 });
                        toast.show();
                        
                        notifSound.play().catch(e => console.log("Audio bloqueado por navegador"));
                    }
                }

                lastCount = data.count;
                isFirstLoad = false; 
            })
            .catch(error => console.error('Error polling:', error));
    }

    setInterval(checkNotifications, 4000);
    checkNotifications();

</script>
</body>
</html>