const pfa = { lotes: [], tipoPrevio: '', autoFinal: true, usado: false };

const esPfa = () => texto($id('tipo').value) === 'PFA';
const redondear2 = (valor) => Math.round(Math.round(valor * 1000000) / 10000) / 100;
const bultosDe = (cantidad, pBulto) => (pBulto > 0 ? redondear2(cantidad / pBulto) : 0);
const cantidadItem = (lote, item) => item.cantidad ?? redondear2((item.palmas ?? lote.palmas) * item.dosis);
const bultosItem = (lote, item) => item.mBulto ?? bultosDe(cantidadItem(lote, item), item.pBulto);
const plural = (numero, singular, varios) => `${fmt(numero)} ${numero === 1 ? singular : varios}`;

const aplicarTipo = (tipo) => {
    const esPlan = tipo === 'PFA';
    const columna = $id('col_finca');

    $id('zona_formulario').dataset.tipo = tipo;
    ['col-md-5', 'col-lg-5'].forEach((clase) => columna.classList.toggle(clase, !esPlan));
    ['col-md-8', 'col-lg-3'].forEach((clase) => columna.classList.toggle(clase, esPlan));

    if (esPlan && pfa.autoFinal) $id('pfa_fecha_final').value = $id('fecha').value;

    if (esPlan && !estado.edicion) {
        $id('chip_estado').className = 'gp-chip-fila tx-chip-neutro';
        $id('chip_estado').textContent = 'Nuevo plan de fertilización';
    }
};

const palmasPfa = () => {
    const opcion = $id('pfa_lote').selectedOptions[0];
    const palmas = opcion?.value ? Number(opcion.dataset.palmas || 0) : null;
    const dosis = aNumero($id('pfa_dosis').value);
    const pBulto = aNumero($id('pfa_pbulto').value);

    $id('pfa_palmas').value = palmas === null ? '' : fmt(palmas);
    $id('pfa_bultos').value = palmas !== null && dosis > 0 && pBulto > 0 ? cant(bultosDe(redondear2(palmas * dosis), pBulto)) : '';
};

const cargarLotesPfa = async (finca) => {
    const selector = $id('pfa_lote');

    reiniciarSelect('pfa_lote', finca ? 'Cargando…' : 'Seleccione una finca primero');
    palmasPfa();

    if (!finca) return;

    let filas = [];

    try {
        filas = (await pedir('lotes-finca', { empresa: empresaId(), finca })).filas || [];
    } catch (error) {
        avisar(error);
    }

    if ($id('cap_finca').value !== finca) return;

    selector.innerHTML = `<option value="">${filas.length ? 'Seleccione el lote…' : 'Sin lotes disponibles'}</option>` + filas.map((fila) => {
        const codigo = escaparHtml(texto(fila.codigo));
        const descripcion = escaparHtml(texto(fila.descripcion));
        const palmas = Number(fila.palmas || 0);

        return `<option value="${codigo}" data-palmas="${palmas}" data-nombre="${codigo} — ${descripcion}" data-descripcion="${descripcion}">${codigo} — ${descripcion} · ${fmt(palmas)} palmas</option>`;
    }).join('');
    selector.disabled = filas.length === 0;
    $('#pfa_lote').trigger('change.select2');
    palmasPfa();
};

