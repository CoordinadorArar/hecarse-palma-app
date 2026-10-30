/* ============================================================================
   Corrección del reparto de kg entre trabajadores en transacciones TLC
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     En la cosecha (tipo TLC) registrada desde AppPalma la pantalla asignaba los
     kg completos de la línea a CADA trabajador y no recalculaba cuando los kg
     de la línea cambiaban. Resultado: trabajadores con mucho más kg (y valor)
     que el tiquete. En Infos la suma de kg de los trabajadores de una misma
     labor en una línea siempre es igual a los kg de la línea.

     Este script toma en AppPalma.dbo.aTransaccionTercero los grupos
     (empresa, tipo, numero, registroNovedad, novedad) de documentos TLC no
     anulados ni eliminados en los que |Σ cantidad trabajadores − cantidad de la
     línea (aTransaccionNovedad)| > 0.01, y reparte los kg de la línea entre los
     trabajadores del grupo con la misma regla de la aplicación:
       - centavos = ROUND(kg línea × 100, 0).
       - peso de cada trabajador = cantidad actual (negativos = 0); si todos
         los pesos son 0, todos pesan 1.
       - base = FLOOR(centavos × peso / Σ pesos); el sobrante se reparte de a
         1 centavo por mayor resto (desempate: orden de registro en el grupo).
       - cantidad = saldo = base / 100; valorTotal = ROUND(cantidad × precioLabor, 2).
     Por cada documento corregido inserta una fila en dbo.Auditoria con el
     antes/después de cada trabajador.

   EXCLUSIONES (se listan aparte, no se tocan)
   -------------------------------------------
     - Grupos con alguna fila liquidada: periodo IS NOT NULL, ejecutado = 1 o
       saldo <> cantidad.
     - Grupos cuya línea tiene más de una fila en aTransaccionNovedad con el
       mismo registro (kg de línea ambiguo).
     - Documentos anulados y documentos eliminados lógicamente
       (dbo.aTransaccionEliminada, solo si la tabla existe).

   MODOS
   -----
     @SoloVistaPrevia = 1 (por defecto): solo calcula en tablas temporales (#)
       y muestra la vista previa. No abre transacción ni modifica AppPalma.
     @SoloVistaPrevia = 0 y @Aplicar = 0: además ejecuta la corrección dentro de
       una transacción, muestra el resumen y hace ROLLBACK. No queda ningún cambio.
     @SoloVistaPrevia = 0 y @Aplicar = 1: lo mismo pero hace COMMIT.
     Cualquier error revierte TODO.

   RESULTADOS DE LA VISTA PREVIA
   -----------------------------
     1. Detalle por documento y trabajador: kg/valor actual vs corregido.
     2. Totales por documento (incluye kg del tiquete = Σ kg de las líneas).
     3. Totales por tercero (NIT y nombre de cTercero).
     4. Total general.
     5. Grupos excluidos y motivo.

   CODIFICACIÓN / FORMA DE EJECUTAR
   --------------------------------
   Este archivo es UTF-8 y contiene columnas [año]. Ejecutarlo SOLO desde SSMS
   o con "sqlcmd -f 65001 -i corregir_reparto_kg_trabajadores_tlc.sql",
   conectado a AppPalma.
   ============================================================================ */

USE AppPalma;

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @SoloVistaPrevia BIT = 1;
DECLARE @Aplicar BIT = 0;
DECLARE @Motivo NVARCHAR(100) = N'Corrección reparto kg trabajadores (script)';
DECLARE @Esperadas INT, @Filas INT, @Documentos INT, @Pendientes INT;

IF OBJECT_ID('tempdb..#eliminadas') IS NOT NULL DROP TABLE #eliminadas;
IF OBJECT_ID('tempdb..#lineas') IS NOT NULL DROP TABLE #lineas;
IF OBJECT_ID('tempdb..#filas') IS NOT NULL DROP TABLE #filas;
IF OBJECT_ID('tempdb..#grupos') IS NOT NULL DROP TABLE #grupos;
IF OBJECT_ID('tempdb..#calculo') IS NOT NULL DROP TABLE #calculo;
IF OBJECT_ID('tempdb..#resultado') IS NOT NULL DROP TABLE #resultado;

