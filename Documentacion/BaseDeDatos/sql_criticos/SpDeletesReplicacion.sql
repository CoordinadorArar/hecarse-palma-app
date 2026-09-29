-- Procedimiento: SpDeletesReplicacion
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE PROCEDURE SpDeletesReplicacion @id int,@Retorno int output  AS begin tran sReplicacion delete sReplicacion where id = @id if (@@error = 0 ) begin set @Retorno = 0 commit tran sReplicacion end else begin set @Retorno = 1 rollback tran sReplicacion end