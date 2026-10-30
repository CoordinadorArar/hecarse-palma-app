(() => {
    const C = window.CS_CONFIG;
    const form = document.getElementById('cs_form');
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    const HEX = /^#[0-9a-f]{6}$/i;
    const LIMITE = 2 * 1024 * 1024;
    const CLAVES = ['logo', 'logo_claro', 'favicon'];
    const FORMATO_LOGO = { ext: ['png', 'webp'], mime: ['image/png', 'image/webp'], msg: 'El archivo debe ser PNG o WEBP' };
    const FORMATOS = {
        logo: FORMATO_LOGO,
        logo_claro: FORMATO_LOGO,
        favicon: { ext: ['png', 'ico'], mime: ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'], msg: 'El archivo debe ser PNG o ICO' }
    };

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

    const nombre = $('#cs_nombre');
    const colores = Object.fromEntries(['primary', 'secundario'].map(k => {
        const fila = $(`[data-cs-color="${k}"]`);
        return [k, { fila, picker: $('.cs-picker', fila), hex: $('.cs-hex', fila), chip: $('[data-cs-contraste]', fila), aviso: $('[data-cs-aviso]', fila), error: $('[data-cs-error]', fila) }];
    }));
    const imagenes = Object.fromEntries(CLAVES.map(k => [k, { archivo: null, url: null, restablecer: false, tarjeta: $(`[data-cs-logo="${k}"]`), input: $(`#cs_archivo_${k}`) }]));
    const vivo = { primary: C.primary, secundario: C.secundario };
    const errores = {};
    let guardando = false;

    const rgb = h => [1, 3, 5].map(i => parseInt(h.substr(i, 2), 16));
    const aHex = c => '#' + c.map(v => Math.round(v).toString(16).padStart(2, '0')).join('');
    const mezcla = (h, t, p) => aHex(rgb(h).map(v => v + (t - v) * p));
    const luz = h => {
        const [r, g, b] = rgb(h).map(v => {
            v /= 255;
            return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const contraste = h => 1.05 / (luz(h) + 0.05);
    const tonos = h => ({ base: h, hover: mezcla(h, 0, 0.15), active: mezcla(h, 0, 0.25), emphasis: mezcla(h, 0, 0.45), border: mezcla(h, 255, 0.7), subtle: mezcla(h, 255, 0.9) });
    const peso = b => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
    const valorHex = k => colores[k].hex.value.trim().toLowerCase();

    const mostrarError = (campo, msg) => {
        const nodo = $(`[data-cs-error="${campo}"]`);
        const objetivo = campo === 'nombre_app' ? nombre : (imagenes[campo]?.tarjeta || colores[campo.replace('color_', '')]?.hex);
        if (!nodo || !objetivo) return;
        nodo.textContent = msg || '';
        nodo.classList.toggle('d-block', !!msg);
        objetivo.classList.toggle('is-invalid', !!msg);
    };

    const urlImagen = k => imagenes[k].url || (imagenes[k].restablecer ? C.defectos.img[k] : C.img[k]);

    const aplicarTema = () => {
        const p = vivo.primary;
        const t = tonos(p);
        const r = rgb(p).join(',');
        const vars = {
            '--app-primary': p, '--app-primary-rgb': r, '--app-primary-hover': t.hover, '--app-primary-active': t.active,
            '--app-primary-subtle': t.subtle, '--app-primary-border': t.border, '--app-primary-emphasis': t.emphasis,
            '--app-secundario': vivo.secundario, '--app-secundario-rgb': rgb(vivo.secundario).join(','),
            '--bs-primary': p, '--bs-primary-rgb': r, '--bs-primary-text-emphasis': t.emphasis, '--bs-primary-bg-subtle': t.subtle,
            '--bs-primary-border-subtle': t.border, '--bs-link-color': p, '--bs-link-color-rgb': r,
            '--bs-link-hover-color': t.hover, '--bs-link-hover-color-rgb': rgb(t.hover).join(',')
        };
        $$('[data-cs-tema]').forEach(el => Object.entries(vars).forEach(([v, x]) => el.style.setProperty(v, x)));
        $$('#cs_tira [data-cs-tono]').forEach(m => {
            const h = t[m.dataset.csTono].toUpperCase();
            m.style.backgroundColor = h;
            m.title = `${m.dataset.csNombre} · ${h}`;
            m.setAttribute('aria-label', m.title);
        });
    };

    const pintarContraste = k => {
        const { chip, aviso } = colores[k];
        const ratio = contraste(vivo[k]);
        const ok = ratio >= 4.5;
        chip.className = 'cs-chip ' + (ok ? 'gp-badge-abierto' : 'gp-chip-fila ambar');
        chip.textContent = `${ratio.toFixed(1)}:1 · ${ok ? 'AA' : 'Bajo'}`;
        aviso.classList.toggle('d-none', ok);
    };

    const contarCambios = () => {
        let n = 0;
        if (nombre.value.trim() !== C.nombre) n++;
        if (valorHex('primary') !== C.primary) n++;
        if (valorHex('secundario') !== C.secundario) n++;
        CLAVES.forEach(k => { if (imagenes[k].archivo || imagenes[k].restablecer) n++; });
        return n;
    };

    const render = () => {
        mostrarError('nombre_app', errores.nombre_app || (nombre.value.trim() === '' ? 'Ingrese el nombre del sitio' : ''));
        Object.keys(colores).forEach(k => {
            const h = valorHex(k);
            const valido = HEX.test(h);
            if (valido) vivo[k] = h;
            mostrarError('color_' + k, valido ? (errores['color_' + k] || '') : 'Use el formato #RRGGBB');
            pintarContraste(k);
        });
        aplicarTema();
        CLAVES.forEach(k => {
            const img = imagenes[k];
            $$(`[data-cs-src="${k}"]`).forEach(el => { el.src = urlImagen(k); });
            const estado = $('[data-cs-estado]', img.tarjeta);
            estado.classList.toggle('d-none', !img.archivo && !img.restablecer);
            $('[data-cs-archivo]', estado).textContent = img.archivo ? `${img.archivo.name} · ${peso(img.archivo.size)}` : 'Imagen por defecto';
            mostrarError(k, errores[k] || '');
        });
        $('#cs_pv_nombre').textContent = nombre.value.trim() || C.nombre;
        const n = contarCambios();
        const pendientes = $('#cs_pendientes');
        pendientes.classList.toggle('d-none', n === 0);
        $('span', pendientes).textContent = n === 1 ? '1 cambio sin guardar' : `${n} cambios sin guardar`;
        $('#cs_descartar').disabled = n === 0 || guardando;
        $('#cs_guardar').disabled = n === 0 || guardando || $$('input.is-invalid', form).length > 0;
    };

    const limpiarImagen = k => {
        const img = imagenes[k];
        if (img.url) URL.revokeObjectURL(img.url);
        img.archivo = null;
        img.url = null;
        img.restablecer = false;
        img.input.value = '';
        delete errores[k];
        const aviso = $('[data-cs-cuadrado]', img.tarjeta);
        if (aviso) aviso.classList.add('d-none');
    };

    const fijarColor = (k, h) => {
        colores[k].hex.value = h.toUpperCase();
        colores[k].picker.value = h.toLowerCase();
        delete errores['color_' + k];
    };

    const elegirArchivo = k => {
        const img = imagenes[k];
        const archivo = img.input.files[0];
        if (!archivo) return;
        const f = FORMATOS[k];
        const ext = archivo.name.split('.').pop().toLowerCase();
        const mimeOk = f.mime.includes(archivo.type) || (ext === 'ico' && archivo.type === '');
        let motivo = '';
        if (!f.ext.includes(ext) || !mimeOk) motivo = f.msg;
        else if (archivo.size > LIMITE) motivo = 'El archivo supera el máximo de 2 MB';
        limpiarImagen(k);
        if (motivo) {
            errores[k] = motivo;
            render();
            return;
        }
        img.archivo = archivo;
        img.url = URL.createObjectURL(archivo);
        if (k === 'favicon') {
            const prueba = new Image();
            prueba.onload = () => $('[data-cs-cuadrado]', img.tarjeta).classList.toggle('d-none', prueba.naturalWidth === prueba.naturalHeight);
            prueba.src = img.url;
        }
        render();
    };

    CLAVES.forEach(k => {
        const img = imagenes[k];
        $(`[data-cs-elegir="${k}"]`).addEventListener('click', () => img.input.click());
        img.input.addEventListener('change', () => elegirArchivo(k));
        $(`[data-cs-deshacer="${k}"]`).addEventListener('click', () => {
            limpiarImagen(k);
            render();
        });
    });

    nombre.addEventListener('input', () => {
        delete errores.nombre_app;
        render();
    });

    Object.entries(colores).forEach(([k, c]) => {
        c.picker.addEventListener('input', () => {
            fijarColor(k, c.picker.value);
            render();
        });
        c.hex.addEventListener('input', () => {
            const h = c.hex.value.trim();
            if (HEX.test(h)) c.picker.value = h.toLowerCase();
            delete errores['color_' + k];
            render();
        });
        c.hex.addEventListener('blur', () => {
            const h = c.hex.value.trim();
            if (/^[0-9a-f]{6}$/i.test(h)) fijarColor(k, '#' + h);
            render();
        });
    });

    $('#cs_colores_defecto').addEventListener('click', () => {
        fijarColor('primary', C.defectos.primary);
        fijarColor('secundario', C.defectos.secundario);
        render();
    });

    $('#cs_descartar').addEventListener('click', () => {
        nombre.value = C.nombre;
        delete errores.nombre_app;
        fijarColor('primary', C.primary);
        fijarColor('secundario', C.secundario);
        CLAVES.forEach(limpiarImagen);
        render();
    });

    $('#cs_defectos').addEventListener('click', async () => {
        const r = await Swal.fire({
            title: '¿Restablecer valores por defecto?',
            text: 'El nombre, los logos, el favicon y los colores volverán a los valores de ELAEIX. Los cambios no se aplican hasta que pulse «Guardar cambios».',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, restablecer',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--app-primary').trim(),
            reverseButtons: true
        });
        if (!r.isConfirmed) return;
        nombre.value = C.defectos.nombre;
        delete errores.nombre_app;
        fijarColor('primary', C.defectos.primary);
        fijarColor('secundario', C.defectos.secundario);
        CLAVES.forEach(k => {
            limpiarImagen(k);
            imagenes[k].restablecer = !!C.defectos.propia[k];
        });
        render();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        render();
        const btn = $('#cs_guardar');
        if (btn.disabled) return;
        const datos = new FormData();
        datos.append('nombre_app', nombre.value.trim());
        datos.append('color_primary', valorHex('primary'));
        datos.append('color_secundario', valorHex('secundario'));
        CLAVES.forEach(k => {
            if (imagenes[k].archivo) datos.append(k, imagenes[k].archivo);
            else if (imagenes[k].restablecer) datos.append('restablecer_' + k, '1');
        });
        guardando = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardando…';
        render();
        let exito = false;
        try {
            const resp = await fetch(C.url, { method: 'POST', body: datos, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json().catch(() => ({}));
            if (resp.ok && res.success) {
                exito = true;
                Toast.fire({ icon: 'success', title: 'Configuración guardada' });
                setTimeout(() => window.location.reload(), 800);
                return;
            }
            Object.assign(errores, res.errores || {});
            Toast.fire({ icon: 'error', title: res.message || 'No se pudo guardar la configuración' });
        } catch (error) {
            Toast.fire({ icon: 'error', title: 'No se pudo guardar la configuración' });
        } finally {
            if (!exito) {
                guardando = false;
                btn.innerHTML = original;
                render();
            }
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (guardando || contarCambios() === 0) return;
        e.preventDefault();
        e.returnValue = '';
    });

    render();
})();
