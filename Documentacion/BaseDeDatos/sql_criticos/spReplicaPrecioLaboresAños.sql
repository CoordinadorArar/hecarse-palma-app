-- Procedimiento: spReplicaPrecioLaboresAños
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

create proc [dbo].[spReplicaPrecioLaboresAños]
@empresa int,
@añoAnterior int,
@añoActual int,
@usuario varchar(50),
@retorno int output
as
begin tran aFinca 

if not exists(select *from aNovedadLotePrecio where empresa=@empresa and año=@añoAnterior )
begin set @Retorno = 2 rollback tran aFinca
return end

if exists(select *from aNovedadLotePrecio where empresa=@empresa and año=@añoActual )
begin set @Retorno = 3 rollback tran aFinca
return end

insert aNovedadLotePrecio
select empresa,@añoActual,novedad,registro,precioDestajo,precioContratistas,precioOtros,porcentaje,fechaRegistro,@usuario,modificado,baseSueldo
 from aNovedadLotePrecio
where empresa=@empresa and año=@añoAnterior

if (@@error = 0 ) 
begin set @Retorno = 0 commit tran aFinca end 
else 
begin set @Retorno = 1 rollback tran aFinca end