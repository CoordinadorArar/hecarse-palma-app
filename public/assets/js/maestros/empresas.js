
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

const filasEmpresas = () => document.querySelectorAll('#cuerpo_tabla_empresas tr[data-id]');

const destruirTabla = () => {
    if ($.fn.DataTable.isDataTable('#tabla_empresas')) {
        $('#tabla_empresas').DataTable().destroy();
    }
};

const actualizarResumen = () => {
    const filas = filasEmpresas();

    document.getElementById('contador_empresas').innerHTML =
        `<i class="bi bi-building me-1"></i><strong>${filas.length}</strong> ${filas.length === 1 ? 'registro encontrado' : 'registros encontrados'}`;

    const sinRepresentantes = filas.length > 0
        && [...filas].every((fila) => fila.dataset.tercero === '0');

    document.getElementById('aviso_sin_representantes').classList.toggle('d-none', !sinRepresentantes);
};

const renderizarTabla = () => {
    destruirTabla();
    actualizarResumen();

    if (filasEmpresas().length === 0) {
        return;
    }

    $('#tabla_empresas').DataTable({
        order: [[0, 'asc']],
        columnDefs: [{ targets: [8], orderable: false }],
        language: {
            lengthMenu: 'Mostrar _MENU_ registros por página',
            zeroRecords: 'No se encontraron resultados',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'No hay registros disponibles',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            search: 'Buscar:',
            emptyTable: 'No hay datos disponibles',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        }
    });
};

const aplicarHtmlTabla = (html) => {
    destruirTabla();
    document.getElementById('cuerpo_tabla_empresas').innerHTML = html;
    renderizarTabla();
};

const escaparHtml = (texto) => String(texto ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
}[caracter]));

// ── Cálculo del dígito de verificación (algoritmo DIAN, módulo 11) ────────────

const calcularDV = (nit) => {
    const pesos = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];
    const digitos = String(nit).replace(/\D/g, '').padStart(15, '0').split('');

    const suma = digitos.reduce((acc, digito, i) => acc + (parseInt(digito, 10) * pesos[i]), 0);
    const residuo = suma % 11;

    return (residuo === 0 || residuo === 1) ? residuo : 11 - residuo;
};

const pintarDV = (nit) => {
    const dv = document.getElementById('dv_crear');
    const digitos = String(nit).replace(/\D/g, '');

    dv.value = digitos.length >= 5 ? calcularDV(digitos) : '';
    dv.classList.toggle('dv-vacio', dv.value === '');
};

let temporizadorDV;
document.getElementById('nit_crear').addEventListener('input', (e) => {
    clearTimeout(temporizadorDV);
    const valor = e.target.value;
    temporizadorDV = setTimeout(() => pintarDV(valor), 250);
});

document.getElementById('editar_nit').addEventListener('blur', (e) => {
    if (e.target.value.trim() !== '') {
        document.getElementById('editar_dv').value = calcularDV(e.target.value);
    }
});

// ── Buscador de terceros (typeahead) ───────────────────────────────────────────

const configurarBuscadorTercero = (inputId, hiddenId, resultadosId) => {
    const input = document.getElementById(inputId);
    const hidden = document.getElementById(hiddenId);
    const resultados = document.getElementById(resultadosId);
    let timeoutId;

    input.addEventListener('input', () => {
        hidden.value = '';
        clearTimeout(timeoutId);

        const termino = input.value.trim();
        if (termino.length < 2) {
            resultados.innerHTML = '';
            return;
        }

        timeoutId = setTimeout(async () => {
            try {
                const body = new FormData();
                body.append('termino', termino);

                const response = await fetch(BASE_URL + 'maestros/empresas/buscarTercero', {
                    method: 'POST',
                    body
                });

                if (!response.ok) {
                    resultados.innerHTML = '';
                    return;
                }

                const result = await response.json();

                resultados.innerHTML = '';
                (result.terceros || []).forEach((tercero) => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = `${tercero.Nombre} (${tercero.nit})`;
                    item.addEventListener('click', () => {
                        hidden.value = tercero.id;
                        input.value = tercero.Nombre;
                        resultados.innerHTML = '';
                    });
                    resultados.appendChild(item);
                });
            } catch (error) {
                resultados.innerHTML = '';
            }
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!resultados.contains(e.target) && e.target !== input) {
            resultados.innerHTML = '';
        }
    });
};

configurarBuscadorTercero('tercero_buscar_editar', 'tercero_id_editar', 'tercero_resultados_editar');

// ── Crear ─────────────────────────────────────────────────────────────────────