const tarjetaLote = (lote, indice) => {
    const kg = lote.items.reduce((suma, item) => suma + cantidadItem(lote, item), 0);
    const bultos = lote.items.reduce((suma, item) => suma + bultosItem(lote, item), 0);
    const codigo = escaparHtml(lote.codigo);
    const descripcion = escaparHtml(lote.descripcion);

    return `<div class="tx-tarjeta${lote.marca ? ` ${lote.marca}` : ''}" data-lote-pfa="${codigo}">
        <div class="tx-tarjeta-cab tx-tarjeta-cab--fija flex-wrap">
            <span class="gp-chip-fila tx-chip-neutro font-monospace">#${indice + 1}</span>
            <span><span class="font-monospace fw-semibold">${codigo}</span> — <span class="gp-truncar d-inline-block align-bottom" title="${descripcion}">${descripcion}</span></span>
            <span class="tx-sep">&middot;</span>
            <span class="gp-chip-fila tx-chip-neutro">${fmt(lote.palmas)} palmas</span>
            <span class="tx-chip-cont verde">${plural(lote.items.length, 'insumo', 'insumos')}</span>
            <span class="ms-auto d-flex align-items-end gap-3">
                <span class="tx-dato text-end"><span class="gp-etiqueta-mini">Total kg</span><span class="tx-dato-valor font-monospace">${cant(kg)}</span></span>
                <span class="tx-dato text-end"><span class="gp-etiqueta-mini">Bultos</span><span class="tx-dato-valor font-monospace">${cant(bultos)}</span></span>
                <button type="button" class="btn btn-sm btn-outline-danger" data-quitar-lote="${codigo}" title="Quitar lote ${codigo}" aria-label="Quitar lote ${codigo}">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                </button>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 gp-tabla tx-grilla tx-tabla-trab">
                <thead class="gp-thead">
                    <tr>
                        <th style="min-width:240px">Item</th>
                        <th class="text-center" style="width:80px">U.M.</th>
                        <th class="text-end" style="width:100px">Dosis</th>
                        <th class="text-end" style="width:110px">Peso bulto</th>
                        <th class="text-end" style="width:100px">Bultos</th>
                        <th class="text-end" style="width:130px">Cantidad (kg)</th>
                        <th class="text-center gp-col-sticky" style="width:52px"><span class="visually-hidden">Quitar</span></th>
                    </tr>
                </thead>
                <tbody>${lote.items.map((item, posicion) => {
        const nombre = escaparHtml(item.itemNombre);

        return `<tr class="${item.marca || ''}">
                        <td><span class="gp-truncar d-inline-block align-bottom" title="${nombre}">${nombre}</span><span class="gp-subtexto font-monospace">${escaparHtml(item.item)}</span></td>
                        <td class="text-center"><span class="gp-chip-fila tx-chip-neutro">${escaparHtml(item.uMedida)}</span></td>
                        <td class="text-end tx-num">${cant(item.dosis)}</td>
                        <td class="text-end tx-num">${cant(item.pBulto)}</td>
                        <td class="text-end tx-num">${cant(bultosItem(lote, item))}</td>
                        <td class="text-end tx-num fw-semibold">${cant(cantidadItem(lote, item))}</td>
                        <td class="text-center gp-col-sticky">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-quitar-item="${posicion}" data-lote="${codigo}" title="Quitar ${nombre}" aria-label="Quitar ${nombre}">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>`;
    }).join('')}</tbody>
            </table>
        </div>
    </div>`;
};

const pintarPfa = () => {
    const lotes = pfa.lotes;
    const items = lotes.reduce((suma, lote) => suma + lote.items.length, 0);
    const kg = lotes.reduce((suma, lote) => suma + lote.items.reduce((total, item) => total + cantidadItem(lote, item), 0), 0);
    const bultos = lotes.reduce((suma, lote) => suma + lote.items.reduce((total, item) => total + bultosItem(lote, item), 0), 0);

    $id('pfa_lista').innerHTML = lotes.map(tarjetaLote).join('');
    lotes.forEach((lote) => {
        lote.marca = '';
        lote.items.forEach((item) => { item.marca = ''; });
    });
    mostrar($id('pfa_vacio'), lotes.length === 0);
    [['pfa_total_lotes', fmt(lotes.length), lotes.length], ['pfa_total_insumos', fmt(items), items],
        ['pfa_total_kg', cant(kg), kg], ['pfa_total_bultos', cant(bultos), bultos]].forEach(([id, valor, cantidad]) => {
        $id(id).textContent = valor;
        $id(id).classList.toggle('tx-total--inactivo', !cantidad);
    });
    $id('pfa_res_lotes').textContent = fmt(lotes.length);
    $id('pfa_res_kg').textContent = cant(kg);
};

