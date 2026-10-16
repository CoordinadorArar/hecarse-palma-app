const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

const Alerta = Swal.mixin({ confirmButtonColor: '#1dab4d' });

const PM = window.PalmasMapa;
const URL_MAPA = BASE_URL + 'gestion-palma/lotes/mapa/';
const $id = (id) => document.getElementById(id);
const esc = PM.escapar;
const num = (n, d = 0) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: d, maximumFractionDigits: d });
const REDUCIR = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const FINO = window.matchMedia('(pointer: fine)').matches;
const TACTIL = window.matchMedia('(pointer: coarse)').matches;
const esMovil = () => window.matchMedia('(max-width: 991.98px)').matches;

const ESTILO_ACTIVO = { color: '#ffffff', weight: 2.5, fillColor: '#41a867', fillOpacity: 0.1, interactive: false };
const ESTILO_CONTEXTO = { color: 'rgba(255,255,255,.75)', weight: 1, dashArray: '4 4', fill: false };
const ESTILO_EDICION = { color: '#fde047', weight: 3, fillColor: '#fde047', fillOpacity: 0.08 };

const est = {
    lotes: [],
    lote: null,
    palmas: new Map(),
    caracteristicas: {},
    catalogo: null,
    sel: null,
    vista: 'resumen',
    modo: 'seleccionar',
    ocultas: new Set(),
    resaltarFuera: false,
    fueraTrasGuardar: 0,
    historial: null,
    detalle: null,
    verTodo: false,
    agregar: { linea: '', siguiente: '', pila: [], ubicar: 'linea', lat: '', lon: '' },
    mover: null,
    edicion: null,
    solicitud: 0,
    reintentar: null
};

const hoy = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const nombre = (r) => `${r.linea}.${r.palma}`;

const anunciar = (texto) => {
    $id('mapa_anuncio').textContent = texto;
};

const avisar = (response, mensaje) => {
    const texto = mensaje || 'No se pudo completar la operación.';
    if (response.status === 401 || texto.length > 70) {
        Alerta.fire({ icon: 'warning', title: response.status === 401 ? 'Sesión expirada' : 'Atención', text: texto });
        return;
    }
    Toast.fire({ icon: 'warning', title: texto });
};

const api = async (accion, datos = {}) => {
    const body = new FormData();
    Object.entries({ empresa: $id('empresa').value, ...datos }).forEach(([clave, valor]) => body.append(clave, valor ?? ''));
    try {
        const response = await fetch(URL_MAPA + accion, { method: 'POST', body });
        const result = await response.json().catch(() => null);
        if (!response.ok || !result || !result.success) {
            return { error: result?.message || 'No se pudo completar la operación.', response };
        }
        return result;
    } catch (error) {
        return { error: 'Error de conexión.', response: { status: 0 } };
    }
};

const mapa = L.map('mapa_lote', { maxZoom: 21 }).setView([4.57, -74.3], 6);
const capasBase = PM.capasBase();
capasBase['Satélite'].addTo(mapa);
L.control.layers(capasBase, null, { position: 'topright', collapsed: esMovil() }).addTo(mapa);
L.control.scale({ imperial: false }).addTo(mapa);
PM.pantallaCompleta(mapa, document.querySelector('.gp-mapa-card'));
mapa.createPane('palmas').style.zIndex = 450;

const lienzo = L.canvas({ pane: 'palmas', padding: 0.5, tolerance: L.Browser.touch ? 10 : 4 });
const capaLotes = L.layerGroup().addTo(mapa);
const capaPalmas = L.layerGroup().addTo(mapa);
const capaSel = L.layerGroup().addTo(mapa);
const capaExtra = L.layerGroup().addTo(mapa);
const fantasma = L.circleMarker([0, 0], { renderer: lienzo, interactive: false, color: '#ffffff', weight: 1, fillColor: '#22c55e', fillOpacity: 0.5, dashArray: '2 2' });
const sugerida = L.circleMarker([0, 0], { renderer: lienzo, interactive: false, radius: 7, color: '#fde047', weight: 2, fillColor: '#22c55e', fillOpacity: 0.7, dashArray: '3 2' });

const DISTANCIA_PALMAS = 9;

const metrosPorGrado = (lat) => ({ x: 111320 * Math.cos(lat * Math.PI / 180), y: 110540 });

const palmasDeLinea = (linea) => [...est.palmas.values()].filter((p) => !p.pendiente && Number(p.linea) === linea).sort((a, b) => a.palma - b.palma);

const direccionLinea = (filas) => {
    if (filas.length < 2) return null;
    const a = filas[0];
    const b = filas[filas.length - 1];
    const k = metrosPorGrado(a.lat);
    const dx = (b.lon - a.lon) * k.x;
    const dy = (b.lat - a.lat) * k.y;
    const largo = Math.hypot(dx, dy);
    return largo < 1 ? null : { x: dx / largo, y: dy / largo };
};

const posicionSugerida = () => {
    const linea = Number(est.agregar.linea);
    const numero = Number(est.agregar.siguiente);
    if (!(linea > 0) || !(numero > 0)) return null;
    const filas = palmasDeLinea(linea);
    const previas = filas.filter((p) => p.palma < numero);
    const base = previas[previas.length - 1];
    if (!base) return null;
    const dir = direccionLinea(previas.length >= 2 ? previas : filas) || direccionLinea(palmasDeLinea(linea - 1)) || direccionLinea(palmasDeLinea(linea + 1));
    if (!dir) return null;
    const k = metrosPorGrado(base.lat);
    const metros = DISTANCIA_PALMAS * (numero - base.palma);
    return { latlng: L.latLng(base.lat + dir.y * metros / k.y, base.lon + dir.x * metros / k.x), base };
};

const coordenadasAgregar = () => {
    const lat = Number(String(est.agregar.lat).replace(',', '.'));
    const lon = Number(String(est.agregar.lon).replace(',', '.'));
    if (String(est.agregar.lat).trim() === '' || String(est.agregar.lon).trim() === '') return null;
    if (!Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) return null;
    return L.latLng(lat, lon);
};

const destinoAgregar = () => (est.agregar.ubicar === 'coordenadas' ? coordenadasAgregar() : posicionSugerida()?.latlng || null);

const volar = (latlng, zoom) => (REDUCIR ? mapa.setView(latlng, zoom, { animate: false }) : mapa.flyTo(latlng, zoom, { duration: 0.6 }));

const encuadrar = (limites) => {
    if (!limites.isValid()) return false;
    mapa.fitBounds(limites, { paddingTopLeft: [24, 24], paddingBottomRight: [24, esMovil() ? 96 : 24], maxZoom: 19, animate: !REDUCIR });
    return true;
};

const latlngsDe = (geo) => L.GeoJSON.coordsToLatLngs(geo.coordinates, geo.type === 'Polygon' ? 1 : 2);

const enAnillo = (anillo, x, y) => {
    let dentro = false;
    for (let i = 0, j = anillo.length - 1; i < anillo.length; j = i++) {
        if ((anillo[i][1] > y) !== (anillo[j][1] > y) && x < (anillo[j][0] - anillo[i][0]) * (y - anillo[i][1]) / (anillo[j][1] - anillo[i][1]) + anillo[i][0]) dentro = !dentro;
    }
    return dentro;
};

const dentroLote = (lon, lat) => {
    const geo = est.lote?.geojson;
    if (!geo) return true;
    const poligonos = geo.type === 'Polygon' ? [geo.coordinates] : geo.coordinates;
    return poligonos.some((p) => enAnillo(p[0], lon, lat) && !p.slice(1).some((h) => enAnillo(h, lon, lat)));
};

const descripcionCar = (codigo) => (codigo === null || codigo === undefined ? '' : (est.caracteristicas[codigo]?.descripcion ?? String(codigo)));

const completar = (r) => {
    r.clave = PM.claveSanitaria(descripcionCar(r.car), r.estado);
    r.fuera = r.estado === 'ACTIVA' && !dentroLote(r.lon, r.lat);
    return r;
};

const registro = (f) => ({ id: f[0], linea: f[1], palma: f[2], lat: f[3], lon: f[4], estado: f[5], car: f[6], fecha: f[7] });

