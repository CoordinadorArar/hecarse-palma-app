<div class="lpl-loader" id="lplLoader" role="status" aria-live="polite" hidden>
    <div class="spinner-border text-success" aria-hidden="true"></div>
    <span class="lpl-loader-texto">Cargando próximas labores…</span>
</div>
<div class="modal fade lpl-modal" id="modalProximasLabores" tabindex="-1" aria-labelledby="lplTitulo" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header lpl-header">
                <div class="d-flex align-items-center gap-3 lpl-min0">
                    <span class="lpl-icono" aria-hidden="true"><i class="bi bi-calendar-check"></i></span>
                    <div class="lpl-min0">
                        <h5 class="modal-title lpl-titulo" id="lplTitulo">Próximas labores agrícolas</h5>
                        <small class="text-muted" id="lplSubtitulo"></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body lpl-body">
                <div class="lpl-kpis" id="lplKpis"></div>
                <div id="lplAvisos"></div>
                <ul class="nav nav-pills lpl-tabs" id="lplTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" id="lplTabPlaneadas" data-bs-toggle="pill" data-bs-target="#lplPanePlaneadas" role="tab" aria-controls="lplPanePlaneadas" aria-selected="false">Planeadas <span class="badge">0</span></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" id="lplTabCiclos" data-bs-toggle="pill" data-bs-target="#lplPaneCiclos" role="tab" aria-controls="lplPaneCiclos" aria-selected="false">Por ciclo <span class="badge">0</span></button>
                    </li>
                </ul>
                <div class="tab-content" id="lplPanes">
                    <div class="tab-pane fade" id="lplPanePlaneadas" role="tabpanel" aria-labelledby="lplTabPlaneadas" tabindex="0"></div>
                    <div class="tab-pane fade" id="lplPaneCiclos" role="tabpanel" aria-labelledby="lplTabCiclos" tabindex="0"></div>
                </div>
                <div class="lpl-vacio" id="lplVacio" hidden>
                    <i class="bi bi-calendar2-x" aria-hidden="true"></i>
                    <p class="mb-0" id="lplVacioTexto"></p>
                </div>
            </div>
            <div class="modal-footer lpl-footer">
                <div class="form-check mb-0" id="lplOcultarWrap">
                    <input class="form-check-input" type="checkbox" id="lplOcultarHoy">
                    <label class="form-check-label lpl-ocultar" for="lplOcultarHoy">No volver a mostrar hoy</label>
                </div>
                <div class="lpl-acciones">
                    <a class="btn btn-outline-success lpl-btn-planeacion" id="lplIrPlaneacion" href="#" aria-label="Ir a planeación" title="Ir a planeación" hidden><i class="bi bi-calendar3" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">Ir a planeación</span></a>
                    <button type="button" class="btn lpl-btn-entendido" id="lplEntendido" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    window.LPL_USU_ID = <?= (int) session('usu_id') ?>;
    window.LPL_URL = "<?= base_url('losetas/alerta-labores') ?>";
</script>
