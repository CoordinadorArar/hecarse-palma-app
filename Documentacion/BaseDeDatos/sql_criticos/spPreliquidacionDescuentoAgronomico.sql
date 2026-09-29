-- Procedimiento: spPreliquidacionDescuentoAgronomico
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura


CREATE proc [dbo].[spPreliquidacionDescuentoAgronomico]
@empresa int,
@fecha date,
@fi date,
@ff date,
@observacion varchar(2550),
@usuario varchar(50),
@remision varchar(50),
@retorno int output
as

declare @finca varchar(50),@novedadBase varchar(50), @novedadAplicar varchar(50),@mp bit,@porcentaje float,@mc bit,
@cantidad float,@mv bit,@valor float,@fechaInicio date,@lote varchar(50), @seccion varchar(50),@mSeccion bit, @mLote bit,@retencion float, @novedadFestiva varchar(50)

set @retorno=0


if not exists(select finca,novedadBase,novedadAplicar,mPorcentaje,porcentaje,mCantidad, cantidad,mValor,valor,fechaInicio from aParametrosDescuento
where empresa=@empresa and activo=1)
begin
	set @retorno=1
	return
end

delete tmpLiquidacionPepa where empresa=@empresa

declare CurLiquidacion insensitive cursor for
select finca,novedadBase,novedadAplicar,mPorcentaje,porcentaje,mCantidad, cantidad,mValor,valor,fechaInicio, mSeccion,seccion,mLote, lote,retencion, novedadFestiva from aParametrosDescuento
where empresa=1 and activo=1
open CurLiquidacion			
fetch CurLiquidacion into @finca,@novedadBase, @novedadAplicar,@mp,@porcentaje,@mc,@cantidad,@mv,@valor,@fechaInicio,@mSeccion,@seccion,@mLote,@lote,@retencion, @novedadFestiva
while( @@fetch_status = 0 )
begin
	if @mp =1
	begin
		if @mLote=1 and @mSeccion=1
		begin
			insert tmpLiquidacionPepa
			select c.empresa,c.año,month(a.fecha),@novedadAplicar novedad,c.registro, c.finca,c.seccion,
			c.lote,c.tercero, ROUND((c.cantidad*(@porcentaje/100)),0) cantidad, 0 jornal, 0 slado, 0 ejecutado, d.cuadrilla,
			e.precioDestajo-round((e.precioDestajo * (@retencion)/100),2) ,
			ROUND((c.cantidad*(@porcentaje/100)),0)* (e.precioDestajo-round((e.precioDestajo * (@retencion)/100),2)) valorTotal,			
			c.ccosto,c.contrato,c.periodo,c.contratista,c.proveedor,@fi,@ff,a.fecha,
			@novedadBase,
			 ROUND((c.cantidad*(@porcentaje/100)),0) cantidaBase,
			c.precioLabor precioBase,
			c.precioLabor * ROUND((c.cantidad*(@porcentaje/100)),0),
			a.tipo, a.numero
			 from aTransaccion a
			join aTransaccionTercero c on c.empresa=a.empresa and c.tipo=a.tipo and c.numero=a.numero 
			left join nCuadrillaFuncionario d on d.funcionario=c.tercero and d.empresa=c.empresa
			join aNovedadLotePrecio e on e.novedad= @novedadAplicar and e.año=c.año and e.empresa=c.empresa
			where a.anulado=0 and a.fecha>=@fechaInicio and c.novedad=@novedadBase and c.finca=@finca and c.seccion=@seccion and c.lote=@lote and tercero<>1981
			and a.fecha between @fi and @ff and c.contratista=0 and a.anulado=0

			
			insert tmpLiquidacionPepa
			select c.empresa,c.año,month(a.fecha),@novedadFestiva novedad,c.registro, c.finca,c.seccion,
			c.lote,c.tercero, ROUND(c.cantidad,0) cantidad, 0 jornal, 0 slado, 0 ejecutado, d.cuadrilla,
			round((e.precioDestajo),2) ,
			ROUND(c.cantidad,0)* (e.precioDestajo) valorTotal,			
			c.ccosto,c.contrato,c.periodo,c.contratista,c.proveedor,@fi,@ff,aa.fecha,
			@novedadFestiva,
			 ROUND(c.cantidad,0) cantidaBase,
			c.precioLabor precioBase,
			c.precioLabor * ROUND(c.cantidad,0),
			a.tipo, a.numero
			 from aTransaccion a
				join atransaccionnovedad aa on a.tipo=aa.tipo and a.numero=aa.numero and aa.empresa=a.empresa 
			join aTransaccionTercero c on c.empresa=a.empresa and c.tipo=a.tipo and c.numero=a.numero and aa.registro=c.registronovedad
			left join nCuadrillaFuncionario d on d.funcionario=c.tercero and d.empresa=c.empresa
			join aNovedadLotePrecio e on e.novedad= @novedadFestiva and e.año=c.año and e.empresa=c.empresa
			where a.anulado=0 and aa.fecha>=@fechaInicio and c.novedad=@novedadBase and c.finca=@finca and c.seccion=@seccion and c.lote=@lote  and tercero<>1981
			and aa.fecha between @fi and @ff and c.contratista=0
			and aa.fecha in (select fecha from nFestivo where empresa=@empresa) 
		end

		if  @mSeccion=1 and @mLote=0
		begin
			insert tmpLiquidacionPepa
			select c.empresa,c.año,month(a.fecha),@novedadAplicar novedad,c.registro, c.finca,c.seccion,
			c.lote,c.tercero, ROUND((c.cantidad*(@porcentaje/100)),0) cantidad, 0 jornal, 0 slado, 0 ejecutado, d.cuadrilla,
			e.precioDestajo-round((e.precioDestajo * (@retencion)/100),2) ,
			ROUND((c.cantidad*(@porcentaje/100)),0)* (e.precioDestajo-round((e.precioDestajo * (@retencion)/100),2)) valorTotal,
			c.ccosto,c.contrato,c.periodo,c.contratista,c.proveedor,@fi,@ff,a.fecha,
					@novedadBase,
			 ROUND((c.cantidad*(@porcentaje/100)),0) cantidaBase,
			c.precioLabor precioBase,
			c.precioLabor * ROUND((c.cantidad*(@porcentaje/100)),0),
			a.tipo, a.numero
			 from aTransaccion a
		    join aTransaccionTercero c on c.empresa=a.empresa and c.tipo=a.tipo and c.numero=a.numero 
			left join nCuadrillaFuncionario d on d.funcionario=c.tercero and d.empresa=c.empresa
			join aNovedadLotePrecio e on e.novedad= @novedadAplicar and e.año=c.año and e.empresa=c.empresa
			where a.anulado=0 and a.fecha>=@fechaInicio and c.novedad=@novedadBase and c.finca=@finca and c.seccion=@seccion and tercero<>1981
			and a.fecha between @fi and @ff and c.contratista=0

			
			insert tmpLiquidacionPepa
			select c.empresa,c.año,month(a.fecha),@novedadFestiva novedad,c.registro, c.finca,c.seccion,
			c.lote,c.tercero, ROUND(c.cantidad,0) cantidad, 0 jornal, 0 slado, 0 ejecutado, d.cuadrilla,
			round((e.precioDestajo),2) ,
			ROUND(c.cantidad,0)* (e.precioDestajo) valorTotal,			
			c.ccosto,c.contrato,c.periodo,c.contratista,c.proveedor,@fi,@ff,aa.fecha,
			@novedadFestiva,
			 ROUND(c.cantidad,0) cantidaBase,
			c.precioLabor precioBase,
			c.precioLabor * ROUND(c.cantidad,0),
			a.tipo, a.numero
			 from aTransaccion a
				join atransaccionnovedad aa on a.tipo=aa.tipo and a.numero=aa.numero and aa.empresa=a.empresa 
			join aTransaccionTercero c on c.empresa=a.empresa and c.tipo=a.tipo and c.numero=a.numero and aa.registro=c.registronovedad
			left join nCuadrillaFuncionario d on d.funcionario=c.tercero and d.empresa=c.empresa
			join aNovedadLotePrecio e on e.novedad= @novedadFestiva and e.año=c.año and e.empresa=c.empresa
			where a.anulado=0 and aa.fecha>=@fechaInicio and c.novedad=@novedadBase and c.finca=@finca and c.seccion=@seccion  and tercero<>1981
			and aa.fecha between @fi and @ff and c.contratista=0
			and aa.fecha in (select fecha from nFestivo where empresa=@empresa)
		end

		if @mLote=0 and @mSeccion=0
		begin

			insert tmpLiquidacionPepa
			select c.empresa,c.año,month(a.fecha),@novedadAplicar novedad,c.registro, c.finca,c.seccion,
			c.lote,c.tercero, ROUND((c.cantidad*(@porcentaje/100)),0) cantidad, 0 jornal, 0 slado, 0 ejecutado, d.cuadrilla,
			e.precioDestajo-round((e.precioDestajo * (@retencion)/100),2) ,
			ROUND((c.cantidad*(@porcentaje/100)),0)* (e.precioDestajo-round((e.precioDestajo * (@retencion)/100),2)) valorTotal,
			c.ccosto,c.contrato,c.periodo,c.contratista,c.proveedor,@fi,@ff,a.fecha,
			@novedadBase,
			 ROUND((c.cantidad*(@porcentaje/100)),0) cantidaBase,
			c.precioLabor precioBase,
			c.precioLabor * ROUND((c.cantidad*(@porcentaje/100)),0),
			a.tipo, a.numero 
			from aTransaccion a
			join aTransaccionTercero c on c.empresa=a.empresa and c.tipo=a.tipo and c.numero=a.numero 
			left join nCuadrillaFuncionario d on d.funcionario=c.tercero and d.empresa=c.empresa
			join aNovedadLotePrecio e on e.novedad= @novedadAplicar and e.año=c.año and e.empresa=c.empresa
			where a.anulado=0 and a.fecha>=@fechaInicio and c.novedad=@novedadBase and c.finca=@finca  and tercero<>1981
			and a.fecha between @fi and @ff and c.contratista=0

			insert tmpLiquidacionPepa
			select c.empresa,c.año,month(a.fecha),@novedadFestiva novedad,c.registro, c.finca,c.seccion,
			c.lote,c.tercero, ROUND(c.cantidad,0) cantidad, 0 jornal, 0 slado, 0 ejecutado, d.cuadrilla,
			round((e.precioDestajo),2) ,
			ROUND(c.cantidad,0)* (e.precioDestajo) valorTotal,			
			c.ccosto,c.contrato,c.periodo,c.contratista,c.proveedor,@fi,@ff,aa.fecha,
			@novedadFestiva,
			 ROUND(c.cantidad,0) cantidaBase,
			c.precioLabor precioBase,
			c.precioLabor * ROUND(c.cantidad,0),
			a.tipo, a.numero
			from aTransaccion a
			join atransaccionnovedad aa on a.tipo=aa.tipo and a.numero=aa.numero and aa.empresa=a.empresa 
			join aTransaccionTercero c on c.empresa=a.empresa and c.tipo=a.tipo and c.numero=a.numero and aa.registro=c.registronovedad
			left join nCuadrillaFuncionario d on d.funcionario=c.tercero and d.empresa=c.empresa
			join aNovedadLotePrecio e on e.novedad= @novedadFestiva and e.año=c.año and e.empresa=c.empresa
			where a.anulado=0 and aa.fecha>=@fechaInicio and c.novedad=@novedadBase and c.finca=@finca and tercero<>1981
			and aa.fecha between @fi and @ff and c.contratista=0
			and aa.fecha in (select fecha from nFestivo where empresa=@empresa)

		end
	end
	else 
	begin
			if @mLote=1 and @mSeccion=1
			begin
				insert tmpLiquidacionPepa
			select c.empresa,c.año,month(a.fecha),@novedadFestiva novedad,c.registro, c.finca,c.seccion,
			c.lote,c.tercero, ROUND(c.cantidad,0) cantidad, 0 jornal, 0 slado, 0 ejecutado, d.cuadrilla,
			round((e.precioDestajo),2) ,
			ROUND(c.cantidad,0)* (e.precioDestajo) valorTotal,			
			c.ccosto,c.contrato,c.periodo,c.contratista,c.proveedor,@fi,@ff,aa.fecha,
			@novedadFestiva,
			 ROUND(c.cantidad,0) cantidaBase,
			c.precioLabor precioBase,
			c.precioLabor * ROUND(c.cantidad,0),
			a.tipo, a.numero
			 from aTransaccion a
			join atransaccionnovedad aa on a.tipo=aa.tipo and a.numero=aa.numero and aa.empresa=a.empresa 
			join aTransaccionTercero c on c.empresa=a.empresa and c.tipo=a.tipo and c.numero=a.numero and aa.registro=c.registronovedad
			left join nCuadrillaFuncionario d on d.funcionario=c.tercero and d.empresa=c.empresa
			join aNovedadLotePrecio e on e.novedad= @novedadFestiva and e.año=c.año and e.empresa=c.empresa
			where a.anulado=0 and aa.fecha>=@fechaInicio and c.novedad=@novedadBase and c.finca=@finca and c.seccion=@seccion and c.lote=@lote and tercero<>1981
			and aa.fecha between @fi and @ff and c.contratista=0 and a.anulado=0
			and aa.fecha in (select fecha from nFestivo where empresa=@empresa)
			end
			if @mLote=0 and @mSeccion=1
			begin
					insert tmpLiquidacionPepa
			select c.empresa,c.año,month(a.fecha),@novedadFestiva novedad,c.registro, c.finca,c.seccion,
			c.lote,c.tercero, ROUND(c.cantidad,0) cantidad, 0 jornal, 0 slado, 0 ejecutado, d.cuadrilla,
			round((e.precioDestajo),2) ,
			ROUND(c.cantidad,0)* (e.precioDestajo) valorTotal,			
			c.ccosto,c.contrato,c.periodo,c.contratista,c.proveedor,@fi,@ff,aa.fecha,
			@novedadFestiva,
			 ROUND(c.cantidad,0) cantidaBase,
			c.precioLabor precioBase,
			c.precioLabor * ROUND(c.cantidad,0),
			a.tipo, a.numero
			 from aTransaccion a
			join atransaccionnovedad aa on a.tipo=aa.tipo and a.numero=aa.numero and aa.empresa=a.empresa 
			join aTransaccionTercero c on c.empresa=a.empresa and c.tipo=a.tipo and c.numero=a.numero and aa.registro=c.registronovedad
			left join nCuadrillaFuncionario d on d.funcionario=c.tercero and d.empresa=c.empresa
			join aNovedadLotePrecio e on e.novedad= @novedadFestiva and e.año=c.año and e.empresa=c.empresa
			where a.anulado=0 and aa.fecha>=@fechaInicio and c.novedad=@novedadBase and c.finca=@finca and c.seccion=@seccion and tercero<>1981
			and aa.fecha between @fi and @ff and c.contratista=0 and a.anulado=0
			and aa.fecha in (select fecha from nFestivo where empresa=@empresa)
			end
			if @mLote=0 and @mSeccion=0
			begin
			insert tmpLiquidacionPepa
			select c.empresa,c.año,month(a.fecha),@novedadFestiva novedad,c.registro, c.finca,c.seccion,
			c.lote,c.tercero, ROUND(c.cantidad,0) cantidad, 0 jornal, 0 slado, 0 ejecutado, d.cuadrilla,
			round((e.precioDestajo),2) ,
			ROUND(c.cantidad,0)* (e.precioDestajo) valorTotal,			
			c.ccosto,c.contrato,c.periodo,c.contratista,c.proveedor,@fi,@ff,aa.fecha,
			@novedadFestiva,
			 ROUND(c.cantidad,0) cantidaBase,
			c.precioLabor precioBase,
			c.precioLabor * ROUND(c.cantidad,0),
			a.tipo, a.numero
			 from aTransaccion a
			join atransaccionnovedad aa on a.tipo=aa.tipo and a.numero=aa.numero and aa.empresa=a.empresa 
			join aTransaccionTercero c on c.empresa=a.empresa and c.tipo=a.tipo and c.numero=a.numero and aa.registro=c.registronovedad
			left join nCuadrillaFuncionario d on d.funcionario=c.tercero and d.empresa=c.empresa
			join aNovedadLotePrecio e on e.novedad= @novedadFestiva and e.año=c.año and e.empresa=c.empresa
			where a.anulado=0 and aa.fecha>=@fechaInicio and c.novedad=@novedadBase and c.finca=@finca and tercero<>1981
			and aa.fecha between @fi and @ff and c.contratista=0 and a.anulado=0
			and aa.fecha in (select fecha from nFestivo where empresa=@empresa)
			end
	end



fetch CurLiquidacion into  @finca,@novedadBase, @novedadAplicar,@mp,@porcentaje,@mc,@cantidad,@mv,@valor,@fechaInicio,@mSeccion,@seccion,@mLote,@lote,@retencion, @novedadFestiva
end
close CurLiquidacion
deallocate CurLiquidacion 

update tmpLiquidacionPepa set
valorTotal = cantidad * precioBase