const estiloDe = (r) => {
    const e = PM.estiloPalma(r.clave, mapa.getZoom());
    if (r.pendiente) return { ...e, color: '#ffffff', fillColor: PM.PALETA_SANITARIA['emision-sana'].relleno, fillOpacity: 0.6, opacity: 0.6, dashArray: '2 2' };
    if (r.fuera) Object.assign(e, { color: '#f59e0b', weight: 2, dashArray: '3 2' });
    if (est.resaltarFuera && !r.fuera) Object.assign(e, { opacity: 0.25, fillOpacity: 0.25 });
    return e;
};

const visible = (r) => !est.ocultas.has(r.clave) && est.mover?.r !== r;

const crearMarcador = (r) => {
    r.marcador = L.circleMarker([r.lat, r.lon], { renderer: lienzo, bubblingMouseEvents: false, ...estiloDe(r) });
    r.marcador.on('click', () => clicPalma(r));
    if (visible(r)) capaPalmas.addLayer(r.marcador);
    return r;
};

const aplicarVisibilidad = () => {
    est.palmas.forEach((r) => {
        const v = visible(r);
        if (v !== capaPalmas.hasLayer(r.marcador)) v ? capaPalmas.addLayer(r.marcador) : capaPalmas.removeLayer(r.marcador);
    });
    $id('ver_erradicadas').checked = !est.ocultas.has('erradicada');
};

let tareaEstilo = 0;
const reestilar = () => {
    const lista = [...est.palmas.values()];
    const tarea = ++tareaEstilo;
    let i = 0;
    const paso = () => {
        if (tarea !== tareaEstilo) return;
        const fin = Math.min(i + 1500, lista.length);
        for (; i < fin; i++) lista[i].marcador.setStyle(estiloDe(lista[i]));
        if (i < lista.length) requestAnimationFrame(paso);
    };
    paso();
    dibujarSeleccion();
};

const dibujarSeleccion = () => {
    capaSel.clearLayers();
    const r = est.palmas.get(est.sel);
    if (!r || est.mover) return;
    const { radio } = PM.radioPalma(mapa.getZoom());
    L.circleMarker([r.lat, r.lon], { renderer: lienzo, interactive: false, radius: radio + 6, color: '#111827', weight: 4, opacity: 0.55, fill: false }).addTo(capaSel);
    L.circleMarker([r.lat, r.lon], { renderer: lienzo, interactive: false, radius: radio + 4, color: '#ffffff', weight: 2.5, fill: false }).addTo(capaSel);
};

const dibujarLotes = () => {
    capaLotes.clearLayers();
    est.lotes.filter((l) => l.geojson).forEach((l) => {
        const activo = est.lote?.codigo === l.codigo;
        if (activo && est.edicion) return;
        const capa = L.polygon(latlngsDe(l.geojson), activo ? ESTILO_ACTIVO : ESTILO_CONTEXTO).addTo(capaLotes);
        capa.bindTooltip(esc(l.codigo), { permanent: true, direction: 'center', className: 'gp-mapa-etiqueta', interactive: false });
        if (activo) return;
        capa.on('mouseover', () => capa.setStyle({ weight: 2 }));
        capa.on('mouseout', () => capa.setStyle({ weight: 1 }));
        capa.on('click', () => {
            if (est.modo === 'seleccionar') $('#lote').val(l.codigo).trigger('change');
        });
    });
};

const limitesLote = () => {
    const limites = L.latLngBounds([]);
    if (est.lote?.geojson) return L.polygon(latlngsDe(est.lote.geojson)).getBounds();
    est.palmas.forEach((r) => limites.extend([r.lat, r.lon]));
    return limites;
};

const encuadrarFinca = () => {
    const limites = L.latLngBounds([]);
    est.lotes.forEach((l) => {
        if (l.geojson) limites.extend(L.polygon(latlngsDe(l.geojson)).getBounds());
        else if (l.centro) limites.extend(l.centro);
    });
    if (!encuadrar(limites)) mapa.setView([4.57, -74.3], 6, { animate: false });
};

const encuadrarLote = () => {
    if (!encuadrar(limitesLote())) encuadrarFinca();
};

const conteos = () => {
    const c = {};
    est.palmas.forEach((r) => {
        c[r.clave] = (c[r.clave] || 0) + 1;
    });
    return c;
};

const pintarKpis = () => {
    const contenedor = $id('kpis');
    contenedor.classList.toggle('d-none', !est.cargado);
    if (!est.cargado) return;
    let activas = 0, erradicadas = 0, censadas = 0, fuera = 0;
    est.palmas.forEach((r) => {
        if (r.pendiente) return;
        if (r.estado === 'ERRADICADA') {
            erradicadas++;
            return;
        }
        activas++;
        if (r.car !== null && r.car !== undefined) censadas++;
        if (r.fuera) fuera++;
    });
    if (fuera === 0 && est.resaltarFuera) {
        est.resaltarFuera = false;
        reestilar();
    }
    const brutas = est.lote.palmasBrutas;
    const diferencia = activas - brutas;
    const avance = brutas > 0 ? Math.min(100, activas / brutas * 100) : 0;
    const kpi = (etiqueta, valor, extra = '') => `
        <div class="col-6 col-md-4 col-xl">
            <div class="card mb-0 gp-mapa-kpi h-100"><div class="card-body py-2 px-3">
                <span class="gp-etiqueta-mini">${etiqueta}</span>
                <div class="gp-mapa-kpi-valor">${valor}</div>${extra}
            </div></div>
        </div>`;
    contenedor.innerHTML = kpi('Palmas activas', `${num(activas)} <small>/ ${num(brutas)}</small>`, `
            <div class="progress gp-mapa-progreso mt-1" role="progressbar" aria-label="Palmas activas frente a palmas brutas" aria-valuenow="${Math.round(avance)}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="--gp-avance:${avance.toFixed(1)}%"></div></div>
            ${diferencia !== 0 ? `<span class="gp-chip-fila ambar mt-1">Descuadre ${diferencia > 0 ? '+' : '−'}${num(Math.abs(diferencia))}</span>` : ''}`)
        + kpi('Erradicadas', num(erradicadas))
        + kpi('Con censo', `${num(censadas)} <small>${activas ? Math.round(censadas / activas * 100) : 0} %</small>`)
        + kpi('Sin censo', num(activas - censadas))
        + (fuera > 0 ? `
        <div class="col-6 col-md-4 col-xl">
            <button type="button" class="card mb-0 gp-mapa-kpi alerta h-100 w-100 text-start" data-accion="ver-fuera" aria-pressed="${est.resaltarFuera}">
                <span class="card-body py-2 px-3 d-block w-100">
                    <span class="gp-etiqueta-mini d-block">Fuera del polígono</span>
                    <span class="gp-mapa-kpi-valor d-block">${num(fuera)}</span>
                    <span class="small"><i class="bi bi-${est.resaltarFuera ? 'eye-slash' : 'eye'} me-1"></i>${est.resaltarFuera ? 'Quitar resaltado' : 'Resaltar y encuadrar'}</span>
                </span>
            </button>
        </div>` : kpi('Fuera del polígono', '0'));
};

const banner = (html) => {
    const b = $id('banner_modo');
    b.classList.toggle('d-none', !html);
    b.innerHTML = html ? `<div class="gp-revelar d-flex flex-wrap align-items-center gap-2 w-100">${html}</div>` : '';
};

const htmlVacio = () => `
    <div class="gp-mapa-panel-cuerpo gp-estado-vacio text-center py-5">
        <div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-3"><i class="bi bi-geo-alt"></i></div>
        <h6>Seleccione un lote</h6>
        <p class="text-muted small mb-0">Elija empresa, finca y lote para ver sus palmas sobre el mapa.</p>
    </div>`;

const htmlLeyenda = () => {
    const conteo = conteos();
    const claves = PM.ORDEN.filter((k) => conteo[k]);
    return `
        <div class="gp-mapa-panel-cuerpo pt-0">
            <h6 class="gp-seccion"><i class="bi bi-heart-pulse"></i>Estado sanitario</h6>
            <div class="d-flex justify-content-end align-items-center gap-1 small mt-2 mb-1">
                <button type="button" class="btn btn-link btn-sm p-0" data-accion="leyenda-todas">Todas</button>
                <span class="text-muted">·</span>
                <button type="button" class="btn btn-link btn-sm p-0" data-accion="leyenda-ninguna">Ninguna</button>
            </div>
            <div class="gp-mapa-leyenda">
                ${claves.map((k) => `
                <label class="gp-mapa-leyenda-item">
                    <input class="form-check-input" type="checkbox" data-clave="${k}" ${est.ocultas.has(k) ? '' : 'checked'}>
                    ${PM.muestra(k)}
                    <span class="flex-grow-1">${esc(PM.PALETA_SANITARIA[k].nombre)}</span>
                    <span class="font-monospace gp-mapa-leyenda-conteo">${num(conteo[k])}</span>
                </label>`).join('')}
            </div>
            <div class="gp-nota mt-3"><i class="bi bi-hand-index me-1"></i>Toque una palma para ver su información.</div>
        </div>`;
};

