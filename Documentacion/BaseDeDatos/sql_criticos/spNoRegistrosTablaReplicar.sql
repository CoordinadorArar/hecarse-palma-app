-- Procedimiento: spNoRegistrosTablaReplicar
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE proc spNoRegistrosTablaReplicar
@empresaA varchar(50),
@tabla varchar(50)
as

if exists(SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'gEmpresa' and COLUMN_NAME='empresa')
	execute( 'select count(*) from ' + @tabla + ' where empresa=' + @empresaA )
else
	execute( 'select count(*) from ' + @tabla  )