/* ---------- 1. CÁLCULO Y VISTA PREVIA (solo lectura sobre AppPalma) ---------- */

CREATE TABLE #eliminadas (
    empresa INT NOT NULL,
    numero  VARCHAR(50) COLLATE DATABASE_DEFAULT NOT NULL
);

IF OBJECT_ID('AppPalma.dbo.aTransaccionEliminada', 'U') IS NOT NULL
    EXEC (N'INSERT INTO #eliminadas (empresa, numero)
            SELECT empresa, LTRIM(RTRIM(numero)) FROM AppPalma.dbo.aTransaccionEliminada WHERE tipo = ''TLC''');

SELECT n.empresa, LTRIM(RTRIM(n.numero)) AS numeroDoc, n.registro,
       MAX(ISNULL(n.cantidad, 0)) AS kgLinea, COUNT(*) AS filasLinea
INTO #lineas
FROM AppPalma.dbo.aTransaccionNovedad n
JOIN AppPalma.dbo.aTransaccion a ON a.empresa = n.empresa AND a.tipo = n.tipo AND a.numero = n.numero
WHERE n.tipo = 'TLC'
  AND ISNULL(a.anulado, 0) = 0
  AND NOT EXISTS (SELECT 1 FROM #eliminadas e WHERE e.empresa = n.empresa AND e.numero = LTRIM(RTRIM(n.numero)))
GROUP BY n.empresa, LTRIM(RTRIM(n.numero)), n.registro;

SELECT t.empresa, t.tipo, t.numero, t.novedad, t.registro, t.registroNovedad,
       l.numeroDoc, LTRIM(RTRIM(t.novedad)) AS novedadGrupo, LTRIM(RTRIM(t.tercero)) AS tercero,
       ISNULL(t.cantidad, 0) AS cantidadAntes, ISNULL(t.valorTotal, 0) AS valorAntes,
       ISNULL(t.precioLabor, 0) AS precioLabor,
       CASE WHEN t.periodo IS NOT NULL OR ISNULL(t.ejecutado, 0) = 1 OR ISNULL(t.saldo, 0) <> ISNULL(t.cantidad, 0)
            THEN 1 ELSE 0 END AS liquidada,
       l.kgLinea, l.filasLinea
INTO #filas
FROM AppPalma.dbo.aTransaccionTercero t
JOIN #lineas l ON l.empresa = t.empresa AND l.numeroDoc = LTRIM(RTRIM(t.numero)) AND l.registro = t.registroNovedad
WHERE t.tipo = 'TLC';

SELECT empresa, numeroDoc, registroNovedad, novedadGrupo,
       MAX(kgLinea) AS kgLinea, MAX(filasLinea) AS filasLinea, SUM(cantidadAntes) AS sumaAntes,
       MAX(liquidada) AS liquidada, COUNT(*) AS trabajadores,
       SUM(CASE WHEN cantidadAntes > 0 THEN CAST(cantidadAntes AS FLOAT) ELSE 0 END) AS sumaPesos
INTO #grupos
FROM #filas
GROUP BY empresa, numeroDoc, registroNovedad, novedadGrupo
HAVING ABS(SUM(cantidadAntes) - MAX(kgLinea)) > 0.01;

;WITH base AS (
    SELECT f.*,
           CAST(ROUND(g.kgLinea * 100, 0) AS BIGINT) AS centavos,
           CASE WHEN g.sumaPesos > 0
                THEN CASE WHEN f.cantidadAntes > 0 THEN CAST(f.cantidadAntes AS FLOAT) ELSE 0 END
                ELSE 1 END AS peso,
           CASE WHEN g.sumaPesos > 0 THEN g.sumaPesos ELSE CAST(g.trabajadores AS FLOAT) END AS totalPesos,
           ROW_NUMBER() OVER (PARTITION BY f.empresa, f.numeroDoc, f.registroNovedad, f.novedadGrupo ORDER BY f.registro) AS idx
    FROM #filas f
    JOIN #grupos g ON g.empresa = f.empresa AND g.numeroDoc = f.numeroDoc
                  AND g.registroNovedad = f.registroNovedad AND g.novedadGrupo = f.novedadGrupo
    WHERE g.liquidada = 0 AND g.filasLinea = 1
), proporcion AS (
    SELECT *, CASE WHEN centavos > 0 THEN CAST(centavos AS FLOAT) * peso / totalPesos ELSE 0 END AS exacto
    FROM base
), piso AS (
    SELECT *, CAST(FLOOR(exacto) AS BIGINT) AS centBase, exacto - FLOOR(exacto) AS resto
    FROM proporcion
), puesto AS (
    SELECT *,
           centavos - SUM(centBase) OVER (PARTITION BY empresa, numeroDoc, registroNovedad, novedadGrupo) AS sobrante,
           ROW_NUMBER() OVER (PARTITION BY empresa, numeroDoc, registroNovedad, novedadGrupo ORDER BY resto DESC, idx ASC) AS orden
    FROM piso
), reparto AS (
    SELECT *, CAST((centBase + CASE WHEN orden <= sobrante THEN 1 ELSE 0 END) / 100.0 AS DECIMAL(18, 2)) AS cantidadDespues
    FROM puesto
)
SELECT empresa, tipo, numero, novedad, registro, registroNovedad, numeroDoc, novedadGrupo, tercero,
       kgLinea, cantidadAntes, valorAntes, precioLabor, cantidadDespues,
       CAST(ROUND(cantidadDespues * CAST(precioLabor AS DECIMAL(19, 6)), 2) AS DECIMAL(18, 2)) AS valorDespues
INTO #calculo
FROM reparto;

SELECT f.empresa, f.numeroDoc, f.registro, f.registroNovedad, f.novedadGrupo, f.tercero, f.kgLinea,
       f.cantidadAntes, ISNULL(c.cantidadDespues, f.cantidadAntes) AS cantidadDespues,
       f.precioLabor, f.valorAntes, ISNULL(c.valorDespues, f.valorAntes) AS valorDespues,
       CASE WHEN c.registro IS NULL THEN 0 ELSE 1 END AS corregida
INTO #resultado
FROM #filas f
LEFT JOIN #calculo c ON c.empresa = f.empresa AND c.numeroDoc = f.numeroDoc AND c.novedad = f.novedad
                    AND c.registro = f.registro AND c.registroNovedad = f.registroNovedad
WHERE EXISTS (SELECT 1 FROM #calculo x WHERE x.empresa = f.empresa AND x.numeroDoc = f.numeroDoc);

SELECT N'1. DETALLE' AS vista, r.numeroDoc AS numero, CONVERT(VARCHAR(10), a.fecha, 23) AS fecha,
       r.registroNovedad AS linea, r.novedadGrupo AS novedad, r.tercero AS nit, ISNULL(ct.nombre, '') AS nombre,
       r.kgLinea, r.cantidadAntes AS kgActual, r.cantidadDespues AS kgCorregido,
       r.precioLabor, r.valorAntes AS valorActual, r.valorDespues AS valorCorregido, r.corregida
FROM #resultado r
JOIN AppPalma.dbo.aTransaccion a ON a.empresa = r.empresa AND a.tipo = 'TLC' AND LTRIM(RTRIM(a.numero)) = r.numeroDoc
OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre
               FROM AppPalma.dbo.cTercero WHERE empresa = r.empresa AND LTRIM(RTRIM(nit)) = r.tercero) ct
ORDER BY r.numeroDoc, r.registroNovedad, r.novedadGrupo, r.registro;

SELECT N'2. TOTAL DOCUMENTO' AS vista, r.numeroDoc AS numero, MAX(CONVERT(VARCHAR(10), a.fecha, 23)) AS fecha,
       MAX(k.kgTiquete) AS kgTiquete, COUNT(*) AS filas, SUM(r.corregida) AS filasCorregidas,
       SUM(r.cantidadAntes) AS kgActual, SUM(r.cantidadDespues) AS kgCorregido,
       SUM(r.valorAntes) AS valorActual, SUM(r.valorDespues) AS valorCorregido
FROM #resultado r
JOIN AppPalma.dbo.aTransaccion a ON a.empresa = r.empresa AND a.tipo = 'TLC' AND LTRIM(RTRIM(a.numero)) = r.numeroDoc
CROSS APPLY (SELECT SUM(l.kgLinea) AS kgTiquete FROM #lineas l WHERE l.empresa = r.empresa AND l.numeroDoc = r.numeroDoc) k
GROUP BY r.empresa, r.numeroDoc
ORDER BY r.numeroDoc;

SELECT N'3. TOTAL TERCERO' AS vista, r.tercero AS nit, ISNULL(MAX(ct.nombre), '') AS nombre,
       COUNT(DISTINCT r.numeroDoc) AS documentos,
       SUM(r.cantidadAntes) AS kgActual, SUM(r.cantidadDespues) AS kgCorregido,
       SUM(r.valorAntes) AS valorActual, SUM(r.valorDespues) AS valorCorregido
FROM #resultado r
OUTER APPLY (SELECT TOP 1 ISNULL(NULLIF(LTRIM(RTRIM(razonSocial)), ''), descripcion) AS nombre
               FROM AppPalma.dbo.cTercero WHERE empresa = r.empresa AND LTRIM(RTRIM(nit)) = r.tercero) ct
GROUP BY r.tercero
ORDER BY SUM(r.valorAntes) - SUM(r.valorDespues) DESC;

SELECT N'4. TOTAL GENERAL' AS vista, COUNT(DISTINCT r.numeroDoc) AS documentos, SUM(r.corregida) AS filasCorregidas,
       (SELECT SUM(l.kgLinea) FROM #lineas l
         WHERE EXISTS (SELECT 1 FROM #calculo x WHERE x.empresa = l.empresa AND x.numeroDoc = l.numeroDoc)) AS kgTiquetes,
       SUM(r.cantidadAntes) AS kgActual, SUM(r.cantidadDespues) AS kgCorregido,
       SUM(r.valorAntes) AS valorActual, SUM(r.valorDespues) AS valorCorregido
FROM #resultado r;

SELECT N'5. EXCLUIDOS' AS vista, g.numeroDoc AS numero, g.registroNovedad AS linea, g.novedadGrupo AS novedad,
       g.kgLinea, g.sumaAntes AS kgTrabajadores, g.trabajadores,
       CASE WHEN g.liquidada = 1 THEN N'Grupo con filas liquidadas' ELSE N'Línea con varias filas en aTransaccionNovedad' END AS motivo
FROM #grupos g
WHERE g.liquidada = 1 OR g.filasLinea <> 1
ORDER BY g.numeroDoc, g.registroNovedad, g.novedadGrupo;

IF @SoloVistaPrevia = 1
BEGIN
    PRINT N'VISTA PREVIA: no se modificó nada. Para probar la corrección use @SoloVistaPrevia = 0 (con @Aplicar = 0 hace ROLLBACK).';
    RETURN;
END

/* ---------- 2. CORRECCIÓN ---------- */

SELECT @Esperadas = COUNT(*) FROM #calculo;

BEGIN TRY

    BEGIN TRAN;

    UPDATE t SET
       t.cantidad   = c.cantidadDespues,
       t.saldo      = c.cantidadDespues,
       t.valorTotal = c.valorDespues
    FROM AppPalma.dbo.aTransaccionTercero t
    JOIN #calculo c ON c.empresa = t.empresa AND c.tipo = t.tipo AND c.numero = t.numero AND c.novedad = t.novedad
                   AND c.registro = t.registro AND c.registroNovedad = t.registroNovedad
    WHERE t.periodo IS NULL
      AND ISNULL(t.ejecutado, 0) = 0
      AND ISNULL(t.saldo, 0) = ISNULL(t.cantidad, 0)
      AND ISNULL(t.cantidad, 0) = c.cantidadAntes;
    SET @Filas = @@ROWCOUNT;

    IF @Filas <> @Esperadas
        RAISERROR (N'Se esperaban %d filas y se actualizaron %d: los datos cambiaron desde la vista previa.', 16, 1, @Esperadas, @Filas);

    INSERT INTO AppPalma.dbo.Auditoria (TablaAfectada, Accion, CambiosRealizados, IdUsuario, FechaRegistro)
    SELECT N'aTransaccion', N'UPDATE',
           N'{"empresa":' + CAST(d.empresa AS NVARCHAR(10))
           + N',"tipo":"TLC","numero":"' + d.numeroDoc
           + N'","motivo":"' + @Motivo
           + N'","trabajadores":['
           + STUFF((SELECT N',{"tercero":"' + REPLACE(REPLACE(c.tercero, N'\', N'\\'), N'"', N'\"')
                         + N'","registroNovedad":' + CAST(c.registroNovedad AS NVARCHAR(10))
                         + N',"novedad":"' + REPLACE(REPLACE(c.novedadGrupo, N'\', N'\\'), N'"', N'\"')
                         + N'","antes":{"cantidad":' + CONVERT(NVARCHAR(40), CAST(c.cantidadAntes AS DECIMAL(18, 2)))
                         + N',"valorTotal":' + CONVERT(NVARCHAR(40), CAST(c.valorAntes AS DECIMAL(18, 2)))
                         + N'},"despues":{"cantidad":' + CONVERT(NVARCHAR(40), c.cantidadDespues)
                         + N',"valorTotal":' + CONVERT(NVARCHAR(40), c.valorDespues)
                         + N'}}'
                      FROM #calculo c
                     WHERE c.empresa = d.empresa AND c.numeroDoc = d.numeroDoc
                     ORDER BY c.registroNovedad, c.registro
                       FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 1, N'')
           + N']}',
           NULL, GETDATE()
    FROM (SELECT DISTINCT empresa, numeroDoc FROM #calculo) d;
    SET @Documentos = @@ROWCOUNT;

    SELECT @Pendientes = COUNT(*)
    FROM (
        SELECT g.empresa, g.numeroDoc, g.registroNovedad, g.novedadGrupo
        FROM (SELECT DISTINCT empresa, numeroDoc, registroNovedad, novedadGrupo, kgLinea FROM #calculo) g
        JOIN AppPalma.dbo.aTransaccionTercero t ON t.empresa = g.empresa AND t.tipo = 'TLC'
                                               AND LTRIM(RTRIM(t.numero)) = g.numeroDoc
                                               AND t.registroNovedad = g.registroNovedad
                                               AND LTRIM(RTRIM(t.novedad)) = g.novedadGrupo
        GROUP BY g.empresa, g.numeroDoc, g.registroNovedad, g.novedadGrupo, g.kgLinea
        HAVING ABS(SUM(ISNULL(t.cantidad, 0)) - g.kgLinea) > 0.01
    ) p;

    SELECT N'RESUMEN' AS vista, @Documentos AS documentosCorregidos, @Filas AS filasCorregidas,
           @Pendientes AS gruposAunDescuadrados;

    IF @Pendientes > 0
        RAISERROR (N'Quedaron %d grupos descuadrados después de la corrección. No se aplicó nada.', 16, 1, @Pendientes);

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'APLICADO: cambios confirmados.';
    END
    ELSE
    BEGIN
        ROLLBACK;
        PRINT N'MODO PRUEBA: no se guardó ningún cambio. Revise el resumen y ejecute con @Aplicar = 1.';
    END

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK;
    SELECT ERROR_NUMBER() AS numero, ERROR_MESSAGE() AS mensaje, ERROR_LINE() AS linea;
    PRINT N'ERROR: se revirtió todo, no quedó ningún cambio.';
END CATCH;