const htmlResumen = () => {
    const l = est.lote;
    const vacio = est.palmas.size === 0;
    const notaPoligono = `<div class="gp-nota-alerta d-flex gap-2 mt-3"><i class="bi bi-exclamation-triangle"></i><div>Este lote no tiene polígono. Use <strong>Dibujar polígono</strong> para delimitarlo.</div></div>`;
    let cuerpo = '';
    if (!l.geojson && !vacio) cuerpo += notaPoligono;
    if (est.fueraTrasGuardar > 0) {
        cuerpo += `<div class="gp-nota-alerta d-flex gap-2 mt-3"><i class="bi bi-exclamation-triangle"></i><div>${num(est.fueraTrasGuardar)} ${est.fueraTrasGuardar === 1 ? 'palma queda' : 'palmas quedan'} fuera del polígono. <button type="button" class="btn btn-link btn-sm p-0 align-baseline" data-accion="ver-fuera-on">Ver palmas</button></div></div>`;
    }
    if (vacio) {
        cuerpo += l.geojson ? `
            <div class="gp-estado-vacio text-center py-4">
                <div class="gp-medallon chico d-inline-flex align-items-center justify-content-center rounded-circle mb-3"><i class="bi bi-flower1"></i></div>
                <h6>Este lote no tiene palmas mapeadas</h6>
                <button type="button" class="btn btn-success mt-2" data-accion="modo" data-valor="agregar"><i class="bi bi-plus-circle me-1"></i>Agregar palmas</button>
            </div>` : `${notaPoligono.replace('mt-3', 'mt-3 mb-3')}
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-success" data-accion="modo" data-valor="poligono"><i class="bi bi-pentagon me-1"></i>Dibujar polígono</button>
                <button type="button" class="btn btn-success" data-accion="modo" data-valor="agregar"><i class="bi bi-plus-circle me-1"></i>Agregar palmas</button>
            </div>`;
    }
    return `
        <div class="gp-mapa-panel-cuerpo">
            <div class="gp-mapa-ficha-titulo"><span class="font-monospace">${esc(l.codigo)}</span></div>
            <span class="gp-subtexto">${esc(l.descripcion)}${l.areaHa ? ` · ${num(l.areaHa, 2)} ha` : ''}${l.activo ? '' : ' · Inactivo'}</span>
            ${est.cargado ? cuerpo : ''}
        </div>
        ${vacio || !est.cargado ? '' : htmlLeyenda()}`;
};

const chipSanitario = (r) => {
    const clave = PM.claveSanitaria(descripcionCar(r.car), 'ACTIVA');
    return `<span class="gp-chip-fila gp-mapa-chip">${PM.muestra(clave)}${esc(r.car === null || r.car === undefined ? 'Sin censo' : descripcionCar(r.car))}</span>`;
};

const htmlPalma = (r) => `
    <div class="gp-mapa-panel-cuerpo">
        <div class="d-flex align-items-start gap-2">
            <div class="flex-grow-1 min-w-0">
                <div class="gp-mapa-ficha-titulo">Palma <span class="font-monospace">${esc(nombre(r))}</span></div>
                <div class="d-flex flex-wrap gap-1 mt-2">
                    ${chipSanitario(r)}
                    <span class="gp-chip-fila ${r.estado === 'ERRADICADA' ? 'gris' : 'verde'}">${esc(r.estado)}</span>
                    ${r.fuera ? '<span class="gp-chip-fila ambar">Fuera del polígono</span>' : ''}
                </div>
            </div>
            <button type="button" class="btn-close" data-accion="cerrar-ficha" aria-label="Cerrar ficha"></button>
        </div>
        <dl class="gp-mapa-datos mt-3">
            <div><dt class="gp-etiqueta-mini">Lote</dt><dd>${esc(est.lote.codigo)}</dd></div>
            <div><dt class="gp-etiqueta-mini">Línea</dt><dd class="font-monospace">${esc(r.linea)}</dd></div>
            <div><dt class="gp-etiqueta-mini">Palma</dt><dd class="font-monospace">${esc(r.palma)}</dd></div>
            <div><dt class="gp-etiqueta-mini">Estado</dt><dd>${r.estado === 'ERRADICADA' ? 'Erradicada' : 'Activa'}</dd></div>
            ${r.estado === 'ERRADICADA' && est.detalle?.fechaErradicacion ? `<div class="completa"><dt class="gp-etiqueta-mini">Fecha erradicación</dt><dd class="font-monospace">${PM.fecha(est.detalle.fechaErradicacion)}</dd></div>` : ''}
            <div class="completa"><dt class="gp-etiqueta-mini">Coordenadas</dt><dd class="d-flex align-items-center gap-2"><span class="gp-mapa-coordenadas">${r.lat.toFixed(6)}, ${r.lon.toFixed(6)}</span><button type="button" class="btn btn-link btn-sm p-0" data-accion="copiar" aria-label="Copiar coordenadas" title="Copiar coordenadas"><i class="bi bi-clipboard"></i></button></dd></div>
            <div class="completa"><dt class="gp-etiqueta-mini">Último censo</dt><dd>${r.fecha ? `<span class="font-monospace me-2">${PM.fecha(r.fecha)}</span>${chipSanitario(r)}` : '<span class="text-muted">Sin censo</span>'}</dd></div>
        </dl>
        ${est.detalle?.observacion ? `<div class="gp-nota mt-3"><i class="bi bi-chat-left-text me-1"></i>${esc(est.detalle.observacion)}</div>` : ''}
        ${est.detalle?.usuarioRegistro ? `<span class="gp-subtexto mt-2">Registrada por ${esc(est.detalle.usuarioRegistro)}${est.detalle.fechaRegistro ? ` el ${PM.fecha(est.detalle.fechaRegistro)}` : ''}</span>` : ''}
        <div class="gp-mapa-acciones">
            <button type="button" class="btn btn-success completa" data-accion="censo"><i class="bi bi-clipboard2-pulse me-1"></i>Registrar censo</button>
            <button type="button" class="btn btn-outline-primary" data-accion="editar" ${est.detalle ? '' : 'disabled'}><i class="bi bi-pencil me-1"></i>Editar</button>
            <button type="button" class="btn btn-outline-secondary" data-accion="mover-sel" disabled><i class="bi bi-arrows-move me-1"></i>Mover</button>
            <button type="button" class="btn btn-outline-danger completa mt-2" data-accion="eliminar"><i class="bi bi-trash me-1"></i>Eliminar</button>
        </div>
    </div>
    <div class="gp-mapa-panel-cuerpo pt-0">
        <h6 class="gp-seccion"><i class="bi bi-clock-history"></i>Historial de censos</h6>
        <div class="mt-3" id="historial"></div>
    </div>`;

const pintarHistorial = () => {
    const destino = $id('historial');
    if (!destino) return;
    if (est.historial === null) {
        destino.innerHTML = '<div class="d-flex align-items-center gap-2 small text-muted"><span class="spinner-border spinner-border-sm text-success" role="status"></span>Cargando historial…</div>';
        return;
    }
    if (est.historial.length === 0) {
        destino.innerHTML = '<div class="gp-nota">Sin censos registrados.</div>';
        return;
    }
    const lista = est.verTodo ? est.historial : est.historial.slice(0, 8);
    destino.innerHTML = `
        <ol class="gp-mapa-historial">
            ${lista.map((h) => {
                const clave = PM.claveSanitaria(h.descripcion, 'ACTIVA');
                return `<li style="--gp-sanitario:${PM.PALETA_SANITARIA[clave].relleno}">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="gp-mapa-historial-fecha">${PM.fecha(h.fecha)}</span>
                        <span class="gp-chip-fila gp-mapa-chip">${PM.muestra(clave)}${esc(h.descripcion)}</span>
                    </div>
                    <span class="gp-subtexto mt-1">${esc(h.grupoDescripcion || '')}${h.usuario ? ` · ${esc(h.usuario)}` : ''}</span>
                    ${h.observacion ? `<span class="gp-subtexto fst-italic">${esc(h.observacion)}</span>` : ''}
                </li>`;
            }).join('')}
        </ol>
        ${!est.verTodo && est.historial.length > 8 ? `<button type="button" class="btn btn-link btn-sm p-0" data-accion="ver-todo">Ver todos (${est.historial.length})</button>` : ''}`;
};

