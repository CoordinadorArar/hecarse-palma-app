IF OBJECT_ID('dbo.aTransaccionEliminada', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.aTransaccionEliminada (
        empresa          INT           NOT NULL,
        tipo             VARCHAR(50)   NOT NULL,
        numero           VARCHAR(50)   NOT NULL,
        fechaEliminado   DATETIME      NOT NULL CONSTRAINT DF_aTransaccionEliminada_fecha DEFAULT (GETDATE()),
        usuarioEliminado VARCHAR(50)   NOT NULL,
        usu_id           INT           NULL,
        motivo           VARCHAR(250)  NULL,
        CONSTRAINT PK_aTransaccionEliminada PRIMARY KEY (empresa, tipo, numero)
    );
END
