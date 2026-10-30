(() => {
    const selector = document.getElementById('tx_empresa');
    if (selector) {
        selector.addEventListener('change', () => {
            window.location.search = '?empresa=' + encodeURIComponent(selector.value);
        });
    }

    if (typeof ApexCharts === 'undefined' || typeof TX_RESUMEN === 'undefined') {
        return;
    }

    const datos = TX_RESUMEN;
    const mods = datos.modulos;
    const fmt = new Intl.NumberFormat('es-CO');
    const fmt1 = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1 });
    const escapar = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const movil = () => !window.matchMedia('(min-width: 768px)').matches;
    const alto = () => (movil() ? 240 : 300);
    const ejes = { colors: '#8795a4', fontSize: '11px' };
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const cortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const dias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    const cuenta = (v, s, p) => fmt.format(v) + ' ' + (+v === 1 ? s : p);

    const base = (tipo, height) => ({
        chart: {
            type: tipo,
            height,
            fontFamily: 'inherit',
            parentHeightOffset: 0,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { speed: 300 },
            locales: [{
                name: 'es',
                options: {
                    months: meses,
                    shortMonths: cortos,
                    days: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                    shortDays: dias
                }
            }],
            defaultLocale: 'es'
        },
        grid: { borderColor: '#eef1f5', strokeDashArray: 3 },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px', formatter: (s) => escapar(s) },
        tooltip: { theme: 'light', y: { formatter: (v) => fmt.format(v) } },
        yaxis: { labels: { style: ejes, formatter: (v) => fmt.format(Math.round(v)) } }
    });

    const pintar = (id, opciones) => {
        const el = document.getElementById(id);
        if (el) {
            new ApexCharts(el, opciones).render();
        }
    };

    const claves = ['produccion', 'labores', 'fertilizacion'];
    const porDia = datos.porDia;
    const totales = porDia.map((d) => claves.reduce((s, c) => s + +d[c], 0));
    const diaOp = base('bar', alto());
    pintar('tx_chart_dia', Object.assign(diaOp, {
        chart: Object.assign(diaOp.chart, { stacked: true }),
        series: claves.map((c) => ({ name: mods[c].nombre, data: porDia.map((d) => +d[c]) })),
        colors: claves.map((c) => mods[c].color),
        plotOptions: { bar: { columnWidth: '60%', borderRadius: 3, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last' } },
        dataLabels: { enabled: false },
        xaxis: Object.assign({ categories: porDia.map((d) => +d.fecha.slice(8, 10)), labels: { rotate: 0, hideOverlappingLabels: true, style: ejes } }, movil() ? { tickAmount: 8 } : {}),
        yaxis: { min: 0, tickAmount: Math.max(1, Math.min(5, Math.max(0, ...totales))), labels: { style: ejes, formatter: (v) => fmt.format(Math.round(v)) } },
        tooltip: Object.assign(diaOp.tooltip, {
            shared: true,
            intersect: false,
            x: {
                formatter: (v, o) => {
                    const f = new Date((porDia[o.dataPointIndex]?.fecha ?? '') + 'T00:00:00');
                    return escapar(dias[f.getDay()] + ' ' + f.getDate() + ' ' + cortos[f.getMonth()].toLowerCase() + ' · ' + cuenta(totales[o.dataPointIndex] ?? 0, 'transacción', 'transacciones'));
                }
            }
        })
    }));

    const modulos = datos.porModulo;
    const moduloOp = base('donut', alto());
    pintar('tx_chart_modulo', Object.assign(moduloOp, {
        series: modulos.map((m) => +m.transacciones),
        labels: modulos.map((m) => (mods[m.clave] ?? mods.otros).nombre),
        colors: modulos.map((m) => (mods[m.clave] ?? mods.otros).color),
        legend: Object.assign(moduloOp.legend, { position: 'bottom', horizontalAlign: 'center' }),
        dataLabels: { enabled: true, formatter: (v) => fmt1.format(v) + ' %', dropShadow: { enabled: false }, style: { fontSize: '11px', colors: modulos.map((m) => (['fertilizacion', 'otros'].includes(m.clave) ? '#1b2a38' : '#fff')) } },
        stroke: { width: 2 },
        tooltip: Object.assign(moduloOp.tooltip, {
            y: {
                formatter: (v, o) => {
                    const an = +(modulos[o.seriesIndex]?.anuladas ?? 0);
                    return escapar(cuenta(v, 'transacción', 'transacciones') + (an > 0 ? ' · ' + cuenta(an, 'anulada', 'anuladas') : ''));
                }
            }
        }),
        plotOptions: {
            pie: {
                donut: {
                    size: '62%',
                    labels: {
                        show: true,
                        value: { color: '#2f3b48', fontWeight: 700, formatter: (v) => fmt.format(v) },
                        total: { show: true, label: 'Transacciones', color: '#8795a4', fontSize: '12px', formatter: (w) => fmt.format(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) }
                    }
                }
            }
        }
    }));

    const fincas = datos.porFinca.map((f) => ({ nombre: f.nombre || f.finca, tx: +f.transacciones, kg: +(f.kg ?? 0) })).sort((a, b) => b.tx - a.tx);
    const fincaOp = base('bar', Math.max(240, fincas.length * 34 + 70));
    pintar('tx_chart_finca', Object.assign(fincaOp, {
        series: [{ name: 'Transacciones', data: fincas.map((f) => f.tx) }],
        colors: ['#287d55'],
        plotOptions: { bar: { horizontal: true, barHeight: '60%', borderRadius: 3 } },
        dataLabels: { enabled: true, formatter: (v) => fmt.format(v), style: { fontSize: '11px', colors: ['#fff'] } },
        legend: { show: false },
        xaxis: { categories: fincas.map((f) => f.nombre), tickAmount: Math.max(1, Math.min(4, Math.max(0, ...fincas.map((f) => f.tx)))), labels: { style: ejes, formatter: (v) => fmt.format(Math.round(v)) } },
        yaxis: { labels: { style: ejes, maxWidth: 180, formatter: (v) => (String(v).length > 22 ? String(v).slice(0, 21) + '…' : v) } },
        tooltip: Object.assign(fincaOp.tooltip, {
            x: { formatter: (v, o) => escapar(fincas[o.dataPointIndex]?.nombre ?? v) },
            y: {
                title: { formatter: () => '' },
                formatter: (v, o) => {
                    const kg = fincas[o.dataPointIndex]?.kg ?? 0;
                    return escapar(cuenta(v, 'transacción', 'transacciones') + (kg > 0 ? ' · ' + fmt.format(Math.round(kg)) + ' kg' : ''));
                }
            }
        })
    }));
})();