const campo = (id, etiqueta, control, col = 'col-12', requerido = false) => `
    <div class="${col}" id="col_${id}">
        <label class="form-label mb-1 small fw-semibold text-muted" for="${id}">${etiqueta}${requerido ? ' <span class="text-danger">*</span>' : ''}</label>
        ${control}
    </div>`;

const pie = () => `
    <div class="gp-mapa-panel-pie">
        <button type="button" class="btn btn-secondary" data-accion="volver-ficha"><i class="bi bi-x-circle me-1"></i>Cancelar</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Guardar</button>
    </div>`;

const htmlEditar = (r) => `
    <form id="form_editar" class="gp-mapa-form" novalidate>
        <div class="gp-mapa-panel-cuerpo">
            <div class="gp-mapa-ficha-titulo">Editar palma <span class="font-monospace">${esc(nombre(r))}</span></div>
            <span class="gp-subtexto mb-3">Lote ${esc(est.lote.codigo)}</span>
            <div class="row g-3">
                ${campo('ed_linea', 'Línea', `<input type="number" class="form-control font-monospace" id="ed_linea" min="1" step="1" value="${esc(r.linea)}" required>`, 'col-6', true)}
                ${campo('ed_palma', 'Número', `<input type="number" class="form-control font-monospace" id="ed_palma" min="1" step="1" value="${esc(r.palma)}" required><div class="invalid-feedback" id="ed_palma_error"></div>`, 'col-6', true)}
                ${campo('ed_estado', 'Estado', `<select class="form-select" id="ed_estado"><option value="ACTIVA" ${r.estado === 'ACTIVA' ? 'selected' : ''}>ACTIVA</option><option value="ERRADICADA" ${r.estado === 'ERRADICADA' ? 'selected' : ''}>ERRADICADA</option></select>`)}
                ${campo('ed_fecha', 'Fecha erradicación', `<input type="date" class="form-control" id="ed_fecha" max="${hoy()}" value="${esc(String(est.detalle?.fechaErradicacion || (r.estado === 'ERRADICADA' ? hoy() : '')).slice(0, 10))}"><div class="invalid-feedback">Indique una fecha válida, no posterior a hoy.</div>`, `col-12 ${r.estado === 'ERRADICADA' ? '' : 'd-none'}`, true)}
                ${campo('ed_obs', 'Observación', `<textarea class="form-control" id="ed_obs" rows="3" maxlength="250">${esc(est.detalle?.observacion || '')}</textarea><div class="form-text">Máximo 250 caracteres.</div>`)}
            </div>
        </div>
        ${pie()}
    </form>`;

const htmlCenso = (r) => `
    <form id="form_censo" class="gp-mapa-form" novalidate>
        <div class="gp-mapa-panel-cuerpo">
            <div class="gp-mapa-ficha-titulo">Registrar censo</div>
            <span class="gp-subtexto mb-3">Palma ${esc(nombre(r))} · Lote ${esc(est.lote.codigo)}</span>
            <div class="row g-3">
                ${campo('cn_fecha', 'Fecha', `<input type="date" class="form-control" id="cn_fecha" value="${hoy()}" max="${hoy()}" required><div class="invalid-feedback">Indique una fecha válida, no posterior a hoy.</div>`, 'col-12', true)}
                ${campo('cn_car', 'Característica', `<div class="input-group gp-select-buscador has-validation"><span class="input-group-text bg-light"><i class="bi bi-clipboard2-pulse"></i></span><select class="form-select" id="cn_car" required><option value=""></option>${
                    [...new Set(est.catalogo.map((c) => c.grupoDescripcion))].map((g) => `<optgroup label="${esc(g)}">${est.catalogo.filter((c) => c.grupoDescripcion === g).map((c) => `<option value="${esc(c.codigo)}">${esc(c.descripcion)}</option>`).join('')}</optgroup>`).join('')
                }</select></div>`, 'col-12', true)}
                ${campo('cn_obs', 'Observación', '<textarea class="form-control" id="cn_obs" rows="3" maxlength="250"></textarea><div class="form-text">Opcional. Máximo 250 caracteres.</div>')}
            </div>
        </div>
        ${pie()}
    </form>`;

const htmlAgregar = () => `
    <form id="form_agregar" class="gp-mapa-form" novalidate>
    <div class="gp-mapa-panel-cuerpo">
        <div class="gp-mapa-ficha-titulo">Agregar palmas</div>
        <span class="gp-subtexto mb-3">Lote ${esc(est.lote.codigo)}. Ubique cada palma por línea o por coordenadas.</span>
        <div class="btn-group w-100 mb-3" role="group" aria-label="Forma de ubicar la palma">
            <button type="button" class="btn btn-sm ${est.agregar.ubicar === 'linea' ? 'btn-success' : 'btn-outline-success'}" data-accion="ag-ubicar" data-valor="linea" aria-pressed="${est.agregar.ubicar === 'linea'}"><i class="bi bi-distribute-horizontal me-1"></i>Por línea</button>
            <button type="button" class="btn btn-sm ${est.agregar.ubicar === 'coordenadas' ? 'btn-success' : 'btn-outline-success'}" data-accion="ag-ubicar" data-valor="coordenadas" aria-pressed="${est.agregar.ubicar === 'coordenadas'}"><i class="bi bi-geo-alt me-1"></i>Por coordenadas</button>
        </div>
        <div class="row g-3">
            ${campo('ag_linea', 'Línea', `<input type="number" class="form-control font-monospace" id="ag_linea" min="1" step="1" value="${esc(est.agregar.linea)}" required>`, 'col-6', true)}
            ${campo('ag_numero', 'Siguiente número', `<input type="number" class="form-control font-monospace" id="ag_numero" min="1" step="1" value="${esc(est.agregar.siguiente)}">`, 'col-6')}
            ${est.agregar.ubicar === 'coordenadas' ? `
            ${campo('ag_lat', 'Latitud', `<input type="text" inputmode="decimal" class="form-control font-monospace" id="ag_lat" placeholder="7.5150000" value="${esc(est.agregar.lat)}" required>`, 'col-6', true)}
            ${campo('ag_lon', 'Longitud', `<input type="text" inputmode="decimal" class="form-control font-monospace" id="ag_lon" placeholder="-73.7450000" value="${esc(est.agregar.lon)}" required>`, 'col-6', true)}
            <div class="col-12"><span class="form-text mt-0">Grados decimales (WGS84). Tocar el mapa llena estos campos.</span></div>` : ''}
        </div>
        <div class="gp-nota mt-3" id="ag_sugerencia" role="status"></div>
        <div class="d-grid mt-3">
            <button type="button" class="btn btn-success" data-accion="agregar-palma" id="btn_agregar_palma" disabled><i class="bi bi-plus-circle me-1"></i>Agregar palma</button>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <span class="gp-contador" id="ag_contador">Agregadas en esta sesión: <strong>${est.agregar.pila.length}</strong></span>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-accion="deshacer" ${est.agregar.pila.length ? '' : 'disabled'}><i class="bi bi-arrow-counterclockwise me-1"></i>Deshacer última</button>
        </div>
        <div class="d-grid mt-3">
            <button type="button" class="btn btn-outline-secondary" data-accion="salir-modo"><i class="bi bi-check2 me-1"></i>Terminar</button>
        </div>
    </div>
    </form>`;

const formatoOpcion = (opcion) => (opcion.id ? $(`<span class="d-inline-flex align-items-center gap-2">${PM.muestra(PM.claveSanitaria(opcion.text, 'ACTIVA'))}${esc(opcion.text)}</span>`) : opcion.text);

