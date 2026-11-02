(() => {
    const CDN = 'https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/';
    const registros = {};
    const boton = document.querySelector('.tut-trigger-header');
    let clave = null;
    let activo = false;
    let carga = null;

    const toast = (icon, title) => Swal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        icon,
        title,
        didOpen: (t) => {
            t.onmouseenter = Swal.stopTimer;
            t.onmouseleave = Swal.resumeTimer;
        }
    });

    const cargar = () => {
        if (window.driver?.js?.driver) return Promise.resolve();

        if (!carga) {
            carga = new Promise((resolve, reject) => {
                if (!document.querySelector('link[data-tut-driver]')) {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = `${CDN}driver.css`;
                    link.dataset.tutDriver = '1';
                    document.head.appendChild(link);
                }

                const script = document.createElement('script');
                const fallo = (error) => {
                    clearTimeout(espera);
                    script.remove();
                    reject(error);
                };
                const espera = setTimeout(() => fallo(new Error('Tiempo de espera agotado')), 10000);

                script.src = `${CDN}driver.js.iife.js`;
                script.onload = () => (window.driver?.js?.driver ? (clearTimeout(espera), resolve()) : fallo(new Error('Driver.js no disponible')));
                script.onerror = () => fallo(new Error('Error al descargar Driver.js'));
                document.head.appendChild(script);
            }).catch((error) => {
                carga = null;
                throw error;
            });
        }

        return carga;
    };

    const visible = (el) => el instanceof Element && el.getClientRects().length > 0 && !el.parentElement?.closest('.d-none');

    const resolver = (element) => (typeof element === 'function' ? element() : document.querySelector(element));

    const construir = (definicion) => (definicion.pasos() || []).reduce((lista, paso) => {
        try {
            if (paso.cuando && !paso.cuando()) return lista;

            const el = paso.element ? resolver(paso.element) : null;

            if (paso.element && !visible(el)) return lista;

            lista.push({
                element: el || undefined,
                popover: {
                    title: paso.title,
                    description: paso.text,
                    side: typeof paso.side === 'function' ? paso.side() : paso.side
                }
            });
        } catch (error) {
            console.warn(`Tutorial: paso "${paso.id}" omitido.`, error);
        }

        return lista;
    }, []);

    const liberar = () => {
        activo = false;
        if (boton) boton.disabled = false;
        document.body.classList.remove('tut-activo');
    };

    const ocultarTooltips = () => document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        try {
            bootstrap.Tooltip.getInstance(el)?.hide();
        } catch (error) {
            console.warn('Tutorial:', error);
        }
    });

    const iniciar = async () => {
        const definicion = registros[clave];

        if (!definicion || activo) return;

        activo = true;
        boton.disabled = true;
        let iniciado = false;

        try {
            if (document.querySelector('.modal.show, .offcanvas.show')) {
                toast('info', 'Cierre la ventana abierta para iniciar el tutorial.');
                return;
            }

            const bloqueo = definicion.antesDeIniciar?.();

            if (bloqueo) {
                toast('info', bloqueo);
                return;
            }

            const respuesta = await Swal.fire({
                icon: 'question',
                title: '¿Desea ver el tutorial del módulo?',
                showCancelButton: true,
                confirmButtonText: 'Sí, iniciar tutorial',
                cancelButtonText: 'No, gracias',
                allowOutsideClick: false,
                confirmButtonColor: '#1dab4d',
                cancelButtonColor: '#6c757d'
            });

            if (!respuesta.isConfirmed) return;

            ocultarTooltips();

            try {
                await cargar();
            } catch (error) {
                console.warn('Tutorial:', error);
                toast('error', 'No se pudo cargar el tutorial. Verifique su conexión e intente de nuevo.');
                return;
            }

            const pasos = construir(definicion);

            if (!pasos.some((paso) => paso.element)) {
                toast('info', 'No hay elementos del tutorial disponibles en este momento.');
                return;
            }

            document.body.classList.add('tut-activo');

            window.driver.js.driver({
                steps: pasos,
                showProgress: true,
                progressText: '{{current}} de {{total}}',
                nextBtnText: 'Siguiente',
                prevBtnText: 'Anterior',
                doneBtnText: 'Finalizar',
                allowClose: true,
                overlayClickBehavior: 'close',
                overlayColor: '#000',
                overlayOpacity: 0.55,
                stagePadding: 6,
                stageRadius: 8,
                smoothScroll: true,
                popoverClass: 'app-tour',
                onPopoverRender: (popover, { state }) => {
                    popover.closeButton.setAttribute('aria-label', 'Cerrar tutorial');
                    if (state.activeIndex === 0) popover.previousButton.style.display = 'none';
                },
                onDestroyed: liberar
            }).drive();

            iniciado = true;
        } catch (error) {
            console.warn('Tutorial:', error);
        } finally {
            if (!iniciado) liberar();
        }
    };

    boton?.addEventListener('click', iniciar);

    window.Tutoriales = {
        registrar(nombre, definicion) {
            if (!boton || !definicion || typeof definicion.pasos !== 'function') return;

            registros[nombre] = definicion;
            clave = nombre;
            boton.closest('li')?.classList.remove('d-none');
        }
    };
})();