const cargarPfa = () => {
    const item = $id('pfa_item').value;
    const um = $id('pfa_um');
    const lote = $id('pfa_lote');
    const opcion = lote.selectedOptions[0];
    const dosis = aNumero($id('pfa_dosis').value);
    const pBulto = aNumero($id('pfa_pbulto').value);
    const grupo = pfa.lotes.find((item) => item.codigo === lote.value);
    const duplicado = Boolean(item && grupo?.items.some((actual) => actual.item === item));
    const errorItem = item === '' ? 'Seleccione un insumo.' : (duplicado ? 'El insumo ya está en este lote.' : '');

    $id('pfa_item').closest('.tx-select-ancho').classList.toggle('is-invalid', errorItem !== '');
    $id('pfa_error_item').textContent = errorItem;
    mostrar($id('pfa_error_item'), errorItem !== '');
    mostrar($id('pfa_error_lote'), lote.value === '');
    errorCampo('error_finca', $id('cap_finca').value === '');
    um.classList.toggle('is-invalid', um.value === '');
    $id('pfa_dosis').classList.toggle('is-invalid', !(dosis > 0));
    $id('pfa_pbulto').classList.toggle('is-invalid', !(pBulto > 0));

    if (duplicado) {
        grupo.marca = 'tx-tarjeta--destacada';
        pintarPfa();
        document.querySelector(`[data-lote-pfa="${CSS.escape(grupo.codigo)}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (errorItem || lote.value === '' || um.value === '' || !(dosis > 0) || !(pBulto > 0)) return;

    if (pfa.lotes.reduce((suma, actual) => suma + actual.items.length, 0) >= 2000) {
        Toast.fire({ icon: 'warning', title: 'El plan admite máximo 2000 insumos' });

        return;
    }

    const destino = grupo || { codigo: lote.value, descripcion: texto(opcion.dataset.descripcion), palmas: Number(opcion.dataset.palmas || 0), items: [], marca: 'tx-flash' };

    if (!grupo) pfa.lotes.push(destino);

    destino.items.push({ item, itemNombre: nombreItem(item), uMedida: um.value, dosis, pBulto, marca: 'tx-flash' });
    $('#pfa_item').val('').trigger('change.select2');
    um.value = '';
    $id('pfa_dosis').value = '';
    pintarPfa();
    palmasPfa();
    $('#pfa_item').select2('open');
};

const quitarLotePfa = (codigo) => {
    pfa.lotes = pfa.lotes.filter((lote) => lote.codigo !== codigo);
    pintarPfa();
};

Object.assign(EXT, {
    activo: esPfa,
    cuerpo: () => ({
        empresa: empresaId(),
        tipo: 'PFA',
        anio: $id('anio').value,
        mes: $id('periodo').value,
        fecha: $id('fecha').value,
        fechaFinal: $id('pfa_fecha_final').value,
        finca: $id('cap_finca').value,
        observacion: texto($id('observacion').value),
        items: JSON.stringify(pfa.lotes.flatMap((lote) => lote.items.map((item) => ({
            lote: lote.codigo, item: item.item, uMedida: item.uMedida, dosis: item.dosis, pBulto: item.pBulto
        }))))
    }),
    hayDetalle: () => pfa.lotes.length > 0,
    validar: () => {
        const final = $id('pfa_fecha_final');
        const invalida = final.value === '' || final.value < $id('fecha').value;

        final.classList.toggle('is-invalid', invalida);

        if (invalida) {
            final.focus();

            return false;
        }

        if ($id('cap_finca').value === '') {
            errorCampo('error_finca', true);
            $('#cap_finca').select2('open');

            return false;
        }

        if (pfa.lotes.length === 0) {
            Swal.fire({ icon: 'warning', title: 'El plan no tiene insumos', html: 'Cargue al menos un insumo antes de guardar.', confirmButtonText: 'Entendido' });

            return false;
        }

        return true;
    },
    resumen: () => `${plural(pfa.lotes.length, 'lote', 'lotes')} y ${plural(pfa.lotes.reduce((suma, lote) => suma + lote.items.length, 0), 'insumo', 'insumos')}`,
    error: (fallo) => {
        const lotes = fallo.datos?.lotes;

        if (!Array.isArray(lotes)) return;

        pfa.lotes.forEach((lote) => {
            if (lotes.map(texto).includes(lote.codigo)) lote.marca = 'tx-tarjeta--destacada';
        });
        pintarPfa();
    },
    finca: async () => {
        const selector = $id('cap_finca');

        if (selector.value === (selector.dataset.previo || '')) return;

        if (pfa.lotes.length > 0) {
            const respuesta = await Swal.fire({
                icon: 'warning',
                title: '¿Cambiar la finca?',
                html: `Se quitarán los ${plural(pfa.lotes.length, 'lote cargado', 'lotes cargados')}.`,
                showCancelButton: true,
                confirmButtonText: 'Cambiar y quitar lotes',
                confirmButtonColor: '#d6293e',
                cancelButtonText: 'Conservar',
                focusCancel: true
            });

            if (!respuesta.isConfirmed) {
                $('#cap_finca').val(selector.dataset.previo || '').trigger('change.select2');

                return;
            }

            pfa.lotes = [];
            pintarPfa();
        }

        selector.dataset.previo = selector.value;
        errorCampo('error_finca', false);
        cargarLotesPfa(selector.value);
    },
    limpiar: () => {
        pfa.lotes = [];
        pfa.usado = false;
        pfa.tipoPrevio = '';
        pfa.autoFinal = true;
        $id('pfa_fecha_final').value = $id('fecha').value;
        mostrar($id('pfa_nota_uso'), false);
        $('#pfa_item').val('').trigger('change.select2');
        $id('pfa_um').value = '';
        $id('pfa_dosis').value = '';
        $id('pfa_pbulto').value = '50';
        ['pfa_um', 'pfa_dosis', 'pfa_pbulto', 'pfa_fecha_final'].forEach((id) => $id(id).classList.remove('is-invalid'));
        $id('pfa_item').closest('.tx-select-ancho').classList.remove('is-invalid');
        ['pfa_error_item', 'pfa_error_lote'].forEach((id) => mostrar($id(id), false));
        reiniciarSelect('pfa_lote', 'Seleccione una finca primero');
        palmasPfa();
        pintarPfa();
        aplicarTipo('');
    },
    editar: async (encabezado, resultado, numero) => {
        const finca = texto(encabezado.finca);
        const anio = texto(encabezado.anio);
        const anios = Array.from($id('anio').options);

        $('#tipo').val('PFA').trigger('change.select2');
        pfa.tipoPrevio = 'PFA';
        pfa.autoFinal = false;
        modoEdicion(texto(encabezado.numero) || numero);
        aplicarTipo('PFA');
        habilitarTipo(true);
        $id('fecha').value = texto(encabezado.fecha).substring(0, 10);
        $id('pfa_fecha_final').value = texto(encabezado.fechaFinal).substring(0, 10);
        $id('observacion').value = texto(encabezado.observacion);
        contarObservacion();
        $('#cap_finca').val(finca).trigger('change.select2');
        $id('cap_finca').dataset.previo = finca;

        pfa.lotes = [];
        (resultado.items || []).forEach((dato) => {
            const codigo = texto(dato.lote);
            let lote = pfa.lotes.find((item) => item.codigo === codigo);

            if (!lote) {
                lote = { codigo, descripcion: texto(dato.loteNombre), palmas: Number(dato.palmas || 0), items: [] };
                pfa.lotes.push(lote);
            }

            lote.items.push({
                item: texto(dato.item), itemNombre: texto(dato.itemNombre) || nombreItem(texto(dato.item)), uMedida: texto(dato.uMedida),
                dosis: Number(dato.dosis || 0), pBulto: Number(dato.pBulto || 0), palmas: Number(dato.palmas || 0),
                cantidad: Number(dato.cantidad || 0), mBulto: Number(dato.mBulto || 0)
            });
        });

        pfa.usado = Number(encabezado.usado) === 1;
        mostrar($id('pfa_nota_uso'), pfa.usado);

        if (pfa.usado) {
            $id('chip_estado').className = 'gp-chip-fila ambar';
            $id('chip_estado').innerHTML = `<i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Plan en uso &middot; Editando <span class="font-monospace">${escaparHtml(texto(encabezado.numero) || numero)}</span>`;
        }

        if (anio !== '' && !anios.some((opcion) => opcion.value === anio)) {
            $id('anio').add(new Option(anio, anio), anios.find((opcion) => Number(opcion.value) < Number(anio)) || null);
        }

        $id('anio').value = anio;
        pintarPfa();
        await cargarPeriodos(anio, encabezado.mes);
        await cargarLotesPfa(finca);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    if (!$id('pfa_item')) return;

    $id('pfa_item').insertAdjacentHTML('beforeend', itemsCatalogo().map((item) => `<option value="${escaparHtml(texto(item.codigo))}" data-um="${escaparHtml(texto(item.uMedida))}">${escaparHtml(texto(item.descripcion))}</option>`).join(''));
    $id('pfa_um').innerHTML = $id('cap_um').innerHTML;
    $('#pfa_item').select2({ width: '100%', placeholder: 'Seleccione un insumo…', allowClear: true, language: IDIOMA_SELECT2 });
    $('#pfa_lote').select2({ width: '100%', placeholder: 'Seleccione una opción…', allowClear: true, language: IDIOMA_SELECT2, templateResult: plantillaOpcion });
    pintarPfa();

    $('#tipo').on('change', async () => {
        const nuevo = texto($id('tipo').value);

        if (estado.edicion || nuevo === pfa.tipoPrevio) return;

        if (pfa.tipoPrevio && (estado.lineas.length > 0 || pfa.lotes.length > 0)) {
            const respuesta = await Swal.fire({
                icon: 'warning',
                title: '¿Cambiar el tipo de transacción?',
                html: 'Se descartará el detalle cargado.',
                showCancelButton: true,
                confirmButtonText: 'Cambiar',
                cancelButtonText: 'Conservar',
                focusCancel: true
            });

            if (!respuesta.isConfirmed) {
                $('#tipo').val(pfa.tipoPrevio).trigger('change.select2');
                habilitarTipo(true);
                aplicarTipo(pfa.tipoPrevio);

                return;
            }
        }

        if (pfa.tipoPrevio) {
            estado.lineas = [];
            pintarLineas();
            pfa.lotes = [];
            pintarPfa();
            $('#cap_finca').val('').trigger('change.select2');
            $id('cap_finca').dataset.previo = '';
            cargarPlanes('');
            cargarSecciones('');
            reiniciarSelect('cap_lote', 'Seleccione una finca primero');
            cargarLotesPfa('');
        }

        pfa.tipoPrevio = nuevo;
        aplicarTipo(nuevo);
    });

    ['input', 'change'].forEach((evento) => $id('fecha').addEventListener(evento, () => {
        if (pfa.autoFinal) $id('pfa_fecha_final').value = $id('fecha').value;
    }));
    $id('pfa_fecha_final').addEventListener('input', () => {
        pfa.autoFinal = false;
        $id('pfa_fecha_final').classList.remove('is-invalid');
    });
    $('#pfa_item').on('change', () => {
        const sugerida = $id('pfa_item').selectedOptions[0]?.dataset.um || '';

        $id('pfa_item').closest('.tx-select-ancho').classList.remove('is-invalid');
        mostrar($id('pfa_error_item'), false);
        $id('pfa_um').value = Array.from($id('pfa_um').options).some((opcion) => opcion.value === sugerida) ? sugerida : '';
        $id('pfa_um').classList.remove('is-invalid');
    });
    $('#pfa_lote').on('change', () => {
        mostrar($id('pfa_error_lote'), false);
        palmasPfa();
    });
    $id('pfa_um').addEventListener('change', () => $id('pfa_um').classList.remove('is-invalid'));
    ['pfa_dosis', 'pfa_pbulto'].forEach((id) => {
        $id(id).addEventListener('input', () => {
            $id(id).classList.remove('is-invalid');
            palmasPfa();
        });
        $id(id).addEventListener('keydown', (evento) => {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                cargarPfa();
            }
        });
    });
    $id('pfa_cargar').addEventListener('click', cargarPfa);
    $id('pfa_lista').addEventListener('click', (evento) => {
        const lote = evento.target.closest('[data-quitar-lote]');
        const item = evento.target.closest('[data-quitar-item]');

        if (lote) quitarLotePfa(lote.dataset.quitarLote);

        if (item) {
            const grupo = pfa.lotes.find((actual) => actual.codigo === item.dataset.lote);

            grupo.items.splice(Number(item.dataset.quitarItem), 1);

            if (grupo.items.length === 0) {
                quitarLotePfa(grupo.codigo);

                return;
            }

            grupo.marca = 'tx-recalculo';
            pintarPfa();
        }
    });
});
