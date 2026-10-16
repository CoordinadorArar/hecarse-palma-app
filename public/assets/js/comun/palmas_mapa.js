window.PalmasMapa = (() => {
    const PALETA_SANITARIA = {
        'emision-sana': { nombre: 'Emisión sana', relleno: '#22c55e', borde: '#ffffff' },
        'de-alta': { nombre: 'De alta', relleno: '#a3e635', borde: '#ffffff' },
        'emision-enferma': { nombre: 'Emisión enferma', relleno: '#fde047', borde: '#1f2937' },
        'grado-1': { nombre: 'Grado 1', relleno: '#fdba74', borde: '#1f2937' },
        'grado-2': { nombre: 'Grado 2', relleno: '#f97316', borde: '#ffffff' },
        'grado-3': { nombre: 'Grado 3', relleno: '#ef4444', borde: '#ffffff' },
        'grado-4': { nombre: 'Grado 4', relleno: '#b91c1c', borde: '#ffffff' },
        'grado-5': { nombre: 'Grado 5', relleno: '#6b0f1a', borde: '#ffffff' },
        'pudricion-cogollo': { nombre: 'Pudrición cogollo', relleno: '#d946ef', borde: '#ffffff' },
        'crater': { nombre: 'Cráter', relleno: '#8b5cf6', borde: '#ffffff' },
        'cogollo-doblado': { nombre: 'Cogollo doblado', relleno: '#3b82f6', borde: '#ffffff' },
        'otra': { nombre: 'Otra condición', relleno: '#22d3ee', borde: '#1f2937' },
        'sin-censo': { nombre: 'Sin censo', relleno: '#e5e7eb', borde: '#4b5563' },
        'erradicada': { nombre: 'Erradicada', relleno: 'transparent', borde: '#f8fafc' }
    };

    const ORDEN = Object.keys(PALETA_SANITARIA);

    const RADIOS = [[15, 1, 0], [16, 1.5, 0.5], [17, 3, 0.75], [18, 5, 1], [19, 7, 1]];

    const normalizar = (texto) => String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/\s+/g, ' ').trim();

    const claveSanitaria = (descripcion, estadoPalma) => {
        if (normalizar(estadoPalma) === 'ERRADICADA') return 'erradicada';
        const d = normalizar(descripcion);
        if (d === '') return 'sin-censo';
        if (d.includes('EMISION SANA')) return 'emision-sana';
        if (d.includes('EMISION ENFERMA')) return 'emision-enferma';
        if (d.includes('DE ALTA')) return 'de-alta';
        const grado = d.match(/GRADO\s*([1-5])\b/);
        if (grado) return 'grado-' + grado[1];
        if (d.includes('PUDRICION')) return 'pudricion-cogollo';
        if (d.includes('CRATER')) return 'crater';
        if (d.includes('COGOLLO DOBLADO')) return 'cogollo-doblado';
        return 'otra';
    };

    const claveFila = (fila, caracteristicas) => claveSanitaria(fila[6] === null || fila[6] === undefined ? '' : (caracteristicas?.[fila[6]]?.descripcion ?? String(fila[6])), fila[5]);

    const radioPalma = (zoom) => {
        const fila = RADIOS.find(([z]) => zoom <= z);
        return fila ? { radio: fila[1], weight: fila[2] } : { radio: 9, weight: 1.25 };
    };

    const estiloPalma = (palma, zoom) => {
        const clave = typeof palma === 'string' ? palma : palma.clave;
        const { radio, weight } = radioPalma(zoom);
        const color = PALETA_SANITARIA[clave] || PALETA_SANITARIA.otra;
        if (clave === 'erradicada') {
            return { radius: radio, weight: 1.5, color: color.borde, opacity: 1, fill: true, fillColor: '#000000', fillOpacity: 0, dashArray: '2 2' };
        }
        return { radius: radio, weight, color: color.borde, opacity: 1, fill: true, fillColor: color.relleno, fillOpacity: clave === 'sin-censo' ? 0.75 : 0.95, dashArray: null };
    };

    const capasBase = () => ({
        'Satélite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxNativeZoom: 17,
            maxZoom: 21,
            attribution: 'Tiles © Esri — Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community'
        }),
        'Calles': L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxNativeZoom: 19,
            maxZoom: 21,
            attribution: '© OpenStreetMap contributors'
        })
    });

    const escapar = (valor) => String(valor ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const muestra = (clave) => {
        const color = PALETA_SANITARIA[clave] || PALETA_SANITARIA.otra;
        const extra = clave === 'erradicada' || clave === 'sin-censo' ? ' ' + clave : '';
        return `<span class="gp-mapa-punto${extra}" style="--gp-sanitario:${color.relleno}" aria-hidden="true"></span>`;
    };

    const fecha = (valor) => {
        const m = String(valor ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
    };

    const pantallaCompleta = (mapa, elemento) => {
        const raiz = document.documentElement;
        const nativo = Boolean((raiz.requestFullscreen || raiz.webkitRequestFullscreen) && (document.fullscreenEnabled || document.webkitFullscreenEnabled));
        const enNativo = () => Boolean(document.fullscreenElement || document.webkitFullscreenElement);
        let activo = false;
        let boton = null;

        const pintar = () => {
            elemento.classList.toggle('gp-pantalla-completa', activo);
            document.body.classList.toggle('gp-pantalla-bloqueo', activo);
            if (boton) {
                const texto = activo ? 'Salir de pantalla completa' : 'Pantalla completa';
                boton.innerHTML = `<i class="bi ${activo ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'}" aria-hidden="true"></i>`;
                boton.title = texto;
                boton.setAttribute('aria-label', texto);
                boton.setAttribute('aria-pressed', String(activo));
            }
            setTimeout(() => mapa.invalidateSize({ pan: false }), 60);
        };

        const fijar = (valor) => {
            if (activo === valor) return;
            activo = valor;
            pintar();
        };

        const entrar = () => {
            fijar(true);
            if (!nativo || enNativo()) return;
            const pedir = raiz.requestFullscreen || raiz.webkitRequestFullscreen;
            Promise.resolve(pedir.call(raiz)).catch(() => {});
        };

        const salir = () => {
            fijar(false);
            if (nativo && enNativo()) Promise.resolve((document.exitFullscreen || document.webkitExitFullscreen).call(document)).catch(() => {});
        };

        const alCambiar = () => {
            if (!enNativo() && activo) fijar(false);
        };

        const alTecla = (evento) => {
            if (evento.key !== 'Escape' || !activo || enNativo()) return;
            if (document.querySelector('.modal.show, .swal2-container')) return;
            salir();
        };

        document.addEventListener('fullscreenchange', alCambiar);
        document.addEventListener('webkitfullscreenchange', alCambiar);
        document.addEventListener('keydown', alTecla);

        const Control = L.Control.extend({
            options: { position: 'topleft' },
            onAdd() {
                const caja = L.DomUtil.create('div', 'leaflet-bar leaflet-control gp-control-pantalla');
                boton = L.DomUtil.create('a', '', caja);
                boton.href = '#';
                boton.setAttribute('role', 'button');
                L.DomEvent.disableClickPropagation(caja);
                L.DomEvent.on(boton, 'click', (evento) => {
                    L.DomEvent.preventDefault(evento);
                    if (activo) salir();
                    else entrar();
                });
                pintar();
                return caja;
            }
        });

        new Control().addTo(mapa);
        mapa.on('unload', () => {
            if (activo) salir();
            document.removeEventListener('fullscreenchange', alCambiar);
            document.removeEventListener('webkitfullscreenchange', alCambiar);
            document.removeEventListener('keydown', alTecla);
        });
    };

    return { PALETA_SANITARIA, ORDEN, normalizar, claveSanitaria, claveFila, radioPalma, estiloPalma, capasBase, escapar, muestra, fecha, pantallaCompleta };
})();
