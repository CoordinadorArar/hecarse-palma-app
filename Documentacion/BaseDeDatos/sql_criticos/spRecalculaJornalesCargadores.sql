-- Procedimiento: spRecalculaJornalesCargadores
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE proc [dbo].[spRecalculaJornalesCargadores]
@empresa int,
@tercero int,
@fechaInicial date,
@fechaFinal date
as

declare @fecha date, @jornal float,@noRegistro float, @claseLabor int=3,  @cantidadLabor decimal(18,3) =0  , @umedidajor varchar(50)

select @umedidajor=umedidajornal from nParametrosGeneral where empresa=@empresa


		update aTransaccionTercero
		set jornales = 0
		from (select a.tipo tipoT, a.numero numeroT, c.tercero terceroT, a.empresa empresaT, c.registro registroT, c.novedad novedadT	from dbo.aTransaccion AS a INNER JOIN
		dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
		dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro 
		join dbo.aNovedad as d on d.codigo= c.novedad and c.empresa=d.empresa
		where a.empresa=@empresa  and d.claseLabor=@claseLabor and c.tercero=@tercero and convert(date,a.fecha) between  @fechaInicial and @fechaFinal and  a.anulado=0 ) z
		where z.tipoT=tipo and z.numeroT=numero and z.empresaT=empresa   and tercero=terceroT and registro=registroT
		and novedad=novedadT

		update aTransaccionTercero
		set jornales = cantidadTercero
		from (select a.tipo tipoT, a.numero numeroT, c.tercero terceroT, a.empresa empresaT, c.registro registroT, c.novedad novedadT, c.cantidad cantidadTercero from dbo.aTransaccion AS a INNER JOIN
		dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
		dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro 
		join dbo.aNovedad as d on d.codigo= c.novedad and c.empresa=d.empresa
		where a.empresa=@empresa  and d.claseLabor<>@claseLabor and c.tercero=@tercero 
		and convert(date,a.fecha) between  @fechaInicial and @fechaFinal and  a.anulado=0  and d.uMedida=@umedidajor) z
		where z.tipoT=tipo and z.numeroT=numero and z.empresaT=empresa   and tercero=terceroT and registro=registroT
		and novedad=novedadT


