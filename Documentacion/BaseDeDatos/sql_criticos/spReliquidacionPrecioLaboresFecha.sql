-- Procedimiento: spReliquidacionPrecioLaboresFecha
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE proc spReliquidacionPrecioLaboresFecha
@empresa int,
@fechaInicial date,
@fechaFinal date,
@usuario varchar(50),
@Retorno int output
AS begin tran aTransaccion

insert aLogReliquidacion
select @empresa,@fechaInicial,@fechaFinal,@usuario,GETDATE()

	update aTransaccionTercero set
	precioLabor = dbo.fRetornaPrecioLaboresTercero(c.empresa,c.novedad,c.año,c.tercero,b.fecha,c.finca,c.seccion,c.lote,c.contratista),
	valorTotal= dbo.fRetornaPrecioLaboresTercero(c.empresa,c.novedad,c.año,c.tercero,b.fecha,c.finca,c.seccion,c.lote,c.contratista)*c.cantidad
	from aTransaccion a
	join aTransaccionNovedad  b on b.numero=a.numero and b.empresa=a.empresa and b.tipo=a.tipo
	join aTransaccionTercero  c on c.numero=b.numero and c.tipo=b.tipo and c.empresa=b.empresa and c.registroNovedad=b.registro
	join cTercero d on d.id=c.tercero and d.empresa=c.empresa
	where a.anulado=0 and c.ejecutado=0 and a.fecha between @fechaInicial and @fechaFinal and a.empresa=@empresa 

if (@@error = 0 ) begin set @Retorno = 0 commit tran aTransaccion end 
else begin set @Retorno = 1 rollback tran aTransaccion end

