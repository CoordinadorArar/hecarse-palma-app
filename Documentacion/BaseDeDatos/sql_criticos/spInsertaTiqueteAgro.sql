-- Procedimiento: spInsertaTiqueteAgro
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE proc [dbo].[spInsertaTiqueteAgro]
@empresa int,
@tipo varchar(50),
@numero varchar(50),
@extractora varchar(50),
@tiquete varchar(50),
@pesoNeto decimal,
@pesoBruto decimal,
@pesoTara decimal,
@sacos decimal,
@racimos decimal,
@cedulaConductor varchar(50),
@nombreConductor varchar(50),
@fechaTiquete datetime,
@interno bit,
@vehiculo varchar(50),
@remolque varchar(50),
@retorno int output
as

begin tran spInsertaTiqueteAgro


insert aTransaccionBascula(
empresa,tipo,numero,empresaExtractora,terceroExtractrora,tiquete,pesoBruto,pesoTara,pesoNeto,sacos,racimos,codigoConductor,nombreConductor,vehiculo,remolque,fecha,interno
)
select @empresa, @tipo, @numero, @extractora, @extractora, @tiquete, @pesoBruto, @pesoTara, @pesoNeto, @sacos, @racimos, @cedulaConductor, @nombreConductor, @vehiculo, @remolque, @fechaTiquete, @interno

if @@ERROR=0
begin
	set @retorno =0
	commit tran spInsertaTiqueteAgro
end
else
begin
	set @retorno = 1
	rollback tran spInsertaTiqueteAgro
end
