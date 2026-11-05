/* ============================================================================
   Registros de báscula (API api/receive-data-bascula)
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Crea, si no existe, la tabla dbo.bBasculaRegistro con las filas de la
     tabla "registros" del software de la báscula, enviadas por lotes desde
     el equipo de la báscula al endpoint POST api/receive-data-bascula.
       Mismas columnas y nombres que la tabla origen (numero ... ctda_eje),
       más las columnas de control lote y fechaRecepcion, y los centros de
       operación coEquipo (equipo que envía, codigoFinca del JSON) y coFinca
       (finca dueña del peso).
       PK (coEquipo, numero).

   USO EN EL APLICATIVO
   --------------------
     El endpoint inserta solo las filas cuyo (coEquipo, numero) no exista
     todavía; las existentes se excluyen, por lo que reenviar un lote no
     duplica datos.

   IDEMPOTENTE: se puede ejecutar varias veces.
   Ejecutar desde SSMS o con "sqlcmd -f 65001 -i crear_bascula_registros.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF OBJECT_ID('dbo.bBasculaRegistro', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.bBasculaRegistro (
        numero         INT            NOT NULL,
        placa          VARCHAR(20)    NULL,
        cliente        VARCHAR(100)   NULL,
        nit            VARCHAR(30)    NULL,
        direccion      VARCHAR(200)   NULL,
        telefono       VARCHAR(30)    NULL,
        conductor      VARCHAR(100)   NULL,
        cedula         VARCHAR(20)    NULL,
        origen         VARCHAR(100)   NULL,
        destino        VARCHAR(100)   NULL,
        transportista  VARCHAR(50)    NULL,
        observaciones  VARCHAR(500)   NULL,
        producto       VARCHAR(100)   NULL,
        codigo         VARCHAR(10)    NULL,
        proceso        INT            NULL,
        f_entrada      DATETIME       NULL,
        f_salida       DATETIME       NULL,
        tipo           INT            NULL,
        pesada1        DECIMAL(12, 2) NULL,
        pesada2        DECIMAL(12, 2) NULL,
        peso_neto      DECIMAL(12, 2) NULL,
        estado         INT            NULL,
        usuario        VARCHAR(50)    NULL,
        n_registro     INT            NULL,
        costo          DECIMAL(18, 2) NULL,
        camion         VARCHAR(50)    NULL,
        eje1           VARCHAR(10)    NULL,
        eje2           VARCHAR(10)    NULL,
        eje3           VARCHAR(10)    NULL,
        eje4           VARCHAR(10)    NULL,
        eje5           VARCHAR(10)    NULL,
        eje6           VARCHAR(10)    NULL,
        eje7           VARCHAR(10)    NULL,
        eje8           VARCHAR(10)    NULL,
        ctda_eje       VARCHAR(10)    NULL,
        lote           INT            NULL,
        fechaRecepcion DATETIME       NOT NULL CONSTRAINT DF_bBasculaRegistro_fechaRecepcion DEFAULT (GETDATE()),
        coEquipo       VARCHAR(3)     NOT NULL,
        coFinca        VARCHAR(3)     NULL,
        CONSTRAINT PK_bBasculaRegistro PRIMARY KEY (coEquipo, numero)
    );

    PRINT N'Tabla dbo.bBasculaRegistro creada.';
END;

/* ----------------------------------------------------------------------------
   REVERSIÓN MANUAL. Descomentar y ejecutar.
   ----------------------------------------------------------------------------
IF OBJECT_ID('dbo.bBasculaRegistro', 'U') IS NOT NULL
    DROP TABLE dbo.bBasculaRegistro;
---------------------------------------------------------------------------- */