const limpiarInvalidos = () => {
    document.querySelectorAll('#formulario_creacion .is-invalid')
        .forEach((campo) => campo.classList.remove('is-invalid'));
};

const marcarInvalido = (id, mensaje) => {
    const campo = document.getElementById(id);
    const feedback = campo.parentElement.querySelector('.invalid-feedback');

    campo.classList.add('is-invalid');
    if (feedback) feedback.textContent = mensaje;

    Toast.fire({ icon: 'warning', title: mensaje });
    campo.focus();
    return false;
};

const validarCreacion = () => {
    limpiarInvalidos();

    const nit = document.getElementById('nit_crear').value.replace(/\D/g, '');
    const razonSocial = document.getElementById('razonSocial_crear').value.trim();
    const cc = document.getElementById('cc_crear').value.trim();
    const nombre = document.getElementById('nombreRepresentante_crear').value.trim();
    const email = document.getElementById('email_crear').value.trim();

    if (nit.length < 5) return marcarInvalido('nit_crear', 'Escriba un Nit válido (mínimo 5 dígitos).');
    if (razonSocial === '') return marcarInvalido('razonSocial_crear', 'Escriba la razón social de la empresa.');
    if (cc === '') return marcarInvalido('cc_crear', 'Escriba la cédula del representante.');
    if (!/^\d+$/.test(cc)) return marcarInvalido('cc_crear', 'La cédula solo admite números.');
    if (nombre === '') return marcarInvalido('nombreRepresentante_crear', 'Escriba el nombre del representante.');
    if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return marcarInvalido('email_crear', 'El correo electrónico no tiene un formato válido.');
    }

    return true;
};

const refrescarCodigo = async () => {
    try {
        const response = await fetch(BASE_URL + 'maestros/empresas/siguienteCodigo', { method: 'POST' });

        if (!response.ok) {
            const result = await response.json();
            document.getElementById('codigo_crear').value = '';
            Toast.fire({ icon: 'error', title: result.message });
            return;
        }

        const result = await response.json();

        if (result.success) document.getElementById('codigo_crear').value = result.codigo;
    } catch (error) {
        document.getElementById('codigo_crear').value = '';
    }
};

const reiniciarFormularioCreacion = () => {
    document.getElementById('formulario_creacion').reset();
    limpiarInvalidos();
    pintarDV('');
    document.getElementById('activo_crear').checked = true;
    document.getElementById('extractora_crear').checked = false;
    document.getElementById('nit_crear').focus();
};

document.getElementById('btn_limpiar_empresa').addEventListener('click', reiniciarFormularioCreacion);

document.getElementById('formulario_creacion').addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validarCreacion()) return;

    const btn = document.getElementById('btn_crear_empresa');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Guardando...';

    const body = new FormData();
    body.append('Nit', document.getElementById('nit_crear').value);
    body.append('Dv', document.getElementById('dv_crear').value);
    body.append('RazonSocial', document.getElementById('razonSocial_crear').value);
    body.append('CcRepresentante', document.getElementById('cc_crear').value);
    body.append('NombreRepresentante', document.getElementById('nombreRepresentante_crear').value);
    body.append('Telefono', document.getElementById('telefono_crear').value);
    body.append('Direccion', document.getElementById('direccion_crear').value);
    body.append('Email', document.getElementById('email_crear').value);
    body.append('ActividadEconomica', document.getElementById('actividadEconomica_crear').value);
    body.append('Notas', document.getElementById('notas_crear').value);
    body.append('Extractora', document.getElementById('extractora_crear').checked ? 1 : 0);
    body.append('Activo', document.getElementById('activo_crear').checked ? 1 : 0);

    try {
        const response = await fetch(BASE_URL + 'maestros/empresas/crearEmpresa', {
            method: 'POST',
            body
        });

        if (!response.ok) {
            const result = await response.json();
            Toast.fire({ icon: 'warning', title: result.message });
            return;
        }

        const html = await response.text();

        Toast.fire({ icon: 'success', title: 'Empresa creada. El representante quedó registrado como tercero.' });
        aplicarHtmlTabla(html);
        reiniciarFormularioCreacion();
        refrescarCodigo();
        bootstrap.Tab.getOrCreateInstance(document.querySelector('a[href="#listaEmpresas"]')).show();
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Crear empresa';
    }
});

// ── Obtener y abrir modal edición ─────────────────────────────────────────────

