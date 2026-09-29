-- Procedimiento: spInsertaTablaReplicar
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE proc [dbo].[spInsertaTablaReplicar]
@empresaA varchar(50),
@empresaB varchar(50),
@tabla varchar(50),
@Retorno int output 
as
begin tran sReplicacion 

DECLARE @valores VARCHAR(1000),@ejecutar varchar(5000)

SELECT @valores= COALESCE(@valores + ', ', '') + COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = @tabla

	if exists(SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = @tabla and COLUMN_NAME='empresa')
	begin
			set @ejecutar=  ('insert ' + @tabla + ' ('+@valores+') execute( ' + CHAR( 39 ) + ' select ' + REPLACE(@valores,'empresa',@empresaB) + ' from ' +
			@tabla + ' where empresa= ' + @empresaA + CHAR( 39 )+ ')')
				execute(@ejecutar)
	end
	else
	begin
		
		set @ejecutar=  ('insert ' + @tabla + ' ('+@valores+') execute( ' + CHAR( 39 ) + ' select ' + REPLACE(@valores,'empresa',@empresaB) + ' from ' +
		@tabla + CHAR(39)+ ' )')
		execute(@ejecutar)
	end
	
	if (@@error = 0 ) begin set @Retorno = 0 commit tran sReplicacion end 
else begin set @Retorno = 1 rollback tran sReplicacion end
