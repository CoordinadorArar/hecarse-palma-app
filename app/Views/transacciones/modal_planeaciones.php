    <div class="modal fade" id="modalPlaneaciones" tabindex="-1" aria-labelledby="titulo_planeaciones" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-md-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title d-flex flex-wrap align-items-center gap-2" id="titulo_planeaciones">
                        <span><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Planeaciones completadas</span>
                        <span class="gp-chip-fila tx-chip-neutro font-monospace" id="pla_rango"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="row g-2 align-items-end mb-3" id="pla_filtros">
                        <div class="col-6 col-md-2">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="pla_desde">Desde</label>
                            <input type="date" class="form-control form-control-sm" id="pla_desde">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="pla_hasta">Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="pla_hasta">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="pla_finca">Finca</label>
                            <select class="form-select form-select-sm" id="pla_finca">
                                <option value="">Todas las fincas</option>
                                <?php foreach ($fincas ?? [] as $finca): ?>
                                    <option value="<?= esc(trim((string) $finca['codigo']), 'attr') ?>"><?= esc(trim((string) $finca['codigo'])) ?> &mdash; <?= esc(trim((string) $finca['descripcion'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md">
                            <label class="form-label mb-1 small fw-semibold text-muted" for="pla_buscar">Buscar</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                                <input type="search" class="form-control" id="pla_buscar" placeholder="Lote, labor o trabajador" autocomplete="off" maxlength="60">
                            </div>
                        </div>
                        <div class="col-12 col-md-auto d-none" id="pla_limpiar_col">
                            <button type="button" class="btn btn-link btn-sm px-0" data-pla-limpiar>Limpiar filtros</button>
                        </div>
                    </div>
                    <div id="pla_cuerpo" aria-busy="false" aria-live="polite"></div>
                </div>
                <div class="modal-footer py-2 justify-content-between">
                    <span class="gp-contador" id="pla_seleccion" aria-live="polite"><strong>0</strong> seleccionadas</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" id="pla_agregar" disabled>
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar al detalle
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