const pintarPanel = (animar = true) => {
    const r = est.palmas.get(est.sel);
    let html;
    if (!est.lote) html = htmlVacio();
    else if (est.vista === 'agregar') html = htmlAgregar();
    else if (r && est.vista === 'editar') html = htmlEditar(r);
    else if (r && est.vista === 'censo') html = htmlCenso(r);
    else if (r) html = htmlPalma(r);
    else html = htmlResumen();
    $id('panel_contenido').innerHTML = `<div class="${animar ? 'gp-revelar ' : ''}gp-mapa-panel-contenido">${html}</div>`;
    if ($id('historial')) pintarHistorial();
    if ($id('ag_sugerencia')) refrescarSugerencia();
    if ($id('cn_car')) {
        $('#cn_car').select2({
            width: '100%',
            placeholder: 'Seleccione una característica…',
            templateResult: formatoOpcion,
            templateSelection: formatoOpcion,
            language: { noResults: () => 'No se encontraron resultados', searching: () => 'Buscando…' }
        });
    }
};

const vista = (v) => {
    est.vista = v;
    pintarPanel();
};

const colapsar = (si) => {
    $id('panel_mapa').classList.toggle('colapsado', si);
    $id('asa_panel').setAttribute('aria-expanded', String(!si));
};

const cargarHistorial = async (r) => {
    const res = await api('historial-censo', { palma: r.id });
    if (est.sel !== r.id) return;
    est.historial = res.error ? [] : res.historial;
    est.detalle = res.error ? null : res.detalle || null;
    if (res.error) Toast.fire({ icon: 'warning', title: res.error });
    if (est.vista === 'palma') pintarPanel(false);
    else pintarHistorial();
};

const seleccionar = (id) => {
    est.sel = id;
    est.historial = null;
    est.detalle = null;
    est.verTodo = false;
    dibujarSeleccion();
    if (id === null) {
        vista('resumen');
        return;
    }
    const r = est.palmas.get(id);
    vista('palma');
    colapsar(false);
    anunciar(`Palma ${nombre(r)} seleccionada`);
    cargarHistorial(r);
};

const clicPalma = (r) => {
    if (r.pendiente) return;
    if (est.modo === 'seleccionar') seleccionar(r.id);
    else if (est.modo === 'mover' && !est.mover) iniciarMover(r);
};

const actualizarFila = (fila) => {
    const r = est.palmas.get(fila[0]);
    if (!r) return agregarFila(fila);
    Object.assign(r, registro(fila));
    completar(r);
    r.marcador.setLatLng([r.lat, r.lon]).setStyle(estiloDe(r));
    aplicarVisibilidad();
    pintarKpis();
    dibujarSeleccion();
    return r;
};

const agregarFila = (fila) => {
    const r = crearMarcador(completar(registro(fila)));
    est.palmas.set(r.id, r);
    pintarKpis();
    return r;
};

const quitarFila = (id) => {
    const r = est.palmas.get(id);
    if (!r) return;
    capaPalmas.removeLayer(r.marcador);
    est.palmas.delete(id);
    if (est.sel === id) est.sel = null;
    dibujarSeleccion();
    pintarKpis();
};

const actualizarBotones = () => {
    const hay = !!est.lote && !!est.cargado;
    document.querySelectorAll('[data-modo]').forEach((b) => {
        const activo = b.dataset.modo === est.modo;
        b.classList.toggle('btn-success', activo);
        b.classList.toggle('btn-outline-success', !activo);
        b.setAttribute('aria-pressed', String(activo));
        if (b.dataset.modo !== 'seleccionar') b.disabled = !hay || b.dataset.modo === 'mover';
    });
    const textoPoligono = est.lote?.geojson || !est.lote ? 'Polígono' : 'Dibujar polígono';
    $id('texto_poligono').textContent = textoPoligono;
    document.querySelector('[data-modo="poligono"]').setAttribute('aria-label', textoPoligono);
    document.querySelector('[data-modo="poligono"]').title = textoPoligono;
    $id('btn_encuadrar').disabled = !hay;
    $id('buscar_palma').disabled = !hay;
    $id('btn_buscar').disabled = !hay;
};

const bannerAgregar = () => {
    const { linea, siguiente } = est.agregar;
    const destino = destinoAgregar();
    banner(`<i class="bi bi-plus-circle text-success"></i><span>${linea ? `Agregando en línea <strong>${esc(linea)}</strong> · siguiente palma <strong>${esc(siguiente || '—')}</strong>` : 'Indique la línea para empezar'}</span>
        <span class="gp-mapa-modo-acciones">${destino && Number(siguiente) > 0 ? '<button type="button" class="btn btn-success btn-sm" data-accion="agregar-palma"><i class="bi bi-plus-circle me-1"></i>Agregar</button>' : ''}<button type="button" class="btn btn-outline-success btn-sm" data-accion="salir-modo"><i class="bi bi-check2 me-1"></i>Terminar</button></span>`);
};

const refrescarSugerencia = () => {
    if (est.modo !== 'agregar') {
        sugerida.remove();
        return;
    }
    const porLinea = est.agregar.ubicar === 'linea';
    const s = porLinea ? posicionSugerida() : null;
    const destino = destinoAgregar();
    const listo = !!destino && Number(est.agregar.linea) > 0 && Number(est.agregar.siguiente) > 0;
    const nota = $id('ag_sugerencia');

    if (destino) sugerida.setLatLng(destino).addTo(mapa);
    else sugerida.remove();
    if (nota) {
        let texto;
        if (!(Number(est.agregar.linea) > 0)) texto = 'Indique la línea para empezar.';
        else if (porLinea && s) texto = `<i class="bi bi-geo-alt me-1"></i>Posición sugerida a ${DISTANCIA_PALMAS} m de la palma <strong>${esc(nombre(s.base))}</strong>, siguiendo la dirección de la línea. Puede tocar el mapa para ubicarla en otro punto.`;
        else if (porLinea) texto = '<i class="bi bi-info-circle me-1"></i>No hay palmas previas suficientes en esta línea para calcular la posición. Use «Por coordenadas» o toque el mapa.';
        else texto = destino ? `<i class="bi bi-geo-alt me-1"></i>Se ubicará en <span class="font-monospace">${destino.lat.toFixed(7)}, ${destino.lng.toFixed(7)}</span>.` : '<i class="bi bi-info-circle me-1"></i>Ingrese latitud y longitud válidas.';
        nota.innerHTML = texto;
    }
    if ($id('btn_agregar_palma')) $id('btn_agregar_palma').disabled = !listo;
    bannerAgregar();
};

const agregarPalma = () => {
    const destino = destinoAgregar();
    if (!destino) return;
    if (!mapa.getBounds().contains(destino)) volar(destino, Math.max(mapa.getZoom(), 18));
    colocar(destino);
};

const sincronizarAgregar = () => {
    if ($id('ag_numero')) $id('ag_numero').value = est.agregar.siguiente;
    if ($id('ag_contador')) $id('ag_contador').innerHTML = `Agregadas en esta sesión: <strong>${est.agregar.pila.length}</strong>`;
    const deshacer = document.querySelector('[data-accion="deshacer"]');
    if (deshacer) deshacer.disabled = est.agregar.pila.length === 0;
    refrescarSugerencia();
};

const pedirSiguiente = async () => {
    const linea = est.agregar.linea;
    if (!(Number(linea) > 0)) return;
    const res = await api('siguiente-numero', { finca: est.lote.finca, lote: est.lote.codigo, linea });
    if (res.error || est.agregar.linea !== linea) return;
    est.agregar.siguiente = String(res.siguiente);
    sincronizarAgregar();
};

