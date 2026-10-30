(() => {
    if (typeof ApexCharts === 'undefined' || typeof ADMIN_RESUMEN === 'undefined') {
        return;
    }

    const datos = ADMIN_RESUMEN;
    const fmt = new Intl.NumberFormat('es-CO');
    const escapar = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const alto = () => (window.matchMedia('(min-width: 768px)').matches ? 300 : 240);
    const ejes = { colors: '#8795a4', fontSize: '11px' };

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
                    months: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                    shortMonths: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    days: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                    shortDays: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']
                }
            }],
            defaultLocale: 'es'
        },
        grid: { borderColor: '#eef1f5', strokeDashArray: 3 },
        legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px' },
        tooltip: { theme: 'light', y: { formatter: (v) => fmt.format(v) } },
        yaxis: { labels: { style: ejes, formatter: (v) => fmt.format(Math.round(v)) } }
    });

    const pintar = (id, opciones) => {
        const el = document.getElementById(id);
        if (el) {
            new ApexCharts(el, opciones).render();
        }
    };

    const sesiones = base('area', alto());
    pintar('ao_chart_sesiones', Object.assign(sesiones, {
        series: [
            { name: 'Sesiones', type: 'area', data: datos.sesiones.map((d) => [new Date(d.fecha + 'T00:00:00').getTime(), +d.sesiones]) },
            { name: 'Usuarios', type: 'line', data: datos.sesiones.map((d) => [new Date(d.fecha + 'T00:00:00').getTime(), +d.usuarios]) }
        ],
        colors: ['#1dab4d', '#0f5f2c'],
        stroke: { curve: 'smooth', width: [2, 2] },
        fill: { type: ['gradient', 'solid'], opacity: [1, 1], gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .05, stops: [0, 100] } },
        dataLabels: { enabled: false },
        xaxis: { type: 'datetime', labels: { style: ejes, format: 'dd MMM', datetimeUTC: false } },
        yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0, labels: { style: ejes, formatter: (v) => fmt.format(Math.round(v)) } },
        tooltip: Object.assign(sesiones.tooltip, { x: { format: 'dd MMM yyyy' } })
    }));

    let roles = datos.roles.slice().sort((a, b) => b.usuarios - a.usuarios);
    if (roles.length > 10) {
        const otros = roles.slice(10).reduce((s, r) => s + +r.usuarios, 0);
        roles = roles.slice(0, 10).concat([{ rol: 'Otros', abreviatura: null, usuarios: otros }]);
    }
    const largo = fmt.format(Math.max(0, ...roles.map((r) => +r.usuarios))).length;
    const rolesOp = base('bar', Math.max(280, roles.length * 28 + 60));
    pintar('ao_chart_roles', Object.assign(rolesOp, {
        series: [{ name: 'Usuarios', data: roles.map((r) => +r.usuarios) }],
        colors: ['#1dab4d'],
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%', dataLabels: { position: 'top' } } },
        grid: Object.assign(rolesOp.grid, { padding: { right: largo * 9 + 12 } }),
        dataLabels: { enabled: true, formatter: (v) => fmt.format(v), offsetX: largo * 4.5 + 10, style: { fontSize: '11px', colors: ['#0b2e17'] } },
        legend: { show: false },
        xaxis: { categories: roles.map((r) => r.abreviatura || r.rol), tickAmount: 4, labels: { style: ejes, formatter: (v) => fmt.format(Math.round(v)) } },
        yaxis: { max: Math.ceil(Math.max(1, ...roles.map((r) => +r.usuarios)) * 1.35), decimalsInFloat: 0, labels: { style: ejes } },
        tooltip: Object.assign(rolesOp.tooltip, { x: { formatter: (v, o) => escapar(roles[o.dataPointIndex]?.rol ?? v) } })
    }));

    const losetas = datos.losetas;
    const losetasOp = base('bar', alto());
    pintar('ao_chart_losetas', Object.assign(losetasOp, {
        series: [
            { name: 'Módulos', data: losetas.map((l) => +l.modulos) },
            { name: 'Submódulos', data: losetas.map((l) => +l.submodulos) },
            { name: 'Acciones', data: losetas.map((l) => +l.acciones) }
        ],
        colors: ['#1dab4d', '#6cc98d', '#b5e3c4'],
        plotOptions: { bar: { columnWidth: '45%', borderRadius: 3 } },
        dataLabels: { enabled: false },
        xaxis: {
            categories: losetas.map((l) => l.loseta),
            labels: { style: ejes, rotate: -35, rotateAlways: losetas.length > 4, trim: false, formatter: (v) => (String(v).length > 18 ? String(v).slice(0, 17) + '…' : v) }
        },
        tooltip: Object.assign(losetasOp.tooltip, { x: { formatter: (v, o) => escapar(losetas[o.dataPointIndex]?.loseta ?? v) } })
    }));
})();
