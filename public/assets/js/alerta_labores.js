(() => {
    const modal = document.getElementById('modalProximasLabores');
    if (!modal) return;

    const pad = (n) => String(n).padStart(2, '0');
    const ahora = new Date();
    const PREFIJO = `lpl_ocultar_${window.LPL_USU_ID}_`;
    const CLAVE = PREFIJO + `${ahora.getFullYear()}-${pad(ahora.getMonth() + 1)}-${pad(ahora.getDate())}`;
    const MAX = 5;
    const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const INFORMES = [
        ['ciclos-cosecha', 'Cosecha', 'bi-basket', ['sobremaduracion', 'alerta']],
        ['ciclos-poda', 'Poda', 'bi-scissors', ['vencido', 'proximo']],
        ['ciclos-fertilizacion', 'Fertilización', 'bi-droplet', ['vencido', 'proximo']],
        ['ciclos-sanidad', 'Sanidad', 'bi-shield-plus', ['vencido', 'proximo']],
        ['ciclos-mantenimiento', 'Mantenimiento', 'bi-tools', ['vencido', 'proximo']]
    ];
    const ESTADOS = {
        alerta: ['ig-e-alerta', 'Listo para cosechar', 'listo', 'listos'],
        sobremaduracion: ['ig-e-sobremaduracion', 'Sobremaduración', 'sobremaduración', 'sobremaduración'],
        vencido: ['ig-e-vencido', 'Vencido', 'vencido', 'vencidos'],
        proximo: ['ig-e-proximo', 'Próximo', 'próximo', 'próximos']
    };

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const aFecha = (s) => { const [y, m, d] = String(s).split('-').map(Number); return new Date(y, m - 1, d); };
    const ddmm = (d) => `${pad(d.getDate())}/${pad(d.getMonth() + 1)}`;
    const dias = (n) => `${n} ${n === 1 ? 'día' : 'días'}`;
    const chip = (cls, txt) => `<span class="ig-chip ${cls}">${esc(txt)}</span>`;
    const txt = (cls, t) => `<span class="${cls} text-truncate" title="${esc(t)}">${esc(t)}</span>`;

    const grupo = (titulo, icono, chips, filas) => {
        const extra = filas.length - MAX;
        return `<section class="lpl-grupo">
            <div class="lpl-seccion"><span class="lpl-seccion-tit">${icono ? `<i class="bi ${icono}" aria-hidden="true"></i>` : ''}${esc(titulo)}</span><span class="lpl-chips">${chips}</span></div>
            ${filas.slice(0, MAX).join('')}
            ${extra > 0 ? `<div class="lpl-extra" hidden>${filas.slice(MAX).join('')}</div>
            <button type="button" class="btn btn-link btn-sm p-0 lpl-mas" aria-expanded="false" data-extra="${extra}"><span>Ver ${extra} más</span> <i class="bi bi-chevron-down" aria-hidden="true"></i></button>` : ''}
        </section>`;
    };

    const pintar = (d) => {
        const planeadas = d.planeadas || [];
        const ciclos = d.ciclos || [];
        const total = Math.max(Number(d.planeadasTotal) || 0, planeadas.length);
        const multi = new Set([...planeadas, ...ciclos].map((x) => x.empresa)).size > 1;
        const hoy = aFecha(d.fecha);
        const fin = new Date(hoy);
        fin.setDate(fin.getDate() + (Number(d.dias) || 7));
        const criticos = ciclos.filter((c) => c.estado === 'vencido' || c.estado === 'sobremaduracion').length;

        document.getElementById('lplSubtitulo').textContent = `Del ${ddmm(hoy)} al ${ddmm(fin)} · corte de ciclos a hoy`;

        const kpi = (cls, n, lbl) => `<div class="lpl-kpi ${cls}"><span class="lpl-kpi-num">${n}</span><span class="lpl-kpi-lbl">${lbl}</span></div>`;
        document.getElementById('lplKpis').innerHTML =
            kpi('ig-e-seguimiento', total, `Planeadas (${Number(d.dias) || 7} días)`) +
            kpi('ig-e-proximo', ciclos.length, 'Lotes por ciclo') +
            kpi(criticos ? 'ig-e-vencido' : 'ig-e-al-dia', criticos, 'Vencidos / Sobremaduración');

        document.getElementById('lplAvisos').innerHTML = (d.avisos || []).length
            ? `<div class="alert alert-warning py-2 small mb-3 d-flex gap-2"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><div>${d.avisos.map(esc).join('<br>')}</div></div>`
            : '';

        const vacio = !planeadas.length && !ciclos.length;
        ['lplKpis', 'lplTabs', 'lplPanes'].forEach((id) => (document.getElementById(id).hidden = vacio));
        document.getElementById('lplVacio').hidden = !vacio;
        document.getElementById('lplVacioTexto').textContent = `No hay labores próximas en los próximos ${Number(d.dias) || 7} días`;

        const porFecha = {};
        planeadas.forEach((p) => (porFecha[p.fecha] = porFecha[p.fecha] || []).push(p));
        const htmlPlaneadas = (total > planeadas.length ? `<p class="lpl-nota">Mostrando ${planeadas.length} de ${total}</p>` : '') +
            Object.keys(porFecha).sort().map((f) => {
                const fecha = aFecha(f);
                const dif = Math.round((fecha - hoy) / 86400000);
                const titulo = dif === 0 ? 'Hoy' : dif === 1 ? 'Mañana' : `${DIAS[fecha.getDay()]} ${ddmm(fecha)}`;
                const filas = porFecha[f].map((p) => {
                    const hora = p.horaInicio ? p.horaInicio + (p.horaFin ? '–' + p.horaFin : '') : '';
                    const ubic = [multi ? p.empresaNombre || p.empresa : '', p.fincaNombre || p.finca, p.loteNombre || p.lote].filter(Boolean).join(' · ');
                    const trab = p.trabajadores || [];
                    const nombres = trab.join(', ');
                    const meta = (Number(p.cantidad) > 0 ? chip('ig-e-sin-dato', `${Number(p.cantidad).toLocaleString('es-CO', { maximumFractionDigits: 2 })} ${p.uMedida || ''}`.trim()) : '') +
                        (trab.length ? `<span class="lpl-trab text-truncate" title="${esc(nombres)}"><i class="bi bi-people" aria-hidden="true"></i> ${esc(trab.length <= 2 ? nombres : `${trab.length} trabajadores`)}</span>` : '');
                    return `<div class="lpl-fila">
                        <div class="lpl-hora${hora ? '' : ' text-muted'}">${hora ? esc(hora) : '—'}</div>
                        <div class="lpl-det">
                            ${txt('lpl-nombre', p.labor)}
                            <span class="gp-subtexto text-truncate" title="${esc(ubic)}">${hora ? `<span class="lpl-hora-m">${esc(hora)} · </span>` : ''}${esc(ubic)}</span>
                            ${meta ? `<div class="lpl-meta">${meta}</div>` : ''}
                        </div>
                    </div>`;
                });
                return grupo(titulo, '', chip(dif === 0 ? 'ig-e-alerta' : 'ig-e-seguimiento', `${filas.length} ${filas.length === 1 ? 'labor' : 'labores'}`), filas);
            }).join('');

        const htmlCiclos = INFORMES.map(([inf, nombre, icono, orden]) => {
            const lista = ciclos.filter((c) => c.informe === inf);
            if (!lista.length) return '';
            const chips = orden.map((e) => {
                const n = lista.filter((c) => c.estado === e).length;
                return n ? chip(ESTADOS[e][0], `${n} ${n === 1 ? ESTADOS[e][2] : ESTADOS[e][3]}`) : '';
            }).join('');
            const filas = lista.map((c) => {
                const [cls, etiqueta] = ESTADOS[c.estado] || ['ig-e-sin-dato', c.estadoNombre || c.estado];
                const lote = [multi ? c.empresaNombre || c.empresa : '', c.fincaNombre || c.finca, c.loteDescripcion || c.lote].filter(Boolean).join(' · ');
                const n = Number(c.diasParaIngreso);
                const sub = [
                    inf === 'ciclos-cosecha' ? `${dias(Number(c.diasTranscurridos))} desde la última cosecha` : n < 0 ? `Vencido hace ${dias(-n)}` : n === 0 ? 'Hoy' : `En ${dias(n)}`,
                    c.proximo ? `Próx. ingreso ${ddmm(aFecha(c.proximo))}` : '',
                    c.labor || ''
                ].filter(Boolean).join(' · ');
                return `<div class="lpl-fila lpl-fila-ciclo">
                    <div class="lpl-det">
                        <div class="lpl-linea">${txt('lpl-nombre', lote)}${chip(cls, etiqueta)}</div>
                        ${txt('gp-subtexto', sub)}
                    </div>
                </div>`;
            });
            return grupo(nombre, icono, chips, filas);
        }).join('');

        document.getElementById('lplPanePlaneadas').innerHTML = htmlPlaneadas;
        document.getElementById('lplPaneCiclos').innerHTML = htmlCiclos;

        const tabs = [['lplTabPlaneadas', 'lplPanePlaneadas', planeadas.length, total], ['lplTabCiclos', 'lplPaneCiclos', ciclos.length, ciclos.length]];
        const activa = planeadas.length ? 0 : 1;
        tabs.forEach(([tab, pane, filas, cuenta], i) => {
            const btn = document.getElementById(tab);
            btn.querySelector('.badge').textContent = cuenta;
            btn.disabled = !filas;
            btn.classList.toggle('active', i === activa);
            btn.setAttribute('aria-selected', i === activa);
            document.getElementById(pane).classList.toggle('show', i === activa);
            document.getElementById(pane).classList.toggle('active', i === activa);
        });
    };

    modal.addEventListener('click', (e) => {
        const btn = e.target.closest('.lpl-mas');
        if (!btn) return;
        const extra = btn.previousElementSibling;
        const abrir = extra.hidden;
        extra.hidden = !abrir;
        btn.setAttribute('aria-expanded', abrir);
        btn.querySelector('span').textContent = abrir ? 'Ver menos' : `Ver ${btn.dataset.extra} más`;
        btn.querySelector('i').className = `bi bi-chevron-${abrir ? 'up' : 'down'}`;
    });

    modal.addEventListener('shown.bs.modal', () => document.getElementById('lplEntendido').focus());

    let modoActual = 'auto';
    const ocultarHoy = document.getElementById('lplOcultarHoy');

    const guardar = () => {
        if (modoActual === 'manual' || !ocultarHoy.checked) return;
        try {
            Object.keys(localStorage).filter((k) => k.startsWith(PREFIJO) && k !== CLAVE).forEach((k) => localStorage.removeItem(k));
            localStorage.setItem(CLAVE, '1');
        } catch (e) {
            console.warn('Próximas labores: no se pudo guardar la preferencia', e);
        }
    };
    const irPlaneacion = document.getElementById('lplIrPlaneacion');

    modal.addEventListener('hidden.bs.modal', guardar);
    irPlaneacion.addEventListener('click', guardar);

    const loader = document.getElementById('lplLoader');
    const pagina = document.querySelector('.losetas-page') || document.getElementById('main') || document.body;
    const ocupado = (si) => {
        loader.hidden = !si;
        pagina.setAttribute('aria-busy', si);
    };
    const ocupadoBtn = (btn, si) => {
        btn.disabled = si;
        btn.setAttribute('aria-busy', si);
        btn.innerHTML = si ? '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>' : '<i class="bi bi-calendar-check" aria-hidden="true"></i>';
    };
    let toast;

    const abrir = async (modo = 'auto', btn = null) => {
        const manual = modo === 'manual';
        if (!manual) {
            try {
                if (localStorage.getItem(CLAVE)) return;
            } catch (e) {}
        }
        const espera = (si) => (btn ? ocupadoBtn(btn, si) : !manual && ocupado(si));
        espera(true);
        const control = new AbortController();
        const limite = setTimeout(() => control.abort(), 20000);
        try {
            const resp = await fetch(window.LPL_URL, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: control.signal });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const datos = await resp.json();
            if (!datos.success) throw new Error(datos.message || 'Respuesta sin éxito');
            if (!manual && !(datos.planeadas || []).length && !(datos.ciclos || []).length) return;
            pintar(datos);
            const url = typeof datos.planeacionUrl === 'string' ? datos.planeacionUrl.trim() : '';
            irPlaneacion.hidden = !url;
            irPlaneacion.href = url || '#';
            modoActual = modo;
            document.getElementById('lplOcultarWrap').hidden = manual;
            ocultarHoy.checked = false;
            espera(false);
            bootstrap.Modal.getOrCreateInstance(modal).show();
        } catch (e) {
            console.warn('Próximas labores:', e);
            if (manual) {
                toast = toast || Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (t) => {
                        t.onmouseenter = Swal.stopTimer;
                        t.onmouseleave = Swal.resumeTimer;
                    }
                });
                toast.fire({ icon: 'error', title: 'No se pudieron cargar las próximas labores. Intente de nuevo.' });
            }
        } finally {
            clearTimeout(limite);
            espera(false);
        }
    };

    window.abrirProximasLabores = abrir;

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.lpl-trigger');
        if (!btn) return;
        bootstrap.Tooltip.getInstance(btn)?.hide();
        abrir('manual', btn);
    });

    if (!window.LPL_AUTO) return;
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => abrir('auto'));
    } else {
        abrir('auto');
    }
})();