const colocar = async (latlng) => {
    const linea = Number(est.agregar.linea);
    const numero = Number(est.agregar.siguiente);
    if (!(linea > 0) || !(numero > 0)) {
        anunciar('Indique la línea para empezar');
        return;
    }
    const lote = est.lote;
    const tmp = crearMarcador({ id: 'tmp', linea, palma: numero, lat: latlng.lat, lon: latlng.lng, estado: 'ACTIVA', car: null, fecha: null, clave: 'sin-censo', pendiente: true });
    est.agregar.siguiente = String(numero + 1);
    sincronizarAgregar();
    const res = await api('guardar-palma', {
        id: '', finca: lote.finca, lote: lote.codigo, linea, palma: numero,
        lat: latlng.lat.toFixed(7), lon: latlng.lng.toFixed(7), estado: 'ACTIVA', fechaErradicacion: '', observacion: ''
    });
    capaPalmas.removeLayer(tmp.marcador);
    if (est.lote !== lote) return;
    if (!res.error && est.agregar.ubicar === 'coordenadas') {
        est.agregar.lat = '';
        est.agregar.lon = '';
        if ($id('ag_lat')) $id('ag_lat').value = '';
        if ($id('ag_lon')) $id('ag_lon').value = '';
    }
    if (res.error) {
        Toast.fire({ icon: 'error', title: res.error });
        if (Number(est.agregar.linea) === linea) est.agregar.siguiente = String(Math.min(Number(est.agregar.siguiente), numero));
        sincronizarAgregar();
        return;
    }
    const r = agregarFila(res.palma);
    est.agregar.pila.push(r.id);
    sincronizarAgregar();
    if (res.aviso || r.fuera) Toast.fire({ icon: 'warning', title: `Palma ${nombre(r)} fuera del polígono del lote` });
    anunciar(`Palma ${nombre(r)} agregada`);
};

const deshacer = async () => {
    const id = est.agregar.pila[est.agregar.pila.length - 1];
    const r = est.palmas.get(id);
    if (!r) return;
    const res = await api('eliminar-palma', { id });
    if (res.error) {
        avisar(res.response, res.error);
        return;
    }
    est.agregar.pila.pop();
    quitarFila(id);
    if (String(r.linea) === String(est.agregar.linea)) est.agregar.siguiente = String(r.palma);
    sincronizarAgregar();
    Toast.fire({ icon: 'success', title: `Palma ${nombre(r)} eliminada` });
};

const bannerMover = () => {
    const m = est.mover;
    if (!m) {
        banner(`<i class="bi bi-arrows-move text-success"></i><span>Toque la palma que desea mover</span>
            <span class="gp-mapa-modo-acciones"><button type="button" class="btn btn-outline-secondary btn-sm" data-accion="salir-modo">Cancelar</button></span>`);
        return;
    }
    const destino = m.destino;
    const fuera = destino && est.lote.geojson && !dentroLote(destino.lng, destino.lat);
    banner(`<i class="bi bi-arrows-move text-success"></i>
        <span>Mover palma <strong>${esc(nombre(m.r))}</strong> · ${destino ? `desplazamiento ${num(mapa.distance(m.origen, destino), 1)} m` : 'arrastre la manija'}
        ${fuera ? '<span class="d-block small text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>Quedará fuera del polígono</span>' : ''}</span>
        <span class="gp-mapa-modo-acciones">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-accion="salir-modo">Cancelar</button>
            ${destino ? '<button type="button" class="btn btn-success btn-sm" data-accion="confirmar-mover"><i class="bi bi-check2 me-1"></i>Confirmar</button>' : ''}
        </span>`);
};

const iniciarMover = (r) => {
    const origen = L.latLng(r.lat, r.lon);
    const tam = TACTIL ? 40 : 32;
    const manija = L.marker(origen, {
        icon: L.divIcon({ className: 'gp-mapa-manija', html: '<i class="bi bi-arrows-move"></i>', iconSize: [tam, tam] }),
        draggable: true,
        autoPan: true,
        zIndexOffset: 1000,
        title: `Arrastre para mover la palma ${nombre(r)}`
    }).addTo(capaExtra);
    const linea = L.polyline([origen, origen], { color: '#ffffff', weight: 2, dashArray: '4 4', interactive: false }).addTo(capaExtra);
    est.mover = { r, origen, destino: null, manija, linea };
    manija.on('drag', (e) => linea.setLatLngs([origen, e.latlng]));
    manija.on('dragend', () => {
        est.mover.destino = manija.getLatLng();
        linea.setLatLngs([origen, est.mover.destino]);
        bannerMover();
    });
    aplicarVisibilidad();
    dibujarSeleccion();
    bannerMover();
    anunciar(`Moviendo la palma ${nombre(r)}. Arrastre la manija y confirme.`);
};

const terminarMover = () => {
    if (!est.mover) return;
    capaExtra.clearLayers();
    est.mover = null;
    aplicarVisibilidad();
    dibujarSeleccion();
};

const confirmarMover = async () => {
    const m = est.mover;
    if (!m?.destino) return;
    const res = await api('mover-palma', { id: m.r.id, lat: m.destino.lat.toFixed(7), lon: m.destino.lng.toFixed(7) });
    if (res.error) {
        avisar(res.response, res.error);
        return;
    }
    const id = m.r.id;
    cambiarModo('seleccionar');
    actualizarFila(res.palma);
    seleccionar(id);
    Toast.fire({ icon: res.aviso ? 'warning' : 'success', title: res.aviso || res.message });
};

const bannerPoligono = (editando) => {
    banner(`<i class="bi bi-pentagon text-success"></i><span>${editando ? 'Editando polígono · arrastre los vértices' : 'Dibuje el polígono · clic para cada vértice, clic en el primero para cerrar'}</span>
        <span class="gp-mapa-modo-acciones">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-accion="salir-modo">Cancelar</button>
            <button type="button" class="btn btn-primary btn-sm" data-accion="guardar-poligono" ${editando ? '' : 'disabled'}><i class="bi bi-floppy me-1"></i>Guardar polígono</button>
        </span>`);
};

const iniciarPoligono = () => {
    mapa.getContainer().classList.add('gp-mapa-editando');
    if (est.lote.geojson) {
        est.edicion = L.polygon(latlngsDe(est.lote.geojson), ESTILO_EDICION).addTo(mapa);
        est.edicion.pm.enable({ allowSelfIntersection: false, snappable: false });
        dibujarLotes();
        bannerPoligono(true);
        return;
    }
    mapa.pm.enableDraw('Polygon', {
        snappable: false,
        allowSelfIntersection: false,
        templineStyle: { color: '#fde047', weight: 3 },
        hintlineStyle: { color: '#fde047', weight: 2, dashArray: '5 5' },
        pathOptions: ESTILO_EDICION
    });
    bannerPoligono(false);
};

mapa.on('pm:create', (e) => {
    if (est.modo !== 'poligono') {
        e.layer.remove();
        return;
    }
    est.edicion = e.layer;
    est.edicion.setStyle(ESTILO_EDICION);
    est.edicion.pm.enable({ allowSelfIntersection: false, snappable: false });
    bannerPoligono(true);
    anunciar('Polígono dibujado. Ajuste los vértices y guarde.');
});

const terminarPoligono = () => {
    mapa.pm.disableDraw();
    if (est.edicion) {
        est.edicion.pm.disable();
        est.edicion.remove();
        est.edicion = null;
    }
    mapa.getContainer().classList.remove('gp-mapa-editando');
    dibujarLotes();
};

const guardarPoligono = async () => {
    if (!est.edicion) return;
    const lote = est.lote;
    const res = await api('guardar-geometria', { finca: lote.finca, lote: lote.codigo, geojson: JSON.stringify(est.edicion.toGeoJSON().geometry) });
    if (res.error) {
        avisar(res.response, res.error);
        return;
    }
    Object.assign(lote, { geojson: res.geojson, areaHa: res.areaHa, centro: res.centro });
    est.fueraTrasGuardar = res.palmasFuera || 0;
    cambiarModo('seleccionar');
    est.palmas.forEach(completar);
    reestilar();
    pintarKpis();
    actualizarBotones();
    seleccionar(null);
    Toast.fire({ icon: 'success', title: res.message });
    anunciar(res.message);
};

const limpiarModo = () => {
    if (est.modo === 'mover') terminarMover();
    if (est.modo === 'poligono') terminarPoligono();
    fantasma.remove();
    sugerida.remove();
    mapa.getContainer().classList.remove('gp-mapa-cruz');
    banner(null);
};

const cambiarModo = (modo, palma = null) => {
    limpiarModo();
    const anterior = est.modo;
    est.modo = modo;
    actualizarBotones();
    if (modo === 'agregar') {
        mapa.getContainer().classList.add('gp-mapa-cruz');
        vista('agregar');
        refrescarSugerencia();
        if (est.agregar.linea) pedirSiguiente();
        else $id('ag_linea')?.focus();
        colapsar(esMovil() && !!est.agregar.linea);
        anunciar('Modo agregar palmas');
        return;
    }
    if (anterior === 'agregar') vista(est.sel ? 'palma' : 'resumen');
    if (modo === 'mover') {
        if (palma) iniciarMover(palma);
        else bannerMover();
        colapsar(esMovil());
        anunciar('Modo mover palma');
    }
    if (modo === 'poligono') {
        iniciarPoligono();
        colapsar(esMovil());
        anunciar('Modo edición de polígono');
    }
    if (modo === 'seleccionar' && anterior !== 'seleccionar') anunciar('Modo seleccionar');
};

