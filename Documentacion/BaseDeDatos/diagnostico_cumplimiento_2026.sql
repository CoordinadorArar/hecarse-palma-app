/* ============================================================================
   Diagnóstico de Presupuesto vs Ejecutado 2026 (solo lectura)
   ----------------------------------------------------------------------------
   Separa, por mes, las fuentes del informe para ubicar por qué el ejecutado
   supera tanto al presupuesto. No modifica datos.

   Referencia del Excel (DATOS PRODUCCION 2018 - 2026, Hecarse 2026):
     Real kg:  Ene 996.520 | Feb 1.024.160 | Mar 1.079.510 | Abr 759.540
               May 637.800 | Jun 360.010   | Jul 374.760   | Ago 365.110
     Ppto kg:  Ene 1.082.527 | Feb 1.153.967 | Mar 1.104.724 | Abr 807.190
               May 613.521   | Jun 366.017   | Jul 311.056   | Ago 293.196
   ============================================================================ */

SET NOCOUNT ON;

DECLARE @Empresa INT = 1;
DECLARE @Anio    INT = 2026;

/* 1) Por mes: tiquetes TLC, filas de báscula, peso neto (facturado), kilos de
      labores de cosecha y presupuesto (de finca y por lote). */
;WITH tx AS (
    SELECT a.empresa, a.tipo, a.numero, MONTH(a.fecha) AS mes
      FROM aTransaccion a
     WHERE a.empresa = @Empresa AND a.tipo = 'TLC' AND a.anulado = 0 AND YEAR(a.fecha) = @Anio
), bas AS (
    SELECT t.mes, COUNT(DISTINCT t.numero) AS tiquetes, COUNT(s.numero) AS filasBascula,
           SUM(s.pesoNeto) AS pesoNeto
      FROM tx t
      LEFT JOIN aTransaccionBascula s ON s.empresa = t.empresa AND s.tipo = t.tipo AND s.numero = t.numero
     GROUP BY t.mes
), lab AS (
    SELECT MONTH(ISNULL(c.fechaNovedad, b.fecha)) AS mes,
           SUM(CASE WHEN b.signo = 2 THEN -1 ELSE 1 END * ISNULL(c.cantidad, 0)) AS kilosLabores
      FROM aTransaccionTercero c
      JOIN aNovedad d ON d.codigo = c.novedad AND d.empresa = c.empresa AND d.claseLabor = 2 AND LTRIM(RTRIM(d.uMedida)) = 'KG'
      JOIN aTransaccionNovedad b ON b.empresa = c.empresa AND b.tipo = c.tipo AND b.numero = c.numero AND b.registro = c.registroNovedad
      JOIN aTransaccion a ON a.empresa = b.empresa AND a.tipo = b.tipo AND a.numero = b.numero AND a.anulado = 0
     WHERE c.empresa = @Empresa AND YEAR(ISNULL(c.fechaNovedad, b.fecha)) = @Anio
     GROUP BY MONTH(ISNULL(c.fechaNovedad, b.fecha))
), ppto AS (
    SELECT mes,
           SUM(CASE WHEN LTRIM(RTRIM(lote)) = '' THEN kilos ELSE 0 END) AS pptoFinca,
           SUM(CASE WHEN LTRIM(RTRIM(lote)) <> '' THEN kilos ELSE 0 END) AS pptoLotes
      FROM dbo.aPresupuestoProduccion
     WHERE empresa = @Empresa AND anio = @Anio
     GROUP BY mes
)
SELECT m.mes, b.tiquetes, b.filasBascula, b.pesoNeto AS facturado, l.kilosLabores,
       p.pptoFinca, p.pptoLotes, ISNULL(p.pptoFinca, 0) + ISNULL(p.pptoLotes, 0) AS pptoTotal,
       CAST(b.pesoNeto / NULLIF(ISNULL(p.pptoFinca, 0) + ISNULL(p.pptoLotes, 0), 0) * 100 AS DECIMAL(9, 1)) AS pctCumplimiento
  FROM (VALUES (1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12)) m(mes)
  LEFT JOIN bas b ON b.mes = m.mes
  LEFT JOIN lab l ON l.mes = m.mes
  LEFT JOIN ppto p ON p.mes = m.mes
 ORDER BY m.mes;

/* 2) Tiquetes con más de una fila de báscula (duplican peso si no corresponde). */
SELECT TOP 50 a.numero, CONVERT(varchar(10), a.fecha, 23) AS fecha, COUNT(*) AS filasBascula, SUM(s.pesoNeto) AS pesoNeto
  FROM aTransaccion a
  JOIN aTransaccionBascula s ON s.empresa = a.empresa AND s.tipo = a.tipo AND s.numero = a.numero
 WHERE a.empresa = @Empresa AND a.tipo = 'TLC' AND a.anulado = 0 AND YEAR(a.fecha) = @Anio
 GROUP BY a.numero, a.fecha
HAVING COUNT(*) > 1
 ORDER BY COUNT(*) DESC, a.numero;

/* 3) Facturado por finca de la primera novedad (incluye tiquetes sin labores). */
SELECT ISNULL(n.finca, '(sin novedad)') AS finca, COUNT(DISTINCT a.numero) AS tiquetes, SUM(s.pesoNeto) AS pesoNeto
  FROM aTransaccion a
  JOIN aTransaccionBascula s ON s.empresa = a.empresa AND s.tipo = a.tipo AND s.numero = a.numero
 OUTER APPLY (SELECT TOP 1 LTRIM(RTRIM(x.finca)) AS finca FROM aTransaccionNovedad x
               WHERE x.empresa = a.empresa AND x.tipo = a.tipo AND x.numero = a.numero ORDER BY x.registro) n
 WHERE a.empresa = @Empresa AND a.tipo = 'TLC' AND a.anulado = 0 AND YEAR(a.fecha) = @Anio
 GROUP BY n.finca
 ORDER BY n.finca;

/* 4) Presupuesto 2026 por finca y nivel (finca '' / lotes). */
SELECT LTRIM(RTRIM(finca)) AS finca,
       SUM(CASE WHEN LTRIM(RTRIM(lote)) = '' THEN kilos ELSE 0 END) AS pptoFinca,
       SUM(CASE WHEN LTRIM(RTRIM(lote)) <> '' THEN kilos ELSE 0 END) AS pptoLotes,
       COUNT(DISTINCT CASE WHEN LTRIM(RTRIM(lote)) <> '' THEN mes END) AS mesesConLotes
  FROM dbo.aPresupuestoProduccion
 WHERE empresa = @Empresa AND anio = @Anio
 GROUP BY LTRIM(RTRIM(finca))
 ORDER BY finca;
