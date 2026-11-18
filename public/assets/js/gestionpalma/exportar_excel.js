(() => {
    const NUM = /^-?\d+(\.\d+)?$/;
    const limpio = (s) => s.replace(/\s+/g, ' ').trim();

    const clonar = (nodo) => {
        const c = nodo.cloneNode(true);
        c.querySelectorAll('i, button, .visually-hidden, .d-none').forEach((e) => e.remove());
        return c;
    };

    const textos = (nodos) => nodos.map((e) => {
        const t = limpio(e.textContent);
        return /^\+\d+$/.test(t) && e.title ? e.title : t;
    }).join(', ');

    const celda = (td) => {
        const original = td.cloneNode(true);
        original.querySelectorAll('i[class*="bi-arrow"]').forEach((e) => e.replaceWith('\u0001'));
        const c = clonar(original);
        const chips = [...c.querySelectorAll('.gp-chip-rol')];
        if (chips.length > 1) return textos(chips.filter((e) => e.classList.contains('activo')));
        const badges = [...c.querySelectorAll('.badge, .gp-chip-fila')];
        if (badges.length > 1) return textos(badges);
        c.querySelectorAll('div, .gp-subtexto, .gp-ruta-ambito').forEach((e) => e.before('\u0001'));
        const texto = c.textContent.split('\u0001').map(limpio).filter((p) => p && p !== '—').join(' - ');
        if (texto === '') return td.querySelector('[title]')?.title ?? '';
        if (!td.classList.contains('text-end')) return /^-?\d{1,3}(\.\d{3})+$/.test(texto) ? texto.replace(/\./g, '') : texto;
        if ((texto.match(/\d[\d.,]*/g) || []).length !== 1) return texto;
        const orden = td.getAttribute('data-order') ?? '';
        if (NUM.test(orden)) return orden;
        const numero = texto.replace(/\./g, '').replace(',', '.').replace(/ha|%|\$|\s/g, '');
        return NUM.test(numero) ? numero : texto;
    };

    const filtros = (api) => {
        const partes = [...document.querySelectorAll('.gp-filtros select, .gp-filtros input:not([type=checkbox]):not([type=radio]):not([type=hidden])')]
            .filter((c) => c.id && !c.closest('.d-none') && c.value.trim() !== '')
            .map((c) => {
                const label = document.querySelector(`label[for="${c.id}"]`);
                const valor = c.tagName === 'SELECT' ? [...c.selectedOptions].map((o) => o.text).join(', ') : c.value;
                return label ? `${limpio(label.textContent)}: ${limpio(valor)}` : '';
            })
            .filter(Boolean);
        const busqueda = limpio(api.search());
        if (busqueda) partes.push(`Buscar en resultados: ${busqueda}`);
        return partes.join(' · ');
    };

    $(document).on('init.dt', (e, settings) => {
        if (e.namespace !== 'dt' || settings.nTable.id === 'tabla_lote_lineas' || !$(settings.nTable).closest('#main').length) return;
        const wrapper = $(settings.nTableWrapper);
        if (wrapper.find('.gp-dt-excel').length) return;

        const api = new $.fn.dataTable.Api(settings);
        const titulo = limpio($('.pagetitle h1').first().text());
        const filtro = wrapper.find('.dataTables_filter').first();
        const destino = filtro.length ? filtro : wrapper.find('.gp-dt-barra').first();

        new $.fn.dataTable.Buttons(api, {
            dom: {
                container: { className: `dt-buttons gp-dt-excel${filtro.length ? '' : ' ms-auto'}` },
                button: { className: 'btn btn-outline-success btn-sm text-nowrap' }
            },
            buttons: [{
                extend: 'excelHtml5',
                text: '<i class="bi bi-file-earmark-excel me-1" aria-hidden="true"></i>Exportar a Excel',
                title: titulo,
                sheetName: titulo.replace(/[\[\]:*?\/\\]/g, '-').slice(0, 31),
                filename: () => `${titulo.replace(/[\\\/:*?"<>|]/g, '-')} - ${new Date().toLocaleDateString('sv-SE')}`,
                messageTop: () => filtros(api),
                exportOptions: {
                    columns: (i, d, th) => {
                        const t = limpio(clonar(th).textContent);
                        return !th.classList.contains('gp-col-sticky') && t !== '' && t.toLowerCase() !== 'acciones';
                    },
                    modifier: { search: 'applied', order: 'applied', page: 'all' },
                    format: {
                        header: (d, i) => limpio(clonar(api.column(i).header()).textContent),
                        body: (d, r, c, td) => celda(td)
                    }
                }
            }]
        });

        const contenedor = api.buttons().container().appendTo(destino);
        const estado = () => {
            const hay = api.rows({ search: 'applied' }).count() > 0;
            api.button(0).enable(hay);
            contenedor.find('.btn').add(contenedor).attr('title', hay ? 'Descargar en Excel los registros listados (respeta la búsqueda)' : 'No hay registros para exportar');
        };

        $(settings.nTable).off('draw.gpExcel').on('draw.dt.gpExcel', estado);
        estado();
    });
})();
