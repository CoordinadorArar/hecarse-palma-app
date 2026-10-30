(() => {
    const selector = document.getElementById('gp_empresa');
    if (selector) {
        selector.addEventListener('change', () => {
            window.location.search = '?empresa=' + encodeURIComponent(selector.value);
        });
    }

    if (typeof ApexCharts === 'undefined' || typeof GP_RESUMEN === 'undefined') {
        return;
    }

    const datos = GP_RESUMEN;
    const fmt = new Intl.NumberFormat('es-CO');
    const fmt1 = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    const escapar = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const alto = () => (window.matchMedia('(min-width: 768px)').matches ? 300 : 240);
    const ejes = { colors: '#8795a4', fontSize: '11px' };
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const cortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

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
                    shortDays: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
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

    const ppto = datos.presupuesto;
    const pptoOp = base('bar', alto());
    pintar('gp_chart_presupuesto', Object.assign(pptoOp, {
        series: [{ name: 'Presupuesto', data: ppto.map((m) => +m.kilos / 1000) }],
        colors: [({ dataPointIndex }) => (ppto[dataPointIndex]?.mes === datos.mes ? '#137a36' : '#1dab4d')],
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, dataLabels: { position: 'top' } } },
        dataLabels: { enabled: window.matchMedia('(min-width: 768px)').matches, formatter: (v) => (v > 0 ? fmt1.format(v) : ''), offsetY: -18, style: { fontSize: '10px', colors: ['#0b2e17'] } },
        legend: { show: false },
        xaxis: { categories: ppto.map((m) => cortos[m.mes - 1]), labels: { style: ejes } },
        yaxis: { min: 0, forceNiceScale: true, labels: { style: ejes, formatter: (v) => fmt.format(Math.round(v)) + ' t' } },
        tooltip: Object.assign(pptoOp.tooltip, {
            x: { formatter: (v, o) => escapar(meses[(ppto[o.dataPointIndex]?.mes ?? 1) - 1]) },
            y: { formatter: (v, o) => escapar(fmt1.format(v) + ' t (' + fmt.format(Math.round(+(ppto[o.dataPointIndex]?.kilos ?? 0))) + ' kg)') }
        })
    }));

    const preciosOp = base('donut', alto());
    pintar('gp_chart_precios', Object.assign(preciosOp, {
        series: [datos.precios.con, datos.precios.sin],
        labels: ['Con precio', 'Sin precio'],
        colors: ['#1dab4d', '#e0a800'],
        legend: Object.assign(preciosOp.legend, { position: 'bottom', horizontalAlign: 'center' }),
        dataLabels: { enabled: true, dropShadow: { enabled: false }, style: { fontSize: '11px', colors: ['#0b2e17'] } },
        stroke: { width: 2 },
        plotOptions: {
            pie: {
                donut: {
                    size: '62%',
                    labels: {
                        show: true,
                        value: { color: '#2f3b48', fontWeight: 700, formatter: (v) => fmt.format(v) },
                        total: { show: true, label: 'Labores', color: '#8795a4', fontSize: '12px', formatter: (w) => fmt.format(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) }
                    }
                }
            }
        }
    }));

    let grupos = datos.grupos.map((g) => ({ nombre: g.nombre || g.grupo, con: +g.conPrecio, sin: +g.sinPrecio })).sort((a, b) => (b.con + b.sin) - (a.con + a.sin));
    if (grupos.length > 10) {
        const resto = grupos.slice(10);
        grupos = grupos.slice(0, 10).concat([{ nombre: 'Otros', con: resto.reduce((s, g) => s + g.con, 0), sin: resto.reduce((s, g) => s + g.sin, 0) }]);
    }
    const gruposOp = base('bar', Math.max(280, grupos.length * 34 + 70));
    pintar('gp_chart_grupos', Object.assign(gruposOp, {
        chart: Object.assign(gruposOp.chart, { stacked: true }),
        series: [
            { name: 'Con precio', data: grupos.map((g) => g.con) },
            { name: 'Sin precio', data: grupos.map((g) => g.sin) }
        ],
        colors: ['#1dab4d', '#e0a800'],
        plotOptions: { bar: { horizontal: true, barHeight: '60%', borderRadius: 3 } },
        grid: Object.assign(gruposOp.grid, { padding: { left: 24 } }),
        dataLabels: { enabled: true, formatter: (v) => (v > 0 ? fmt.format(v) : ''), style: { fontSize: '11px', colors: ['#0b2e17'] } },
        xaxis: { categories: grupos.map((g) => g.nombre), tickAmount: 4, labels: { style: ejes, formatter: (v) => fmt.format(Math.round(v)) } },
        yaxis: { labels: { style: ejes, maxWidth: 240, formatter: (v) => (String(v).length > 24 ? String(v).slice(0, 23) + '…' : v) } },
        tooltip: Object.assign(gruposOp.tooltip, { x: { formatter: (v, o) => escapar(grupos[o.dataPointIndex]?.nombre ?? v) } })
    }));
})();