const obtenerEmpresa = async (id) => {
    document.getElementById('formulario_editar_empresa').reset();
    document.getElementById('tercero_id_editar').value = '';

    try {
        const response = await fetch(BASE_URL + `maestros/empresas/${id}`, {
            method: 'POST'
        });

        if (!response.ok) {
            const error = await response.json();
            Toast.fire({ icon: 'error', title: error.message });
            return;
        }

        const result = await response.json();

        if (result.success) {
            const empresa = result.empresa;
            document.getElementById('editar_id').value = empresa.id;
            document.getElementById('editar_nit').value = empresa.nit;
            document.getElementById('editar_dv').value = empresa.dv;
            document.getElementById('editar_razonSocial').value = empresa.razonSocial;
            document.getElementById('editar_extractora').checked = Number(empresa.extractora) === 1;
            document.getElementById('editar_activo').checked = Number(empresa.activo) === 1;
            document.getElementById('tercero_id_editar').value = empresa.tercero || '';
            document.getElementById('tercero_buscar_editar').value = empresa.NombreTercero || '';
        }
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    }
};

// ── Guardar edición ───────────────────────────────────────────────────────────

document.getElementById('formulario_editar_empresa').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btn_guardar_edicion');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Guardando...';

    const body = new FormData();
    body.append('Id', document.getElementById('editar_id').value);
    body.append('Nit', document.getElementById('editar_nit').value);
    body.append('Dv', document.getElementById('editar_dv').value);
    body.append('RazonSocial', document.getElementById('editar_razonSocial').value);
    body.append('Tercero', document.getElementById('tercero_id_editar').value);
    body.append('Extractora', document.getElementById('editar_extractora').checked ? 1 : 0);
    body.append('Activo', document.getElementById('editar_activo').checked ? 1 : 0);

    try {
        const response = await fetch(BASE_URL + 'maestros/empresas/actualizarEmpresa', {
            method: 'POST',
            body
        });

        if (!response.ok) {
            const result = await response.json();
            Toast.fire({ icon: 'warning', title: result.message });
            return;
        }

        const html = await response.text();

        bootstrap.Modal.getInstance(document.getElementById('modalEditarEmpresa')).hide();
        Toast.fire({ icon: 'success', title: 'Empresa actualizada correctamente.' });
        aplicarHtmlTabla(html);
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-floppy me-1"></i> Guardar';
    }
});

// ── Eliminar ──────────────────────────────────────────────────────────────────

const botonesFila = (id) =>
    [...document.querySelectorAll(`#cuerpo_tabla_empresas tr[data-id="${id}"] .btn`)];

const avisarDependencias = (result) => {
    const detalle = (result.dependencias || [])
        .map((dep) => `<li><strong>${escaparHtml(dep.tabla)}</strong> · ${Number(dep.filas).toLocaleString('es-CO')} ${Number(dep.filas) === 1 ? 'registro' : 'registros'}</li>`)
        .join('');

    Swal.fire({
        icon: 'error',
        title: 'La empresa está en uso',
        html: `${escaparHtml(result.message)}<ul class="text-start small mb-0">${detalle}</ul>`,
        confirmButtonText: 'Entendido'
    });
};

const eliminarEmpresa = async (id, razonSocial, nit) => {
    const confirmacion = await Swal.fire({
        icon: 'warning',
        title: '¿Eliminar empresa?',
        html: `Se eliminará «${escaparHtml(razonSocial)}» (Nit ${escaparHtml(nit)}).<br>El tercero del representante no se elimina.<br>Esta acción no se puede deshacer.`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        confirmButtonColor: '#dc3545',
        cancelButtonText: 'Cancelar',
        focusCancel: true
    });

    if (!confirmacion.isConfirmed) return;

    const botones = botonesFila(id);
    const iconoBorrar = botones.find((btn) => btn.querySelector('.bi-trash'));

    botones.forEach((btn) => { btn.disabled = true; });
    if (iconoBorrar) iconoBorrar.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i>';

    try {
        const body = new FormData();
        body.append('Id', id);

        const response = await fetch(BASE_URL + 'maestros/empresas/eliminarEmpresa', {
            method: 'POST',
            body
        });

        if (!response.ok) {
            const result = await response.json();

            if (result.dependencias && result.dependencias.length) {
                avisarDependencias(result);
            } else {
                Toast.fire({ icon: 'warning', title: result.message });
            }

            botones.forEach((btn) => { btn.disabled = false; });
            if (iconoBorrar) iconoBorrar.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
            return;
        }

        Toast.fire({ icon: 'success', title: 'Empresa eliminada.' });
        aplicarHtmlTabla(await response.text());
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'Error de conexión.' });
        botones.forEach((btn) => { btn.disabled = false; });
        if (iconoBorrar) iconoBorrar.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
    }
};

document.addEventListener('DOMContentLoaded', renderizarTabla);
