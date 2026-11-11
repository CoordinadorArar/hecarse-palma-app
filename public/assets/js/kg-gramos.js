(() => {
    const formato = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1, useGrouping: 'always' });
    let pendiente = 0;

    const leer = (input) => {
        const valor = input.value.trim();

        if (valor === '') return NaN;
        if (window.kgGramos.parsear) return Number(window.kgGramos.parsear(valor));
        if (input.type === 'number') return input.valueAsNumber;

        return Number(valor.replace(/\./g, '').replace(',', '.'));
    };

    const unidad = (input) => {
        if (input.dataset.gramosUm) return document.querySelector(input.dataset.gramosUm)?.value || '';

        return input.dataset.gramos || 'KG';
    };

    const pintar = (input) => {
        const span = (input.getAttribute('aria-describedby') || '').split(/\s+/).map((id) => id && document.getElementById(id)).find((el) => el?.classList.contains('gp-gramos'));

        if (!span) return;

        const kg = leer(input);
        const visible = kg > 0 && String(unidad(input)).trim().toUpperCase() === 'KG' && !input.matches(':disabled') && !input.readOnly;
        const contenido = visible ? `= ${formato.format(kg * 1000)} g${input.dataset.gramosSufijo || ''}` : '';

        span.classList.toggle('d-none', !visible);
        if (span.textContent !== contenido) span.textContent = contenido;
    };

    const refrescar = () => document.querySelectorAll('[data-gramos],[data-gramos-um]').forEach(pintar);

    const programar = () => {
        if (!pendiente) pendiente = requestAnimationFrame(() => { pendiente = 0; refrescar(); });
    };

    [[HTMLInputElement, 'value'], [HTMLSelectElement, 'value'], [HTMLOptionElement, 'selected']].forEach(([clase, propiedad]) => {
        const descriptor = Object.getOwnPropertyDescriptor(clase.prototype, propiedad);

        Object.defineProperty(clase.prototype, propiedad, { ...descriptor, set(valor) { descriptor.set.call(this, valor); programar(); } });
    });

    window.kgGramos = { parsear: null, refrescar };

    $(document).on('input change', refrescar).on('reset', programar);
    new MutationObserver(programar).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'readonly', 'data-gramos'] });
    programar();
})();
