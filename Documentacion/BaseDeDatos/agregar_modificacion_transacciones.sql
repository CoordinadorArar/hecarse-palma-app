/* ============================================================================
   Columnas de modificación en aTransaccion
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     1) Agrega a aTransaccion las columnas NULLABLE:
          - fechaModificacion   DATETIME     NULL
          - usuarioModificacion VARCHAR(50)  NULL
        No afectan las filas existentes ni los INSERT actuales.
     2) Backfill desde Auditoria: para cada transacción con al menos un UPDATE
        registrado (TablaAfectada = 'aTransaccion', Accion = 'UPDATE') toma el
        ÚLTIMO (mayor Id) y llena fechaModificacion = Auditoria.FechaRegistro y
        usuarioModificacion = Usuarios.Usuario del IdUsuario (el mismo valor
        que la sesión guarda en usu_login y que se escribe en usuarioRegistro;
        'WEB' si no hay usuario). Solo llena filas con fechaModificacion NULL.
        La llave se lee del JSON CambiosRealizados con JSON_VALUE ($.empresa,
        $.tipo, $.numero); JSON_VALUE funciona con el nivel de compatibilidad
        actual de la base (100) en SQL Server 2017.

   La aplicación detecta las columnas con COL_LENGTH: mientras el script no se
   ejecute, la edición no las llena y la consulta las devuelve vacías.

   IDEMPOTENTE: se puede ejecutar varias veces.

   Ejecutar contra la base de datos AppPalma (SQL Server).
   ============================================================================ */

IF COL_LENGTH('dbo.aTransaccion', 'fechaModificacion') IS NULL
    ALTER TABLE dbo.aTransaccion ADD fechaModificacion DATETIME NULL;
GO

IF COL_LENGTH('dbo.aTransaccion', 'usuarioModificacion') IS NULL
    ALTER TABLE dbo.aTransaccion ADD usuarioModificacion VARCHAR(50) NULL;
GO

;WITH ultima AS (
    SELECT TRY_CAST(JSON_VALUE(a.CambiosRealizados, '$.empresa') AS INT) AS empresa,
           JSON_VALUE(a.CambiosRealizados, '$.tipo')                 AS tipo,
           JSON_VALUE(a.CambiosRealizados, '$.numero')               AS numero,
           a.FechaRegistro,
           a.IdUsuario,
           ROW_NUMBER() OVER (PARTITION BY JSON_VALUE(a.CambiosRealizados, '$.empresa'),
                                           JSON_VALUE(a.CambiosRealizados, '$.tipo'),
                                           JSON_VALUE(a.CambiosRealizados, '$.numero')
                                  ORDER BY a.Id DESC) AS orden
      FROM dbo.Auditoria a
     WHERE a.TablaAfectada = 'aTransaccion'
       AND a.Accion = 'UPDATE'
       AND ISJSON(a.CambiosRealizados) = 1
)
UPDATE t
   SET t.fechaModificacion   = u.FechaRegistro,
       t.usuarioModificacion = LEFT(ISNULL(NULLIF(LTRIM(RTRIM(us.Usuario)), ''), 'WEB'), 50)
  FROM dbo.aTransaccion t
  JOIN ultima u
    ON u.orden = 1
   AND u.empresa = t.empresa
   AND u.tipo = LTRIM(RTRIM(t.tipo))
   AND u.numero = LTRIM(RTRIM(t.numero))
  LEFT JOIN dbo.Usuarios us ON us.Id = u.IdUsuario
 WHERE t.fechaModificacion IS NULL;
GO
