<?php
// Archivo: includes/footer.php
// Propósito: Cierre HTML, Scripts Globales y Notificaciones Inteligentes (Sin Bucle)
?>
    </div> </div> <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1060;">
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

<script>
    // 1. Sidebar Toggle (Móvil)
    document.addEventListener("DOMContentLoaded", function() {
        const toggle = document.getElementById('sidebarCollapse');
        const sidebar = document.getElementById('sidebar');
        if(toggle && sidebar) {
            toggle.addEventListener('click', () => sidebar.classList.toggle('d-none'));
        }
    });

    // 2. Configuración de Audio
    const notifSound = new Audio('assets/sound/alert.mp3'); // Asegúrate que la ruta sea correcta o usa ruta absoluta

    // 3. SISTEMA DE NOTIFICACIONES INTELIGENTE
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
                        
                        // Construir link de marcado para el Toast
                        let urlDestino = encodeURIComponent(latest.url_destino);
                        let linkMarcar = `marcar_notificacion.php?id=${latest.id}&url=${urlDestino}`;

                        // Inyectar HTML
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
                        
                        // Mostrar
                        const toast = new bootstrap.Toast(toastEl, { delay: 8000 });
                        toast.show();
                        
                        // Sonido
                        notifSound.play().catch(e => console.log("Audio bloqueado por navegador"));
                    }
                }

                // C. Actualizar estado
                lastCount = data.count;
                isFirstLoad = false; // Ya pasó la primera carga, habilitar alertas futuras
            })
            .catch(error => console.error('Error polling:', error));
    }

    // Ejecutar cada 4 segundos
    setInterval(checkNotifications, 4000);
    
    // Ejecutar inmediatamente al cargar (sin sonido gracias a isFirstLoad)
    checkNotifications();

</script>
</body>
</html>