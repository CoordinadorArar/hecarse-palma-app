/*
  Actualización de lotes según Documentacion/LotesHato.xlsx (2026-11-25), empresa 1.

  - Los lotes del archivo quedan activo = 1 con palmas y hectáreas del archivo
    (palmasBrutas = palmasProduccion = Palmas; hBrutas = hNetas = Hectáreas).
  - Los lotes de palma que no están en el archivo quedan activo = 0.
  - Los lotes auxiliares (DISTRIBUIR, GANADERIA, VIVERO) no se tocan: no son lotes de palma
    y se usan para distribuir costos. Ver bloque opcional al final.
  - SIEM-24 (Hato) del archivo corresponde al lote existente HTL2024 "LOTE SIEMBRA 2024 HATO".
  - CRL1-14, CRL5-14 y CRL6B-14 conservan sus palmas brutas y en producción actuales;
    solo se actualizan hectáreas y estado.
  - No modifica aLotesDetalle (líneas): el dato del archivo es el vigente según agronomía.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @Empresa INT = 1;

DECLARE @Archivo TABLE (finca VARCHAR(50), codigo VARCHAR(50), palmas INT, hectareas DECIMAL(18,2));

INSERT INTO @Archivo (finca, codigo, palmas, hectareas) VALUES
('001', 'HTL24-01',    536,   3.75),
('001', 'HTL25-01',    243,   1.70),
('001', 'HTL26-01',    569,   3.98),
('001', 'HTL27-01',    762,   5.33),
('001', 'HTL28-01',    589,   4.12),
('001', 'HTL29-03',    782,   5.47),
('001', 'HTL30-03',   1069,   7.48),
('001', 'HTL31-01',    634,   4.43),
('001', 'HTL32-01',    235,   1.64),
('001', 'HTL37-09',   8250,  57.69),
('001', 'HTL51-03',    326,   2.28),
('001', 'HTL53-02',    734,   5.13),
('001', 'HTL2024',    9301,  65.00),
('001', 'SIEM-26',    7515,  52.55),
('002', 'CRL1-14',    1286,   9.03),
('002', 'CRL2-14',     980,   6.99),
('002', 'CRL3-14',    1230,   8.76),
('002', 'CRL4-14',    1401,   9.87),
('002', 'CRL5-14',    1632,  11.24),
('002', 'CRL6A-14',   1297,   9.27),
('002', 'CRL6B-14',   1223,   8.60),
('002', 'CRL7-14',    2095,  14.74),
('002', 'CRL8A-14',    620,   4.49),
('002', 'CRL8B-14',   3972,  27.94),
('002', 'CRL9-14',    5452,  38.61),
('002', 'CRL10-14',   1913,  13.46),
('002', 'CRL11-14',   2375,  16.74),
('002', 'CRL12-14',   2210,  15.60),
('002', 'CRL13-14',    472,   3.34),
('002', 'CRL14A-21',  2072,  18.43),
('002', 'CRL 14B-21', 3055,  19.08),
('003', 'CGL1-08',     524,   3.66),
('003', 'CGL2-08',     574,   4.01),
('003', 'CGL3-08',     628,   4.39),
('003', 'CGL4-08',     817,   5.71),
('003', 'CGL5-08',     222,   1.55),
('003', 'CGL6-08',     751,   5.25),
('003', 'CGL7-08',     288,   2.01),
('003', 'CGL8-08',    1008,   7.05),
('003', 'CGL9-08',     982,   6.87),
('003', 'CGL10-22',    323,   2.26),
('003', 'CGL11-22',    682,   4.77),
('003', 'CGL12-22',    778,   5.44),
('003', 'CGL13-22',   2334,  16.32),
('003', 'CGL14-22',    485,   3.39),
('003', 'SIEM-23',     517,   3.62),
('003', 'SIEMC-24',   1941,  13.57);

DECLARE @Auxiliares TABLE (codigo VARCHAR(50));

INSERT INTO @Auxiliares (codigo) VALUES
('DGHT26-01'), ('DHT26-01'), ('DCR26-01'), ('CRLLC2-25'), ('VIV-22'), ('DCG26-01'), ('DGCR26-01'), ('DGCR26-02');

IF EXISTS (SELECT 1 FROM @Archivo a WHERE NOT EXISTS (
    SELECT 1 FROM aLotes l WHERE l.empresa = @Empresa AND LTRIM(RTRIM(l.finca)) = a.finca AND LTRIM(RTRIM(l.codigo)) = a.codigo))
BEGIN
    SELECT a.* FROM @Archivo a WHERE NOT EXISTS (
        SELECT 1 FROM aLotes l WHERE l.empresa = @Empresa AND LTRIM(RTRIM(l.finca)) = a.finca AND LTRIM(RTRIM(l.codigo)) = a.codigo);
    RAISERROR('Hay lotes del archivo que no existen en aLotes. No se aplicó ningún cambio.', 16, 1);
    ROLLBACK TRANSACTION;
    RETURN;
END;

UPDATE l
   SET l.palmasBrutas     = CASE WHEN a.codigo IN ('CRL1-14', 'CRL5-14', 'CRL6B-14') THEN l.palmasBrutas ELSE a.palmas END,
       l.palmasProduccion = CASE WHEN a.codigo IN ('CRL1-14', 'CRL5-14', 'CRL6B-14') THEN l.palmasProduccion ELSE a.palmas END,
       l.hBrutas          = a.hectareas,
       l.hNetas           = a.hectareas,
       l.activo           = 1
  FROM aLotes l
  JOIN @Archivo a ON LTRIM(RTRIM(l.finca)) = a.finca AND LTRIM(RTRIM(l.codigo)) = a.codigo
 WHERE l.empresa = @Empresa;

PRINT CONCAT('Lotes actualizados: ', @@ROWCOUNT);

UPDATE l
   SET l.activo = 0
  FROM aLotes l
 WHERE l.empresa = @Empresa
   AND LTRIM(RTRIM(l.finca)) IN ('001', '002', '003')
   AND NOT EXISTS (SELECT 1 FROM @Archivo a WHERE a.finca = LTRIM(RTRIM(l.finca)) AND a.codigo = LTRIM(RTRIM(l.codigo)))
   AND NOT EXISTS (SELECT 1 FROM @Auxiliares x WHERE x.codigo = LTRIM(RTRIM(l.codigo)));

PRINT CONCAT('Lotes inactivados: ', @@ROWCOUNT);

INSERT INTO Auditoria (TablaAfectada, Accion, CambiosRealizados, IdUsuario, FechaRegistro)
VALUES ('aLotes', 'UPDATE', '{"script":"actualizar_lotes_2026-11.sql","origen":"Documentacion/LotesHato.xlsx"}', NULL, GETDATE());

SELECT LTRIM(RTRIM(finca)) AS finca, LTRIM(RTRIM(codigo)) AS codigo, descripcion, palmasBrutas, palmasProduccion, hBrutas, hNetas, activo
  FROM aLotes
 WHERE empresa = @Empresa
 ORDER BY finca, activo DESC, codigo;

COMMIT TRANSACTION;

/*
  Opcional: si también deben quedar inactivos los lotes auxiliares, ejecutar aparte:

  UPDATE aLotes SET activo = 0
   WHERE empresa = 1
     AND LTRIM(RTRIM(codigo)) IN ('DGHT26-01', 'DHT26-01', 'DCR26-01', 'CRLLC2-25', 'VIV-22', 'DCG26-01', 'DGCR26-01', 'DGCR26-02');
*/