const reiniciarLote = () => {
    if (est.modo !== 'seleccionar') cambiarModo('seleccionar');
    tareaEstilo++;
    capaPalmas.clearLayers();
    capaSel.clearLayers();
    est.palmas = new Map();
    est.sel = null;
    est.vista = 'resumen';
    est.resaltarFuera = false;
    est.fueraTrasGuardar = 0;
    est.cargado = false;
    est.agregar = { linea: '', siguiente: '', pila: [], ubicar: 'linea', lat: '', lon: '' };
    $id('buscar_palma').value = '';
    $id('buscar_palma').classList.remove('is-invalid');
    $id('mapa_error').classList.add('d-none');
};

const cargarLote = async () => {
    const codigo = $('#lote').val();
    reiniciarLote();
    est.lote = est.lotes.find((l) => l.codigo === codigo) || null;
    dibujarLotes();
    pintarKpis();
    actualizarBotones();
    pintarPanel();
    $id('mapa_lote').setAttribute('aria-label', est.lote ? `Mapa satelital del lote ${est.lote.codigo}` : 'Mapa satelital de la finca');
    if (!est.lote) {
        encuadrarFinca();
        return;
    }
    const solicitud = ++est.solicitud;
    const lote = est.lote;
    est.reintentar = cargarLote;
    $id('mapa_cargando_texto').textContent = `Cargando ${num(lote.palmasBrutas || lote.palmas)} palmas…`;
    $id('mapa_cargando').classList.remove('d-none');
    if (lote.geojson) encuadrarLote();
    const res = await api('palmas', { finca: lote.finca, lote: lote.codigo });
    if (solicitud !== est.solicitud) return;
    $id('mapa_cargando').classList.add('d-none');
    if (res.error) {
        $id('mapa_error_texto').textContent = res.error;
        $id('mapa_error').classList.remove('d-none');
        return;
    }
    est.caracteristicas = res.caracteristicas || {};
    res.palmas.forEach((f) => {
        const r = crearMarcador(completar(registro(f)));
        est.palmas.set(r.id, r);
    });
    est.cargado = true;
    pintarKpis();
    actualizarBotones();
    pintarPanel();
    encuadrarLote();
    colapsar(esMovil() && !est.sel);
    anunciar(`Lote ${lote.codigo}: ${num(est.palmas.size)} palmas cargadas`);
};

const cargarLotes = async () => {
    const finca = $id('finca').value;
    est.lotes = [];
    $('#lote').html('<option value=""></option>').val('').trigger('change.select2');
    reiniciarLote();
    est.lote = null;
    dibujarLotes();
    pintarKpis();
    actualizarBotones();
    pintarPanel();
    if (!finca) {
        encuadrarFinca();
        return;
    }
    const solicitud = ++est.solicitud;
    est.reintentar = cargarLotes;
    const res = await api('lotes', { finca });
    if (solicitud !== est.solicitud) return;
    if (res.error) {
        avisar(res.response, res.error);
        return;
    }
    est.lotes = res.lotes.map((l) => ({ ...l, finca }));
    $('#lote').html('<option value=""></option>' + est.lotes.map((l) => `<option value="${esc(l.codigo)}">${esc(l.codigo)} — ${esc(l.descripcion)}${l.activo ? '' : ' (inactivo)'}</option>`).join('')).val('').trigger('change.select2');
    dibujarLotes();
    encuadrarFinca();
};

const cargarFincas = async () => {
    est.catalogo = null;
    const res = await api('fincas');
    const select = $id('finca');
    select.innerHTML = '<option value="">Seleccione…</option>';
    if (res.error) avisar(res.response, res.error);
    (res.fincas || []).forEach((f) => select.add(new Option(`${f.codigo} — ${f.nombre}`, f.codigo)));
    if ((res.fincas || []).length === 1) select.value = res.fincas[0].codigo;
    cargarLotes();
};

const guardarEdicion = async () => {
    const r = est.palmas.get(est.sel);
    const linea = $id('ed_linea');
    const palma = $id('ed_palma');
    const estado = $id('ed_estado').value;
    const fecha = $id('ed_fecha');
    let valido = true;
    [linea, palma].forEach((c) => {
        const ok = /^\d+$/.test(c.value) && Number(c.value) > 0;
        c.classList.toggle('is-invalid', !ok);
        valido = valido && ok;
    });
    if (!/^\d+$/.test(palma.value) || Number(palma.value) < 1) $id('ed_palma_error').textContent = 'Indique un número mayor a cero.';
    const fechaOk = estado !== 'ERRADICADA' || (fecha.value !== '' && fecha.value <= hoy());
    fecha.classList.toggle('is-invalid', !fechaOk);
    if (!valido || !fechaOk) return;
    const res = await api('guardar-palma', {
        id: r.id, finca: est.lote.finca, lote: est.lote.codigo, linea: linea.value, palma: palma.value,
        lat: r.lat.toFixed(7), lon: r.lon.toFixed(7), estado, fechaErradicacion: estado === 'ERRADICADA' ? fecha.value : '', observacion: $id('ed_obs').value.trim()
    });
    if (res.error) {
        if (/ya existe/i.test(res.error) && $id('ed_palma')) {
            palma.classList.add('is-invalid');
            $id('ed_palma_error').textContent = `Ya existe la palma ${Number(linea.value)}.${Number(palma.value)} en este lote`;
            palma.focus();
            return;
        }
        avisar(res.response, res.error);
        return;
    }
    actualizarFila(res.palma);
    est.detalle = null;
    vista('palma');
    cargarHistorial(r);
    Toast.fire({ icon: res.aviso ? 'warning' : 'success', title: res.aviso || res.message });
};

const guardarCenso = async () => {
    const r = est.palmas.get(est.sel);
    const fecha = $id('cn_fecha');
    const car = $('#cn_car').val();
    const fechaOk = fecha.value !== '' && fecha.value <= hoy();
    fecha.classList.toggle('is-invalid', !fechaOk);
    $id('cn_car').closest('.input-group').classList.toggle('is-invalid', !car);
    if (!fechaOk || !car) {
        if (!car) Toast.fire({ icon: 'warning', title: 'Seleccione una característica.' });
        return;
    }
    const res = await api('registrar-censo', { palma: r.id, fecha: fecha.value, caracteristica: car, observacion: $id('cn_obs').value.trim() });
    if (res.error) {
        avisar(res.response, res.error);
        return;
    }
    const c = est.catalogo.find((x) => String(x.codigo) === String(car));
    if (c) est.caracteristicas[c.codigo] = { descripcion: c.descripcion, grupo: c.grupo };
    actualizarFila(res.palma);
    est.historial = null;
    vista('palma');
    cargarHistorial(r);
    Toast.fire({ icon: 'success', title: 'Censo registrado' });
    anunciar(`Censo registrado para la palma ${nombre(r)}`);
};

const eliminarPalma = async () => {
    const r = est.palmas.get(est.sel);
    const confirmacion = await Alerta.fire({
        icon: 'warning',
        title: `¿Eliminar la palma ${nombre(r)}?`,
        text: 'Se eliminará la palma y su ubicación. Esta acción no se puede deshacer.',
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });
    if (!confirmacion.isConfirmed) return;
    const res = await api('eliminar-palma', { id: r.id });
    if (res.error) {
        avisar(res.response, res.error);
        return;
    }
    quitarFila(r.id);
    est.agregar.pila = est.agregar.pila.filter((id) => id !== r.id);
    seleccionar(null);
    Toast.fire({ icon: 'success', title: 'Palma eliminada' });
};

const verFuera = (activar) => {
    est.resaltarFuera = activar;
    reestilar();
    pintarKpis();
    if (!activar) return;
    if (est.ocultas.size) {
        est.ocultas.clear();
        aplicarVisibilidad();
        pintarPanel();
    }
    const limites = L.latLngBounds([]);
    est.palmas.forEach((r) => r.fuera && limites.extend([r.lat, r.lon]));
    encuadrar(limites);
    anunciar('Palmas fuera del polígono resaltadas');
};