declare @noPrestamo varchar(50)
declare curPrestamo insensitive cursor for
select distinct c.tercero, b.fecha from aTransaccion a
join aTransaccionNovedad b on b.numero=a.numero and b.tipo=a.tipo and b.empresa=a.empresa
join aTransaccionTercero c on c.numero=b.numero and c.tipo=b.tipo and c.empresa=b.empresa and c.registroNovedad=b.registro
join aNovedad d on d.codigo=c.novedad and d.empresa=c.empresa
where  tercero=@tercero and a.fecha between @fechaInicial and @fechaFinal and a.anulado=0  and d.claseLabor = @claseLabor
and a.empresa=@empresa
group by c.tercero,b.fecha

	open curPrestamo                  
	fetch curPrestamo into @tercero,@fecha
	while( @@fetch_status = 0 )
	begin  
                                 
								 
		if (select  sum(c.jornales) from aTransaccion a
		join aTransaccionNovedad b on b.numero=a.numero and b.tipo=a.tipo and b.empresa=a.empresa
		join aTransaccionTercero c on c.numero=b.numero and c.tipo=b.tipo and c.empresa=b.empresa and c.registroNovedad=b.registro
		join aNovedad d on d.codigo=c.novedad and d.empresa=c.empresa
		where  tercero=@tercero  and a.anulado=0  and b.fecha=@fecha and a.empresa=@empresa group by tercero)<=0
		begin
			set @cantidadLabor=0
			if exists (select * from vTransaccionAgronomico a
			where fechaLabor = @fecha and a.codEmpresa=@empresa
			and a.idTercero = @tercero and claseLabor=@claseLabor	and a.anulado=0)
			begin
				if exists (	select * from vTransaccionAgronomico a
				where fechaLabor = @fecha and a.codEmpresa=@empresa	and a.idTercero = @tercero	and a.claseLabor<>@claseLabor	and a.anulado=0)
				begin
					
					set @cantidadLabor = (select SUM(jornalLabor) from 
					vTransaccionAgronomico a where fechaLabor = @fecha and a.codEmpresa=@empresa
					and a.idTercero = @tercero and a.claseLabor<>@claseLabor and a.anulado=0 )		
					
					if 1 - @cantidadLabor > 0
					begin
																
						update aTransaccionTercero
						set jornales = 1-@cantidadLabor
						from
							(
							select top 1 c.tipo tipoTra, c.numero numeroTra, c.novedad conceptoAgro, 
							c.registro registroTran, c.registroNovedad registroConcepto,c.año añoLabor, c.mes mesLabor  from dbo.aTransaccion AS a INNER JOIN
							dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
							dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro 
							join dbo.aNovedad as d on d.codigo= c.novedad and c.empresa=d.empresa
							where c.tercero=@tercero and a.empresa=@empresa  and d.claseLabor=@claseLabor	
							and convert(date,b.fecha) = @fecha
							and  a.anulado=0 )	e	
							where tipo=e.tipoTra and numero=e.numeroTra and novedad=e.conceptoAgro
							and registro=e.registroTran and registroNovedad=e.registroConcepto	
							and año=e.añoLabor and mes=e.mesLabor 

					end
					else
					begin									
						update aTransaccionTercero
						set jornales = 0
						from
							(
							select  c.tipo tipoTra, c.numero numeroTra, c.novedad conceptoAgro, c.tercero terceroT,
							c.registro registroTran, c.registroNovedad registroConcepto,c.año añoLabor, c.mes mesLabor  from dbo.aTransaccion AS a INNER JOIN
							dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
							dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro 
							join dbo.aNovedad as d on d.codigo= c.novedad and c.empresa=d.empresa
							where c.tercero=@tercero and a.empresa=@empresa  and d.claseLabor=@claseLabor	
							and convert(date,b.fecha) = @fecha
							and  a.anulado=0 )	e	
							where tipo=e.tipoTra and numero=e.numeroTra and novedad=e.conceptoAgro
							--and registro=e.registroTran 
							and registroNovedad=e.registroConcepto	
							and año=e.añoLabor and mes=e.mesLabor 
							and e.terceroT = tercero
							and novedad=conceptoAgro
					end
				end
				else
				begin
					update aTransaccionTercero
					set jornales = 1
					from
						( select top 1  c.tipo tipoTra, c.numero numeroTra, c.novedad conceptoAgro, 
						c.registro registroTran, c.registroNovedad registroConcepto,c.año añoLabor, c.mes mesLabor  from dbo.aTransaccion AS a INNER JOIN
						dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
						dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro 
						join dbo.aNovedad as d on d.codigo= c.novedad and c.empresa=d.empresa
						where c.tercero=@tercero and a.empresa=@empresa  and d.claseLabor=@claseLabor	
						and convert(date,b.fecha) = @fecha
						and a.anulado=0 )	e	
						where tipo=e.tipoTra and numero=e.numeroTra and novedad=e.conceptoAgro
						and registro=e.registroTran and registroNovedad=e.registroConcepto	
						and año=e.añoLabor and mes=e.mesLabor  and empresa=@empresa

																	
				end
					end
			else
			begin
														
				update aTransaccionTercero
						set jornales = 0
				from (select  c.tipo tipoTra, c.numero numeroTra, c.novedad conceptoAgro, 
				c.registro registroTran, c.registroNovedad registroConcepto,c.año añoLabor, c.mes mesLabor, c.tercero terceroT  from dbo.aTransaccion AS a INNER JOIN
				dbo.aTransaccionNovedad AS b ON b.numero = a.numero AND b.tipo = a.tipo AND b.empresa = a.empresa INNER JOIN
				dbo.aTransaccionTercero AS c ON c.numero = b.numero AND c.tipo = b.tipo AND c.empresa = b.empresa AND c.registroNovedad = b.registro 
				join dbo.aNovedad as d on d.codigo= c.novedad and c.empresa=d.empresa
				where c.tercero=@tercero and a.empresa=@empresa  and d.claseLabor=@claseLabor	
				and convert(date,b.fecha) = @fecha
				and a.anulado=0 )	e	
				where tipo=e.tipoTra and numero=e.numeroTra and novedad=e.conceptoAgro
				--and registro=e.registroTran 
				and registroNovedad=e.registroConcepto	
				and año=e.añoLabor and mes=e.mesLabor 
				and tercero=terceroT
				and novedad=conceptoAgro
			end
         end


fetch curPrestamo into @tercero,@fecha
end
close curPrestamo
deallocate curPrestamo




