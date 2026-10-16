$.extend(true, $.fn.dataTable.defaults, {
    pageLength: 25,
    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Todos']],
    autoWidth: false,
    orderClasses: false,
    searching: true,
    dom: "<'gp-dt-barra'lf><'table-responsive't><'gp-dt-barra gp-dt-pie'ip>",
    language: {
        search: 'Buscar:',
        searchPlaceholder: 'Escriba para filtrar…',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
        infoEmpty: 'Sin registros para mostrar',
        infoFiltered: '(filtrado de _MAX_ registros)',
        infoPostFix: '',
        zeroRecords: 'Ningún registro coincide con la búsqueda',
        emptyTable: 'No hay datos disponibles',
        loadingRecords: 'Cargando…',
        processing: 'Procesando…',
        thousands: '.',
        paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
        aria: { sortAscending: ': activar para ordenar ascendente', sortDescending: ': activar para ordenar descendente' }
    }
});
