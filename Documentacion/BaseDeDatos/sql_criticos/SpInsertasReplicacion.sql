-- Procedimiento: SpInsertasReplicacion
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura


CREATE PROCEDURE [dbo].[SpInsertasReplicacion] @empresaA int,@empresaB int,@noRegistro int,@fechaRegistro datetime,@tabla varchar(50),
@usuario varchar(50),@Retorno int output  AS begin tran sReplicacion 
insert sReplicacion( empresaA,empresaB,noRegistro,fechaRegistro,tabla,usuario ) 
select @empresaA,@empresaB,@noRegistro,@fechaRegistro,@tabla,@usuario 
if (@@error = 0 ) begin set @Retorno = 0 commit tran sReplicacion end 
else begin set @Retorno = 1 rollback tran sReplicacion end