const acciones = {
    'modo': (b) => cambiarModo(b.dataset.valor),
    'salir-modo': () => cambiarModo('seleccionar'),
    'reintentar': () => est.reintentar?.(),
    'leyenda-todas': () => {
        est.ocultas.clear();
        aplicarVisibilidad();
        pintarPanel();
    },
    'leyenda-ninguna': () => {
        PM.ORDEN.forEach((k) => est.ocultas.add(k));
        aplicarVisibilidad();
        pintarPanel();
    },
    'ver-fuera': () => verFuera(!est.resaltarFuera),
    'ver-fuera-on': () => verFuera(true),
    'cerrar-ficha': () => seleccionar(null),
    'copiar': () => {
        const r = est.palmas.get(est.sel);
        const texto = `${r.lat.toFixed(6)}, ${r.lon.toFixed(6)}`;
        (navigator.clipboard ? navigator.clipboard.writeText(texto) : Promise.reject())
            .then(() => Toast.fire({ icon: 'success', title: 'Coordenadas copiadas' }))
            .catch(() => Toast.fire({ icon: 'error', title: 'No se pudieron copiar las coordenadas.' }));
    },
    'censo': async () => {
        if (!est.catalogo) {
            const res = await api('catalogo-censo');
            if (res.error) {
                avisar(res.response, res.error);
                return;
            }
            est.catalogo = res.caracteristicas;
        }
        vista('censo');
        $id('cn_fecha').focus();
    },
    'editar': () => {
        if (!est.detalle) return;
        vista('editar');
        $id('ed_linea').focus();
    },
    'volver-ficha': () => vista(est.sel ? 'palma' : 'resumen'),
    'mover-sel': () => cambiarModo('mover', est.palmas.get(est.sel)),
    'confirmar-mover': confirmarMover,
    'eliminar': eliminarPalma,
    'deshacer': deshacer,
    'agregar-palma': agregarPalma,
    'ag-ubicar': (b) => {
        if (est.agregar.ubicar === b.dataset.valor) return;
        est.agregar.ubicar = b.dataset.valor;
        vista('agregar');
        refrescarSugerencia();
        (b.dataset.valor === 'coordenadas' ? $id('ag_lat') : $id('ag_linea'))?.focus();
    },
    'guardar-poligono': guardarPoligono,
    'ver-todo': () => {
        est.verTodo = true;
        pintarHistorial();
    }
};

document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-accion]');
    if (b && !b.disabled && acciones[b.dataset.accion]) acciones[b.dataset.accion](b);
});

document.querySelectorAll('[data-modo]').forEach((b) => b.addEventListener('click', () => {
    if (b.dataset.modo === est.modo && est.modo !== 'seleccionar') cambiarModo('seleccionar');
    else cambiarModo(b.dataset.modo);
}));

$id('panel_contenido').addEventListener('change', (e) => {
    const t = e.target;
    if (t.dataset.clave) {
        t.checked ? est.ocultas.delete(t.dataset.clave) : est.ocultas.add(t.dataset.clave);
        aplicarVisibilidad();
    }
    if (t.id === 'ed_estado') {
        const col = $id('col_ed_fecha');
        col.classList.toggle('d-none', t.value !== 'ERRADICADA');
        col.classList.add('gp-revelar');
        if (t.value === 'ERRADICADA' && !$id('ed_fecha').value) $id('ed_fecha').value = hoy();
    }
    if (t.id === 'ag_linea') {
        est.agregar.linea = /^\d+$/.test(t.value) && Number(t.value) > 0 ? String(Number(t.value)) : '';
        t.classList.toggle('is-invalid', t.value !== '' && !est.agregar.linea);
        est.agregar.siguiente = '';
        sincronizarAgregar();
        pedirSiguiente();
    }
});

$id('panel_contenido').addEventListener('input', (e) => {
    if (e.target.id === 'ag_numero') {
        est.agregar.siguiente = e.target.value;
        refrescarSugerencia();
    }
    if (e.target.id === 'ag_lat' || e.target.id === 'ag_lon') {
        est.agregar[e.target.id === 'ag_lat' ? 'lat' : 'lon'] = e.target.value.trim();
        e.target.classList.remove('is-invalid');
        refrescarSugerencia();
    }
    if (e.target.id === 'ed_palma' || e.target.id === 'ed_linea') e.target.classList.remove('is-invalid');
});

$id('panel_contenido').addEventListener('submit', (e) => {
    e.preventDefault();
    if (e.target.id === 'form_editar') guardarEdicion();
    if (e.target.id === 'form_censo') guardarCenso();
});

$id('asa_panel').addEventListener('click', () => colapsar(!$id('panel_mapa').classList.contains('colapsado')));

$id('ver_erradicadas').addEventListener('change', (e) => {
    e.target.checked ? est.ocultas.delete('erradicada') : est.ocultas.add('erradicada');
    aplicarVisibilidad();
    if (est.lote && !est.sel && est.vista === 'resumen') pintarPanel();
});

$id('btn_encuadrar').addEventListener('click', encuadrarLote);

$id('form_buscar').addEventListener('submit', (e) => {
    e.preventDefault();
    const input = $id('buscar_palma');
    const m = input.value.trim().match(/^(\d+)\s*[.,\-\s]\s*(\d+)$/);
    const r = m ? [...est.palmas.values()].find((p) => !p.pendiente && p.linea === Number(m[1]) && p.palma === Number(m[2])) : null;
    if (!r) {
        const texto = m ? `No existe la palma ${Number(m[1])}.${Number(m[2])} en este lote` : 'Use el formato línea.palma (ej. 12.34)';
        $id('buscar_error').textContent = texto;
        input.classList.add('is-invalid');
        anunciar(texto);
        return;
    }
    input.classList.remove('is-invalid');
    if (est.modo !== 'seleccionar') cambiarModo('seleccionar');
    if (est.ocultas.has(r.clave)) {
        est.ocultas.delete(r.clave);
        aplicarVisibilidad();
    }
    volar([r.lat, r.lon], Math.max(mapa.getZoom(), 19));
    seleccionar(r.id);
    anunciar(`Palma ${nombre(r)} encontrada y seleccionada`);
});

$id('buscar_palma').addEventListener('input', (e) => e.target.classList.remove('is-invalid'));

$id('empresa').addEventListener('change', cargarFincas);
$id('finca').addEventListener('change', cargarLotes);

$('#lote').select2({
    width: '100%',
    placeholder: 'Seleccione un lote…',
    allowClear: true,
    language: { noResults: () => 'No se encontraron resultados', searching: () => 'Buscando…' }
}).on('change', cargarLote);

mapa.on('click', (e) => {
    if (est.modo !== 'agregar') return;
    if (est.agregar.ubicar === 'coordenadas') {
        est.agregar.lat = e.latlng.lat.toFixed(7);
        est.agregar.lon = e.latlng.lng.toFixed(7);
        if ($id('ag_lat')) $id('ag_lat').value = est.agregar.lat;
        if ($id('ag_lon')) $id('ag_lon').value = est.agregar.lon;
        refrescarSugerencia();
        return;
    }
    colocar(e.latlng);
});

mapa.on('mousemove', (e) => {
    if (est.modo !== 'agregar' || !FINO || !est.agregar.linea) return;
    fantasma.setLatLng(e.latlng).setRadius(Math.max(3, PM.radioPalma(mapa.getZoom()).radio));
    if (!mapa.hasLayer(fantasma)) fantasma.addTo(mapa);
});

mapa.on('mouseout', () => fantasma.remove());

mapa.on('zoomend', () => {
    mapa.getContainer().classList.toggle('gp-mapa-sin-etiquetas', mapa.getZoom() < 14);
    reestilar();
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || document.querySelector('.swal2-popup:not(.swal2-toast), .select2-container--open')) return;
    mapa.closePopup();
    if (est.modo !== 'seleccionar') cambiarModo('seleccionar');
});

mapa.getContainer().classList.toggle('gp-mapa-sin-etiquetas', mapa.getZoom() < 14);
pintarPanel();
actualizarBotones();
if ($id('finca').value) cargarLotes();
