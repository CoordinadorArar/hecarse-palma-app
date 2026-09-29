# Funciones (18)

Definicion completa de las funciones escalares/tabulares del sistema.

---

## `SplitString` (SQL_TABLE_VALUED_FUNCTION)

```sql
CREATE FUNCTION [dbo].[SplitString]
(    
      @Input NVARCHAR(MAX),
      @Character CHAR(1)
)
RETURNS @Output TABLE (
      Item NVARCHAR(1000)
)
AS
BEGIN
      DECLARE @StartIndex INT, @EndIndex INT
 
      SET @StartIndex = 1
      IF SUBSTRING(@Input, LEN(@Input) - 1, LEN(@Input)) <> @Character
      BEGIN
            SET @Input = @Input + @Character
      END
	
	

      WHILE CHARINDEX(@Character, @Input) > 0
      BEGIN
            SET @EndIndex = CHARINDEX(@Character, @Input)
           
            INSERT INTO @Output(Item)
            SELECT SUBSTRING(@Input, @StartIndex, @EndIndex - 1)
           
            SET @Input = SUBSTRING(@Input, @EndIndex + 1, LEN(@Input))
      END
 
      RETURN
END

```

## `fRetornaDatos` (SQL_SCALAR_FUNCTION)

```sql

CREATE FUNCTION [dbo].[fRetornaDatos]
	( @item		varchar(50),
	  @producto	varchar(50),
	  @tipo		varchar(50),
	  @fecha	varchar(50),
	  @empresa  varchar(50) )
RETURNS float
AS
/***************************************************************************
Nombre: fRetornaDatos
Tipo: Función
Desarrollado: Infos Tacnologia SAS
Fecha: 06/02/2015

Argumentos de entrada: Producto, periodo
Argumentos de salida: Saldo Final
Descripción: 
***************************************************************************/
BEGIN

	

declare  @vehiculo varchar(50),
@remolque varchar(50), @pesoBruto int

declare @tabla2 table(
		vehiculo varchar(50),
		remolque varchar(50),
		pesoNeto int)
		
		declare @tabla1 table(
		vehiculo varchar(50),
		remolque varchar(50),
		pesoTara int)
		
		

if (@fecha in ('FAC','FAN'))
	set @fecha = convert(varchar(50),GETDATE())
	
	declare @dato float, @fechaN date = convert(date,@fecha)
	
	set @dato = 0

	if( @item = 'PND' )
	begin
	
	if (@tipo='DESSLL')
	begin
		set @dato = isnull((select  SUM( sacos )
		from bRegistroBascula
		where
		item = @producto and
		tipo = 'DPT' and
		CONVERT( date,fechaProceso ) = @fechaN
		and empresa=@empresa),0)
	end
	else
	begin
		set @dato = isnull((select  SUM( PesoNeto)
		from bRegistroBascula
		where
		item = @producto and
		tipo = @tipo and
		CONVERT( date,fechaProceso ) = @fechaN
		and empresa=@empresa),0)
	end	
	end

	if( @item = 'PDD' )
	begin
	
		set @dato = isnull((select  SUM( pesoDescuento )
		from bRegistroBascula
		where
		item = @producto and
		tipo = @tipo and
		CONVERT( date,fechaProceso ) = @fechaN
		and empresa=@empresa),0)
	
	end
	
	if( @item = 'VIN' )
	begin
		set @dato = isnull((select  SUM( valor )
		from vTransaccionProduccion
		where
		producto = @producto and
		movimiento = @tipo and
		CONVERT( date,fecha ) = @fechaN
		and empresa=@empresa),0)
	end	
	
	if( @item = 'PVA' )
	begin
		set @dato = isnull((select  valor
		from vTransaccionProduccion
		where
		producto = @producto and
		movimiento = @tipo and
		CONVERT( date,fecha ) = @fechaN
		and empresa=@empresa),0)
		
		if @dato=0
		begin
		set @dato = isnull((select  SUM( valor )
		from vTransaccionProduccion
		where
		producto = @producto and
		movimiento = 27 and
		CONVERT( date,fecha ) = @fechaN),0)
		end
		
	end	
	
		if( @item = 'FPD' )
	begin
	
	
	 
	 set @dato= ISNULL((select pesoneto from pFrutaEstimadaTmp
	 where fecha=@fechaN),0)
	 
	end	
	
	
		if( @item = 'NVP' )
	begin
		set @dato = isnull((select count(*) from  bRegistroBascula b where
		b.item = @producto and
		b.tipo = @tipo and
		CONVERT( date,b.fechaProceso ) = @fechaN
		and pesoNeto=0),0)
	end	
	
	if( @item = 'FDC' )
	begin
		set @dato = isnull((select sum(a.pesoNeto) from bRegistroBascula a
		where a.item = @producto and a.tipo = @tipo and
		CONVERT( date,a.fechaProceso) = @fechaN
		and a.fechaNeto < dateadd(HOUR,14,convert(datetime,dateadd(day,1,CONVERT(date, a.fechaProceso))))),0)
	end	
	
	if( @item = 'SED' )
	begin
		set @dato = isnull((select  SUM( PesoNeto )
		from bRegistroBascula
		where
		item = @producto and
		tipo = @tipo and
		CONVERT( date,fechaProceso ) = @fechaN),0)
	end	
	
		if( @item = 'FPF' )
	begin
		set @dato = isnull((select  SUM( valor )
		from vTransaccionProduccion
		where
		producto = @producto and
		movimiento = @tipo and
		CONVERT( date,fecha ) = @fechaN),0)
	end		
	
	if( @item = 'PNS' )
	begin	
		set @dato = isnull((select  SUM( pesoNeto )
		from bRegistroBascula
		where
		item = @producto and
		tipo = @tipo and
		DATEPART( WEEK,CONVERT( date,fechaProceso ) ) = DATEPART( WEEK,@fechaN ) and
		YEAR( fechaProceso ) = YEAR( @fechaN )),0)
	end		
	
	if( @item = 'PNM' )
		begin	
		set @dato = isnull((select  SUM( pesoNeto )
		from bRegistroBascula
		where
		item = @producto and
		tipo = @tipo and
		MONTH( CONVERT( date,fechaProceso ) ) = MONTH( @fechaN ) and
		YEAR( fechaProceso ) = YEAR( @fechaN )	),0)
	end		
	
	if( @item = 'PNA' )
	begin		
		set @dato = isnull((select  SUM( pesoNeto )
		from bRegistroBascula
		where
		item = @producto and
		tipo = @tipo and
		YEAR( CONVERT( date,fechaProceso ) ) = YEAR( @fechaN )),0)
	end		
	
	if( @item = 'SID' )
	begin	
		set @dato = isnull((select  sum( valor )
		from vTransaccionProduccion
		where
		producto = @producto and
		movimiento=@tipo and
		fecha =  @fechaN and empresa=@empresa),0)
	end		
	
	--if( @item = 'SOD' )
	--begin	
	--	set @dato = isnull((select  sum( saldoNuevo )
	--	from pSaldo
	--	where
	--	producto = @producto and
	--	fecha =  @fechaN),0)
	--end		
	
	--if( @item = 'SIS' )
	--begin	
	--	select @dato = sum( saldoAnterior )
	--	from pSaldo a
	--	where
	--	producto = @producto and
	--	fecha = ( select MIN( b.fecha )
	--			  from pSaldo b
	--			  where
	--			  a.producto = b.producto and
	--			  DATEPART( WEEK,b.fecha ) =  DATEPART( WEEK,@fechaN ) and
	--			  YEAR( b.fecha ) = YEAR( @fechaN ) )
	--end		
	
	--if( @item = 'SOS' )
	--begin	
	--	set @dato = isnull((select  sum( saldoNuevo )
	--	from pSaldo a
	--	where
	--	producto = @producto and
	--	fecha = ( select MAX( b.fecha )
	--			  from pSaldo b
	--			  where
	--			  a.producto = b.producto and
	--			  DATEPART( WEEK,b.fecha ) =  DATEPART( WEEK,@fechaN ) and
	--			  YEAR( b.fecha ) = YEAR( @fechaN ) )),0)
	--end		
	
	--if( @item = 'SIM' )
	--begin		
	--	set @dato = isnull((select  sum( saldoAnterior )
	--	from pSaldo a
	--	where
	--	producto = @producto and
	--	fecha = ( select MIN( b.fecha )
	--			  from pSaldo b
	--			  where
	--			  a.producto = b.producto and
	--			  MONTH( b.fecha ) =  MONTH( @fechaN ) and
	--			  YEAR( b.fecha ) = YEAR( @fechaN ) )),0)
	--end		
	
	--if( @item = 'SOM' )
	--begin		
	--	set @dato = isnull((select  sum( valor )
	--	from vTransaccionProduccion a
	--	where
	--	producto = @producto and
	--	fecha = ( select MAX( b.fecha )
	--			  from pSaldo b
	--			  where
	--			  a.producto = b.producto and
	--			  MONTH( b.fecha ) =  MONTH( @fechaN ) and
	--			  YEAR( b.fecha ) = YEAR( @fechaN ) )),0)
	--end
	
	--if( @item = 'SIA' )
	--begin			
	--	set @dato = isnull((select sum( saldoAnterior )
	--	from pSaldo a
	--	where
	--	producto = @producto and
	--	fecha = ( select MIN( b.fecha )
	--			  from pSaldo b
	--			  where
	--			  a.producto = b.producto and
	--			  YEAR( b.fecha ) = YEAR( @fechaN ) )),0)
	--end		
		
	--if( @item = 'SOA' )
	--begin		
	--	set @dato = isnull((select  sum( saldoNuevo )
	--	from pSaldo a
	--	where
	--	producto = @producto and
	--	fecha = ( select MAX( b.fecha )
	--			  from pSaldo b
	--			  where
	--			  a.producto = b.producto and
	--			  YEAR( b.fecha ) = YEAR( @fechaN ) )),0)
	-- end
	
	--if( @item = 'TND' )
	--begin
	--	select @dato = SUM( valor )
	--	from pTransaccion
	--	where
	--	producto = @producto and
	--	tipo = @tipo and
	--	CONVERT( date,fecha ) = @fechaN
	--end		
	
	--if( @item = 'TNS' )
	--begin	
	--	select @dato = SUM( valor )
	--	from pTransaccion
	--	where
	--	producto = @producto and
	--	tipo = @tipo and
	--	DATEPART( WEEK,CONVERT( date,fecha ) ) = DATEPART( WEEK,@fechaN ) and
	--	YEAR( fecha ) = YEAR( @fechaN )
	--end		
	
	--if( @item = 'TNM' )
	--	begin	
	--	select @dato = SUM( valor )
	--	from pTransaccion
	--	where
	--	producto = @producto and
	--	tipo = @tipo and
	--	MONTH( CONVERT( date,fecha ) ) = MONTH( @fechaN ) and
	--	YEAR( fecha ) = YEAR( @fechaN )	
	--end		
	
	--if( @item = 'TNA' )
	--begin		
	--	select @dato = SUM( valor )
	--	from pTransaccion
	--	where
	--	producto = @producto and
	--	tipo = @tipo and
	--	YEAR( CONVERT( date,fecha ) ) = YEAR( @fechaN )
	--end	
	
	return @dato

END


```

## `fRetornaDatosProduccionInforme` (SQL_SCALAR_FUNCTION)

```sql
CREATE FUNCTION [dbo].[fRetornaDatosProduccionInforme]
	( @item		varchar(50),
	  @producto	varchar(10),
	  @movimiento		varchar(50),
	  @fecha	date,
	  @empresa int )
RETURNS float
AS

BEGIN

	declare @dato float, @INI float, @FR float, @FP float 
	declare @año char(6)=year(@fecha),@PP FLOAT,@PTP float,@planta varchar(50)
	
	
	set @dato = 0
	
	--select @planta=planta from pPlantaProducto
	--where producto=@producto

	


	if( @item = 'D' )
	begin
	
	if( @movimiento in ('EX') )
	begin
			
	if (@producto='CPO' OR @producto='ADPA')		
	begin
		 set @PP= (select  SUM( valor )
		from vTransaccionesProduccion 
		where refProducto='FRU' AND refMovimiento='FP' and
		fecha=@fecha and empresa=@empresa)
	end
	else
	begin
		select  @PP= SUM( valor )
		from vTransaccionesProduccion 
		where refMovimiento='AP' and refProducto='ADPA' AND
		fecha=@fecha and empresa=@empresa
	end

		select  @PTP= SUM( valor )
		from vTransaccionesProduccion 
		where refProducto=@producto and refMovimiento='PRO' and
		fecha=@fecha and empresa=@empresa
		
	
		set @dato=isnull((@PTP/nullif(@PP,0)),0)*100
			
	end
	ELSE
	BEGIN
		set @dato =(select top 1 valor
		from vTransaccionesProduccion	
		where
		refProducto = @producto and
		refMovimiento = @movimiento and
		CONVERT( date,fecha ) = @fecha and empresa=@empresa)
	END
	end	
		
	
	if( @item = 'S' )
	begin	
	
		if	@movimiento='INI'
		BEGIN
		select top 1 @dato = valor
		from vTransaccionesProduccion	
		where
		refproducto = @producto and
		refMovimiento = @movimiento and empresa=@empresa and
		fecha=(select min( b.fecha )
				  from vTransaccionesProduccion b
				  where
					producto = b.producto and empresa=@empresa and
				  DATEPART( WEEK,b.fecha ) =  DATEPART( WEEK,@fecha ) and
				  DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))  
				)	and
		 DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))  
		END
		
		if	@movimiento='INISLL'
		BEGIN
		select top 1 @dato = valor
		from vTransaccionesProduccion	
		where
		refproducto = @producto and
		refmovimiento = @movimiento and empresa=@empresa and
		fecha=(select min( b.fecha )
				  from vTransaccionesProduccion b
				  where
					producto = b.producto and empresa=@empresa and
				  DATEPART( WEEK,b.fecha ) =  DATEPART( WEEK,@fecha ) and
				  DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))  
				)	and
		 DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))  
		END
		
			if	@movimiento='INISV'
		BEGIN
		select top 1 @dato = valor
		from vTransaccionesProduccion	
		where
		refproducto = @producto and
		refmovimiento = @movimiento and empresa=@empresa and
		fecha=(select min( b.fecha )
				  from vTransaccionesProduccion b
				  where
					producto = b.producto and empresa=@empresa and
				  DATEPART( WEEK,b.fecha ) =  DATEPART( WEEK,@fecha ) and
				  DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))  
				)	and
		 DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))  
		END
		
	if( @movimiento = 'EX' )
	Begin		
		
	if (@producto='CPO' OR @producto='ADPA')		
	begin
		select  @PP= SUM( valor )
		from vTransaccionesProduccion a
		where refproducto='FRU' AND refMovimiento='FP' and
			DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha)) and empresa=@empresa
	end
	else
	begin
		select  @PP= SUM( valor )
		from vTransaccionesProduccion a
		where a.refmovimiento='AP' and a.refproducto='ADPA' AND
		DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha)) and empresa=@empresa
	end
		
		select  @PTP= SUM( valor )
		from vTransaccionesProduccion a
		where A.refproducto=@producto and a.refmovimiento='PRO' and
		DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha)) and empresa=@empresa
		
		set @dato=(@PTP/@PP)*100
		
		end	
	
		if	@movimiento<>'INI' AND @movimiento<>'IFSV' AND @movimiento<>'INISLL' AND @movimiento<>'INISV' AND @movimiento<>'IFSLL' AND @movimiento<>'EX' AND @movimiento<>'IF' AND (select almacena from pProductoMovimiento where @movimiento=movimiento and producto=@producto)  <>1
		BEGIN
		select @dato = sum(isnull(valor,0))
		from vTransaccionesProduccion	
		where
		refproducto = @producto and
		refMovimiento = @movimiento and
		DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))   and empresa=@empresa
		END
			
	
		if	@movimiento='IF'
		BEGIN
		select @dato= isnull(valor,0)
		from vTransaccionesProduccion	
		where
		refProducto = @producto and
		refMovimiento = @movimiento and empresa=@empresa and
		fecha=(select max( b.fecha )
				  from vTransaccionesProduccion b
				  where
					producto = b.producto and empresa=@empresa and
				  DATEPART( WEEK,b.fecha ) =  DATEPART( WEEK,@fecha ) and
				  YEAR( b.fecha ) = YEAR( @fecha ) )
		  and
		DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))
		END
		
		if	@movimiento='IFSV' 
		BEGIN
		select @dato= isnull(valor,0)
		from vTransaccionesProduccion	
		where
		refproducto = @producto and
		refMovimiento = @movimiento and empresa=@empresa and
		fecha=(select max( b.fecha )
				  from vTransaccionesProduccion b
				  where
					producto = b.producto and empresa=@empresa and
				  DATEPART( WEEK,b.fecha ) =  DATEPART( WEEK,@fecha ) and
				  YEAR( b.fecha ) = YEAR( @fecha ) )
		  and
		DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))
		END
		
		if	@movimiento='IFSLL' 
		BEGIN
		select @dato= isnull(valor,0)
		from vTransaccionesProduccion	
		where
		refproducto = @producto and
		refmovimiento = @movimiento and empresa=@empresa and
		fecha=(select max( b.fecha )
				  from vTransaccionesProduccion b
				  where
					producto = b.producto and empresa=@empresa and
				  DATEPART( WEEK,b.fecha ) =  DATEPART( WEEK,@fecha ) and
				  YEAR( b.fecha ) = YEAR( @fecha ) )
		  and
		DATEPART(week,convert(date,fecha))= DATEPART(week,convert(date,@fecha)) and 
		DATEPART(MONTH,convert(date,fecha))= DATEPART(MONTH,convert(date,@fecha))
		END
			
				end	
				
					
	return @dato

END

```

## `fRetornaDatosProduccionPeriodo` (SQL_SCALAR_FUNCTION)

```sql
CREATE FUNCTION [dbo].[fRetornaDatosProduccionPeriodo]
	( @item		varchar(50),
	  @producto	varchar(10),
	  @movimiento		varchar(50),
	  @periodo	varchar(6),
	  @empresa	int,
	  @almacena bit )
RETURNS float
AS

BEGIN

	DECLARE @INI float,@FR	float,@VP float,@VLL float,@IF float,@HP float,@HPA float
	declare @dato float, @FP float 
	declare @PP FLOAT,@PTP float,@planta varchar(50)
	declare @mes varchar(2)=substring(@periodo,5,len(@periodo)),@año varchar(4)=substring(@periodo,1,4)
	declare @refProducto varchar(50) = (select referencia from iitems where codigo=@producto and empresa = @empresa AND tipo='P')

	
	
	set @dato = 0
	
	if( @item = 'M' )
	begin	
		 
		if	(@movimiento= 'INI' OR @movimiento='INII' OR @movimiento='INIP')
		BEGIN
			select @dato = valor from vTransaccionesProduccion	a
			where a.producto = @producto and a.anulado=0 and 
			a.refmovimiento = @movimiento and empresa=@empresa and
			convert(date,fecha)=convert(date,(select min( b.fecha )
				  from vTransaccionesProduccion b
				  where b.producto=@producto and b.refmovimiento=@movimiento and empresa=@empresa  
				  AND b.anulado=0 and MONTH(b.fecha) =  @mes and YEAR(b.fecha) = @año ) )
		END
		
	if( @movimiento in ('HCPO','HCPKO','ACPO','ACPKO') )
	begin	
	
	SET  @dato= ISNULL((select  AVG( valor ) from vTransaccionesProduccion a
		where producto =@producto AND a.refmovimiento=@movimiento and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa AND anulado=0),0)

	end	


							
	if( @movimiento in ('EXCPKO') )
	begin	
	
	select  @PP= SUM( valor )
		from vTransaccionesProduccion a
		where refproducto in ('ADPA') AND a.refmovimiento='AP' and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa 
		AND anulado=0

		select  @PTP= SUM( valor )
		from vTransaccionesProduccion a
		where A.refProducto='CPKO' and a.refMovimiento='PRO' and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa 
		AND anulado=0
		set @dato=isnull((@PTP/nullif(@PP,0)),0)*100
	end	

	if( @movimiento in ('EXTP') )
	begin	
	
	select  @PP= SUM( valor )
		from vTransaccionesProduccion a
		where refproducto in ('ADPA') AND a.refmovimiento='AP' and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa 
		AND anulado=0

		select  @PTP= SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='TP' and a.refMovimiento='PRO' and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa 
		AND anulado=0
		set @dato=isnull((@PTP/nullif(@PP,0)),0)*100
	end	

					
	if( @movimiento in ('EX') )
	begin	
	
	if (@refproducto='CPO' OR @refproducto='ADPA')		
	begin
		select  @PP= SUM( valor )
		from vTransaccionesProduccion a
		where refproducto in ('FRU','FRUPAL') AND a.refmovimiento='FP' and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa 
		AND anulado=0
	end
	else
	begin
		select  @PP= SUM( valor )
		from vTransaccionesProduccion a
		where a.refMovimiento='AP' and a.refProducto='ADPA' AND
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa 
	end

		select  @PTP= SUM( valor )
		from vTransaccionesProduccion a
		where A.producto=@producto and a.refMovimiento='PRO' and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa 
		AND anulado=0
		set @dato=isnull((@PTP/nullif(@PP,0)),0)*100
	end	
	
	if( @movimiento = 'PPV' )
	begin	
		set @FR=isnull((select   SUM( valor )
		from vTransaccionesProduccion a
		where producto=@producto AND a.refMovimiento='FP' and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa AND anulado=0 ),0)
		
		set @VP=isnull((select   SUM( valor )
		from vTransaccionesProduccion a
		where producto=@producto AND a.refMovimiento='VP' and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes and empresa=@empresa AND anulado=0 ),0)
		
		set @dato=isnull(@FR/nullif(@VP,0),0)
	end	
	
	if( @movimiento = 'CPTHE' )
	begin
	
	if(@producto='FRU')
	begin			  
		SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='FP' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes),0)
			
		SET @HP=ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='HE'  and empresa=@empresa and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes AND anulado=0),0)
	end
	
	if(@producto='CPKO')
	begin			  
		SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refproducto='ADPA' AND a.refMovimiento='AP' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes AND anulado=0),0)
			
		SET @HP=ISNULL((select  SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='CPKO' AND a.refMovimiento='HE' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes AND anulado=0),0)
	end
		
	IF @HP=0
	SET @dato=0
	ELSE
	set @dato=round(isnull((@FR/nullif(@HP,0)),0),0)
		
	end
	
	if( @movimiento = 'CPTHP' )
	begin
	if(@producto='FRU')
		begin	
		SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='FP'  and empresa=@empresa and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes),0)
						
		SET @HP=ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='HP'  and empresa=@empresa and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes),0)
		end
		if(@producto='CPKO')
	begin			  
		SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='ADPA' AND a.refMovimiento='AP'  and empresa=@empresa and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes),0)
			
		SET @HP=ISNULL((select  SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='CPKO' AND a.refMovimiento='HP'  and empresa=@empresa and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes),0)
	end
											  
	IF @HP=0
	SET @dato=0
	ELSE
	set @dato=round(isnull((@FR/nullif(@HP,0)),0),0)
	
	end
	
	IF (@movimiento='VLL' OR @movimiento='SEC')
	BEGIN
	select @dato = valor from vTransaccionesProduccion	a
		where a.refProducto = @producto and	a.refMovimiento = @movimiento  and empresa=@empresa and
		convert(date,fecha)=convert(date,(select MAX( b.fecha )
				  from vTransaccionesProduccion b
				  where b.refProducto=@producto  and empresa=@empresa and
					B.refMovimiento=@movimiento AND 
				MONTH(b.fecha) =  @mes and 
				  YEAR(b.fecha) = @año ) )
	END
	
						
	if	@almacena=1 and @dato=0
	BEGIN
		select @dato = valor
		from vTransaccionesProduccion a	where a.producto = @producto and
		a.refMovimiento = @movimiento  and empresa=@empresa and
		convert(date,fecha)=convert(date,(select MAX( b.fecha )
				  from vTransaccionesProduccion b		  where b.producto=@producto and
					B.refMovimiento=@movimiento and empresa=@empresa  AND MONTH(b.fecha) =  @mes and  YEAR(b.fecha) = @año ) )
	END
			
	if( @movimiento = 'NR' AND @producto='ADPA' )
	begin
	    SET @dato=ISNULL((SELECT SUM(VALOR) FROM vTransaccionesProduccion 
	    WHERE refProducto='NUEZFP' AND refMovimiento='FR' and empresa=@empresa  AND YEAR(fecha) =  @año and MONTH(fecha)=@mes),0)
	end
	    	
	IF(@movimiento='PN')
	BEGIN
	SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refproducto='NUEZFP' AND a.refMovimiento='ANR' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes),0)
						
		SET @HP=ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='NUEZFP' AND a.refMovimiento='FR' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)=@mes),0)
		
	IF @HP=0
	SET @dato=0
	ELSE
	set @dato=round(isnull((@FR/nullif(@HP,0)),0),0)
	
	END
	
	IF (@movimiento='IH')
	BEGIN
	SET @DATO=ISNULL((SELECT AVG(VALOR) from vTransaccionesProduccion
	where refProducto=@producto AND refMovimiento=@movimiento  and empresa=@empresa and
	YEAR(fecha) =  @año and MONTH(fecha)=@mes AND valor<>0),0)
	END	
	
	end
		
		if( @item = 'A' )
		begin	
		
		if	(@movimiento= 'INI' OR @movimiento='INII' OR @movimiento='INIP')
		BEGIN
		 select @dato = valor
		from vTransaccionesProduccion	
		where producto = @producto  and empresa=@empresa and
		refMovimiento = @movimiento and anulado=0 and 
		fecha=(	(select min( b.fecha ) from vTransaccionesProduccion b
				  where	producto =@producto and YEAR( b.fecha ) = @año and anulado=0
				  and refMovimiento=@movimiento  and empresa=@empresa and MONTH(fecha)<=@mes))
		END
		
		if( @movimiento = 'PPB' )
	begin	
		select  @FR= SUM( valor )
		from vTransaccionesProduccion a
		where refproducto=@producto AND a.refMovimiento='DESSLL' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes
		
		select  @VP= SUM( valor )
		from vTransaccionesProduccion a
		where refProducto=@producto AND a.refMovimiento='DES' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes
		
			set @dato=round(isnull(@VP/nullif(@FR,0),0),0)
	end	
	
		if( @movimiento in ('HCPO','HCPKO','ACPO','ACPKO') )
	begin	
	
	SET  @dato= ISNULL((select  AVG( valor ) from vTransaccionesProduccion a
		where producto =@producto AND a.refmovimiento=@movimiento and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes and empresa=@empresa AND anulado=0),0)

	end	

	IF (@movimiento='IH')
	BEGIN
	SET @DATO=ISNULL((SELECT AVG(VALOR) from vTransaccionesProduccion
	where refProducto=@producto AND refMovimiento=@movimiento  and empresa=@empresa and
	YEAR(fecha) =  @año and MONTH(fecha)<=@mes AND valor<>0),0)
	END	
	
		if( @movimiento = 'NR' AND @producto='ADPA' )
	    begin
	    SET @dato=ISNULL((SELECT SUM(VALOR) FROM vTransaccionesProduccion 
	    WHERE refProducto='NUEZFP' AND refMovimiento='FR'  and empresa=@empresa AND YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
	    end
	    
		if( @movimiento = 'PPV' )
	    begin
		
		SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='FP'  and empresa=@empresa and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
		
		SET @VP=ISNULL((select SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='VP'  and empresa=@empresa and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
		
		IF @VP=0
		SET @dato=0
		ELSE
		set @dato=ROUND(isnull((@FR/nullif(@VP,0)),0),0)
	end	
	
	if( @movimiento = 'CPTHE' )
	begin
		if(@refProducto='FRU')
		begin	
		SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='FP' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
						
		SET @HP=ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='HE' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
		end
		if(@producto='CPKO')
	begin			  
		SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='ADPA' AND a.refMovimiento='AP' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
			
		SET @HP=ISNULL((select  SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='CPKO' AND a.refMovimiento='HE' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
	end
	
		
	IF @HP=0
	SET @dato=0
	ELSE
	set @dato=round(isnull((@FR/nullif(@HP,0)),0),0)
		
	end
	
	IF(@movimiento='PN')
	BEGIN
	SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto=@producto AND a.refMovimiento='ANR' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
						
		SET @HP=ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto=@producto AND a.refMovimiento='FR' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
		
	IF @HP=0
	SET @dato=0
	ELSE
	set @dato=round((@FR/@HP)*100,0)
	
	END
	
	
	
	if( @movimiento = 'CPTHP')
	begin
		if(@producto='FRU')
		begin	
		SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='FP' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
						
		SET @HP=ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='FRU' AND a.refMovimiento='HP' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
		end
		if(@producto='CPKO')
	begin			  
		SET @FR= ISNULL((select   SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='ADPA' AND a.refMovimiento='AP' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
			
		SET @HP=ISNULL((select  SUM( valor )
		from vTransaccionesProduccion a
		where refProducto='CPKO' AND a.refMovimiento='HP' and empresa=@empresa  and
		YEAR(fecha) =  @año and MONTH(fecha)<=@mes),0)
	end
	
											  
	IF @HP=0
	SET @dato=0
	ELSE
	set @dato=round(isnull((@FR/nullif(@HP,0)),0),0)
	
	end
		
		
	if( @movimiento = 'EX' )
	begin		
	if (@refProducto IN ('CPO','ADPA'))		
	begin
		select  @PP= SUM( valor )
		from vTransaccionesProduccion a
		where refProducto LIKE 'FRU%' AND a.refMovimiento='FP' and empresa=@empresa  and
			YEAR(fecha) =  @año	and MONTH(fecha)<=@mes AND anulado=0 and anulado=0
	end
	else
	begin
		select  @PP= SUM( valor )
		from vTransaccionesProduccion a
		where a.movimiento='AP' and a.producto='ADPA' and empresa=@empresa  AND
		YEAR(fecha) =  @año	   and MONTH(fecha)<=@mes AND anulado=0 and anulado=0
	end
		
		select  @PTP= SUM( valor )
		from vTransaccionesProduccion a
		where A.refProducto=@producto and a.refMovimiento='PRO' and empresa=@empresa  and
		YEAR(fecha) =  @año	   and MONTH(fecha)<=@mes AND anulado=0
		
		set @dato=isnull((@PTP/nullif(@PP,0)),0)*100
		
	end	
	
	if	@almacena=1 and @dato=0
	BEGIN
		select @dato= isnull(valor,0)
		from vTransaccionesProduccion	
		where producto = @producto and
		refMovimiento = @movimiento and empresa=@empresa  and anulado=0 and 
		fecha=(select max( b.fecha )	  from vTransaccionesProduccion b
				  where producto = b.producto and b.anulado=0 and 
					refmovimiento=@movimiento and YEAR(b.fecha) = @año and empresa=@empresa  and MONTH(fecha)<=@mes )
	END
		
	end
	
	return @dato

	
end

```

## `fRetornaDeTabla` (SQL_SCALAR_FUNCTION)

```sql
CREATE FUNCTION [dbo].[fRetornaDeTabla]
	(  
	@tipo varchar(50),
	@producto varchar(50),
	@movimiento varchar(50),
	@empresa int,
	@variable float
	
	  )
RETURNS float
AS

BEGIN

declare @valor float

if @tipo='TDT'
begin

set @valor = ISNULL((select densidad from pDensidad	
where temperatura = convert(int,@variable) and item =@producto and empresa =@empresa),0)
end


if @tipo='ZER'
begin

if @variable>0
	set @valor =1
else
	set @valor =0
	
end

if @tipo='TAT'
begin
	declare @metros int, @centimetro int, @milimetro int
	set @metros = convert(int,(convert(int,@variable)/10))*10
	set @centimetro = convert(int,(convert(int,@variable) - @metros))
	set @milimetro = round(((@variable-convert(int,@variable))*10),0)
	
	if not exists(select * from pCalibracionTanque where movimiento=@movimiento and tipo='CI'
	and empresa=@empresa  and altura=@metros)
	begin
		
		set @milimetro =(@variable)*10 
		set @valor = isnull((select volumen from pCalibracionTanque 
		where movimiento=@movimiento and altura =@milimetro and tipo='FO' and empresa=@empresa),0)
	end
	else
	begin
		set @valor = isnull((select volumen from pCalibracionTanque 
		where movimiento=@movimiento and altura =@metros and tipo='CI' and empresa=@empresa),0)
		
		set @valor = @valor + isnull((select volumen from pCalibracionTanque 
		where movimiento=@movimiento and altura =@centimetro and tipo='CM' and empresa=@empresa),0)
		
		set @valor = @valor + isnull((select volumen from pCalibracionTanque 
		where movimiento=@movimiento and altura =@milimetro and tipo='MM' and empresa=@empresa),0)
	end
end


return @valor

END
```

## `fRetornaNombreMes` (SQL_SCALAR_FUNCTION)

```sql
CREATE FUNCTION fRetornaNombreMes(@mes int)
RETURNS varchar(50)
AS
BEGIN
	
	DECLARE @nombreMes varchar(50)

	if(@mes=1)
		set @nombreMes='Enero'
	if(@mes=2)
		set @nombreMes='Febrero'
	if(@mes=3)
		set @nombreMes='Marzo'
	if(@mes=4)
		set @nombreMes='Abril'
	if(@mes=5)
		set @nombreMes='Mayo'
	if(@mes=6)
		set @nombreMes='Junio'
	if(@mes=7)
		set @nombreMes='Julio'
	if(@mes=8)
		set @nombreMes='Agosto'
	if(@mes=9)
		set @nombreMes='Septiembre'
	if(@mes=10)
		set @nombreMes='Octubre'
	if(@mes=11)
		set @nombreMes='Noviembre'
	if(@mes=12)
		set @nombreMes='Diciembre'

	RETURN @nombreMes

END

```

## `fRetornaPrecioLaboresTercero` (SQL_SCALAR_FUNCTION)

```sql

CREATE FUNCTION [dbo].[fRetornaPrecioLaboresTercero] 
	( @empresa int,@novedad varchar(50),@año int,@tercero int,@fechaNovedad date,@finca varchar(50),@seccion varchar(50),@lote varchar(50),@contratista bit )
RETURNS float
AS
begin
declare @precio float


declare @VDSM money = isnull((select top 1 vSalarioMinimo/30 from nParametrosAno where empresa=@empresa and ano=@año),1)

declare @SMLV money,@SMLVD money,@JD int,@Salario money, @fechaTerminacionContrato date
declare @base money,@conceptoBase varchar(50),@mPorcentaje bit, @valorPorcentaje decimal(18,3),@tipoUnidad int,@domingo date,@lunes date,@conceptoTransporte varchar(50),@signo int
declare @salarioDiario money= 0, @salarioHora money,@VU money,@controlConcepto int,@restaTransporte bit,@quitaDomingo bit,@conceptoSueldo varchar(50),@conceptoDomingo varchar(50)
select @SMLV=vSalarioMinimo, @SMLVD=vSalarioMinimo/30 from nParametrosAno where empresa=@empresa and ano= @año

select @conceptoSueldo=sueldo, @conceptoDomingo=ganaDomingo, @conceptoTransporte=subsidioTransporte,@JD=jornadaDiaria from nParametrosGeneral where empresa=@empresa
set @precio = 0

select @fechaTerminacionContrato  = fechaRetiro from nProrroga
where tercero=@tercero and tipo='R' and contrato=(select max(id) from nContratos where tercero=@tercero and empresa=@empresa)
and empresa=@empresa



if (exists( select 1 from nFuncionario where empresa=@empresa and codigo=@tercero and fechaIngreso<=@fechaNovedad and contratista=0 and activo=1 ))
begin

	select top 1 @Salario= salario from nFuncionario where empresa=@empresa and tercero=@tercero 

	set @salarioDiario = @Salario/30
	set @salarioHora = @salarioDiario / @JD
	set @VU = @salarioHora 

	set @VDSM = CEILING(@salarioDiario)

	
	
	if exists(select 1 from aNovedadPrecio where empresa=@empresa and novedad=@novedad and lote=@lote and isnull(seccion,'')=@seccion and finca=@finca and año=@año)
	begin
		set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
		 else  precioDestajo end  FROM aNovedadPrecio
		where empresa= @empresa and novedad=@novedad and año=@año and lote=@lote and seccion=@seccion and finca=@finca ),0)
	end
	else
	begin
		if exists(select * from aNovedadPrecio where empresa=@empresa and novedad=@novedad and seccion=@seccion and finca=@finca and año=@año and lote='')
		begin
			set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
			else  precioDestajo end  FROM aNovedadPrecio
			where empresa= @empresa and novedad=@novedad and año=@año and seccion=@seccion and finca=@finca ),0)
		end
		else
		begin
			if exists(select * from aNovedadPrecio where empresa=@empresa and novedad=@novedad  and finca=@finca and año=@año and lote='' and seccion='')
			begin
				set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))				else  precioDestajo end  FROM aNovedadPrecio
				where empresa= @empresa and novedad=@novedad and año=@año  and finca=@finca ),0)
			end
			else
			begin
				set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))	else  precioDestajo end FROM aNovedadLotePrecio
				where empresa= @empresa and novedad=@novedad and año=@año ),0)

			end
		end
	end
end
else
begin
	if exists(select 1 from  nFuncionario where empresa=@empresa and codigo=@tercero and contratista=1)
	begin
	
		set @Salario=CEILING(@SMLV)
		set @salarioDiario = @Salario/30
		set @salarioHora = @salarioDiario / @JD
		set @VU = @salarioHora 
		
		if exists(select * from aNovedadPrecio where empresa=@empresa and novedad=@novedad and lote=@lote and seccion=@seccion and finca=@finca and año=@año)
		begin
			set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
			else  precioContratistas end  FROM aNovedadPrecio
			where empresa= @empresa and novedad=@novedad and año=@año and lote=@lote and seccion=@seccion and finca=@finca ),0)
		end
		else
		begin
			 if exists(select * from aNovedadPrecio where empresa=@empresa and novedad=@novedad  and seccion=@seccion and finca=@finca and año=@año and (lote is null or lote=''))
			begin
				set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
				else  precioContratistas end  FROM aNovedadPrecio
				where empresa= @empresa and novedad=@novedad and año=@año and seccion=@seccion and finca=@finca and (lote is null or lote='')),0)
			end
			else
			begin
				 if exists(select 1 from aNovedadPrecio where empresa=@empresa and novedad=@novedad and finca=@finca and año=@año and (lote is null or lote=''))
				begin
					set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
					else  precioContratistas end  FROM aNovedadPrecio
					where empresa= @empresa and novedad=@novedad and año=@año  and finca=@finca and (lote is null or lote='') ),0)
				end
				else
				begin
					set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
					else  precioContratistas end FROM aNovedadLotePrecio
					where empresa= @empresa and novedad=@novedad and año=@año  ),0)
				end
			end
		end
	end
	
	if exists(select 1 from  nFuncionario where empresa=@empresa and codigo=@tercero and activo=0 and contratista=0 )
	begin
			set @Salario=@SMLV
		set @salarioDiario = @Salario/30
		set @salarioHora = @salarioDiario / @JD
		set @VU = @salarioHora 

		if exists(select 1 from aNovedadPrecio where empresa=@empresa and novedad=@novedad and lote=@lote and seccion=@seccion and finca=@finca and año=@año)
		begin
		
			set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
			else  precioOtros end  FROM aNovedadPrecio
			where empresa= @empresa and novedad=@novedad and año=@año and lote=@lote and seccion=@seccion and finca=@finca ),0)
		end
		else
		begin
			if exists(select 1 from aNovedadPrecio where empresa=@empresa and novedad=@novedad and seccion=@seccion and finca=@finca and año=@año and (lote is null or lote='') )
			begin
				set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
				else  precioOtros end  FROM aNovedadPrecio
				where empresa= @empresa and novedad=@novedad and año=@año and seccion=@seccion and finca=@finca and (lote is null or lote='') ),0)
			end
			else
			begin
				if exists(select 1 from aNovedadPrecio where empresa=@empresa and novedad=@novedad  and finca=@finca and año=@año and (lote is null or lote=''))
				begin
					set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
					else  precioOtros end  FROM aNovedadPrecio
					where empresa= @empresa and novedad=@novedad and año=@año  and finca=@finca and (lote is null or lote='')),0)
				end
				else
				begin
				
					set @precio=  isnull((select top 1 case when baseSueldo = 1 and porcentaje=0 then @VDSM  when baseSueldo=1 and porcentaje>0 then convert(float,CEILING(@VU * (porcentaje/100)))
					else  precioOtros end FROM aNovedadLotePrecio
					where empresa= @empresa and novedad=@novedad and año=@año  ),0)
				end
			end
		end
		end


end

return @precio
end

```

## `fRetornaRacimosFincaAño` (SQL_SCALAR_FUNCTION)

```sql

CREATE FUNCTION [dbo].[fRetornaRacimosFincaAño]
	( @empresa		int,
		@año int,
		@ff date,
	  @finca	varchar(50),
	  @seccion varchar(50))
RETURNS float
AS
BEGIN
			declare @racimos float


		set @racimos= isnull((select sum(b.racimos) from aTransaccion a
			join aTransaccionNovedad b on b.numero=a.numero and b.tipo=a.tipo and b.empresa=a.empresa
			join aNovedad c on c.empresa=b.empresa and c.codigo=b.novedad and c.claseLabor=2
			where year(a.fecha)=@año and a.anulado=0 and a.fecha <=@ff
			and b.finca=@finca and isnull(seccion,'')=isnull(@seccion,'')),0)


			return @racimos
end


```

## `fRetornaRacimosFincaFecha` (SQL_SCALAR_FUNCTION)

```sql

CREATE FUNCTION [dbo].[fRetornaRacimosFincaFecha]
	( @empresa		int,
	  @fi	date,
	  @ff		date,
	  @finca	varchar(50),
	  @seccion varchar(50))
RETURNS float
AS
BEGIN
			declare @racimos float


		set @racimos= isnull((select sum(b.racimos) from aTransaccion a
			join aTransaccionNovedad b on b.numero=a.numero and b.tipo=a.tipo and b.empresa=a.empresa
			join aNovedad c on c.empresa=b.empresa and c.codigo=b.novedad and c.claseLabor=2
			where a.fecha between @fi and @ff and a.anulado=0 and b.finca=@finca and isnull(seccion,'')=isnull(@seccion,'')),0)


			return @racimos
end


```

## `fRetornaTotalDepachos` (SQL_SCALAR_FUNCTION)

```sql

CREATE FUNCTION [dbo].[fRetornaTotalDepachos]
	( @tipo		varchar(50),
	  @valor	int,
	  @producto varchar(50),
	  @empresa	int,
	  @fecha	date	  )
RETURNS float
AS
/***************************************************************************
Nombre: fRetornaTotalFruta
Tipo: Función
Desarrollado: Infos Tacnologia SAS
Fecha: 06/02/2015

Argumentos de entrada: Producto, periodo
Argumentos de salida: Saldo Final
Descripción: 
***************************************************************************/
BEGIN

declare @dato float

if (@tipo='D')
begin 
	set @dato = isnull((select SUM(pesoNeto)  FROM            bRegistroBascula a
	join iItems b on b.codigo=a.item and b.empresa=a.empresa
	WHERE   a.tipo = 'DPT' AND pesoNeto <> 0 AND b.codigo =@producto and convert(date,fechaProceso)=@fecha
	GROUP BY YEAR(CONVERT(varchar(50), fechaProceso)), CONVERT(varchar(50), CONVERT(date, fechaProceso))),0)
end

if (@tipo='M')
begin 
	set @dato = isnull((select SUM(pesoNeto)  FROM            bRegistroBascula a
	join iItems b on b.codigo=a.item and b.empresa=a.empresa
	WHERE   a.tipo = 'DPT' AND pesoNeto <> 0 AND b.codigo =@producto and MONTH(fechaproceso)=@valor and YEAR(fechaproceso)=YEAR(@fecha)
	GROUP BY YEAR(fechaProceso), MONTH(fechaProceso)),0)
end

if (@tipo='A')
begin 
	set @dato = isnull((select SUM(pesoNeto)  FROM            bRegistroBascula a
	join iItems b on b.codigo=a.item and b.empresa=a.empresa
	WHERE   a.tipo = 'DPT' AND pesoNeto <> 0 AND b.codigo =@producto and YEAR(fechaproceso)=@valor
	GROUP BY YEAR(fechaProceso), YEAR(fechaProceso)),0)
end
		
	return @dato

END


```

## `fRetornaTotalFruta` (SQL_SCALAR_FUNCTION)

```sql

CREATE FUNCTION [dbo].[fRetornaTotalFruta]
	( @tipo		varchar(50),
	  @valor	int,
	  @producto varchar(50),
	  @empresa	int,
	  @fecha	date	  )
RETURNS float
AS
/***************************************************************************
Nombre: fRetornaTotalFruta
Tipo: Función
Desarrollado: Infos Tacnologia SAS
Fecha: 06/02/2015

Argumentos de entrada: Producto, periodo
Argumentos de salida: Saldo Final
Descripción: 
***************************************************************************/
BEGIN

declare @dato float

if (@tipo='D')
begin 
	set @dato = isnull((select SUM(pesoNeto)  FROM            bRegistroBascula a
	join iItems b on b.codigo=a.item and b.empresa=a.empresa
	WHERE   a.tipo = 'EMP' AND pesoNeto <> 0 AND b.referencia like 'FRU%' and convert(date,fechaProceso)=@fecha
	GROUP BY YEAR(CONVERT(varchar(50), fechaProceso)), CONVERT(varchar(50), CONVERT(date, fechaProceso))),0)
end

if (@tipo='M')
begin 
	set @dato = isnull((select SUM(pesoNeto)  FROM    bRegistroBascula a
	join iItems b on b.codigo=a.item and b.empresa=a.empresa
	WHERE   a.tipo = 'EMP' AND pesoNeto <> 0 AND b.referencia like 'FRU%' and MONTH(fechaproceso)=@valor and MONTH(fechaproceso)= YEAR(@fecha)
	GROUP BY YEAR(fechaProceso), MONTH(fechaProceso)),0)
end

if (@tipo='A')
begin 
	set @dato = isnull((select SUM(pesoNeto)  FROM            bRegistroBascula a
	join iItems b on b.codigo=a.item and b.empresa=a.empresa
	WHERE   a.tipo = 'EMP' AND pesoNeto <> 0 AND b.referencia like 'FRU%' and YEAR(fechaproceso)=@valor
	GROUP BY YEAR(fechaProceso), YEAR(fechaProceso)),0)
end
		
	return @dato

END


```

## `fRetornaUltimodiaMes` (SQL_SCALAR_FUNCTION)

```sql
CREATE FUNCTION [dbo].[fRetornaUltimodiaMes](@mes int, @año int,@tercero int , @SLN varchar(1))
RETURNS int
AS
BEGIN
	
	declare @dia int,@totalDias int

	set @totalDias = case when @SLN='X' then 30 else (select SUM(Dias_Cotizados_Salud) from vDatosSeguridadSocialParaRedondeo where mes=@mes and año=@año and codigo_Empleado=@tercero) end 
	set @dia = 30 -  case when @totalDias>30 then @totalDias else 30 end
	
	RETURN  @dia

END


```

## `fValorFormulaLab` (SQL_SCALAR_FUNCTION)

```sql

CREATE FUNCTION [dbo].[fValorFormulaLab]
	( @jerarquia	varchar(50),
	  @sentencia	varchar(250),
	  @objVar		varchar(500),
	  @modo			char(1),
	  @fecha		date,
	  @empresa int )
RETURNS varchar(250)
AS
/***************************************************************************
Nombre: fValorFormulaP
Tipo: Función
Desarrollado: Alirio Noche Arzuza
Fecha: 03/04/2013

Argumentos de entrada: Jerarquia, sentencia, objeto variable, modo de operación,
					   fecha
Argumentos de salida: Valor sentencia
Descripción: Retorna el valor de la sentencia indicada.
***************************************************************************/
BEGIN

	declare @valor	varchar(max),
			@var	varchar(max),
			@tipoFecha varchar(50),
			@char	char(1),					
			@len	int,
			@lenV	int,
			@lenV1	int,
			@i		int,
			@pos1	int,
			@pos2	int
	
	set @valor = ''	
	set @len = LEN( @sentencia )
	set @lenV = LEN( @objVar )
	set @i = 0
	set @pos1 = 0
	set @pos2 = 0	
	set @char = CHAR(39)

	if( SUBSTRING( @sentencia,1,1 ) = 'C' )
	begin
		select @valor = CONVERT( varchar(max),valor )	
		from pJerarquiaCaracteristica
		where
		jerarquia = @jerarquia and
		caracteristica = REPlACE( REPLACE( SUBSTRING( @sentencia,2,LEN( @sentencia ) ),'(','' ),')','' )
		
		set @valor = CONVERT( varchar(max),CONVERT( numeric(28,13),@valor ) )
	end
	else
	begin
		if( SUBSTRING( @sentencia,1,1 ) = 'V' )
		begin
			if( @modo = 'V' )
			begin
				set @valor = 1
			end				
			else
			begin
				while( @i <= @lenV )
				begin							
					select @pos1 = CHARINDEX( CHAR(124),@objVar,@pos2 + 1 )				
					select @pos2 = CHARINDEX( CHAR(124),@objVar,@pos1 + 1 )						
	
					if( @pos1 <> 0 )
					begin
						set @var = SUBSTRING( @objVar,@pos1 + 1,@pos2 - @pos1 - 1 )							
						set @lenV1 = LEN( @var )
						
						if( SUBSTRING( @var,1,CHARINDEX( CHAR(40),@var,0 ) - 1 ) = 
							REPlACE( REPLACE( SUBSTRING( @sentencia,2,LEN( @sentencia ) ),'(','' ),')','' ) )
						begin
							set @valor = SUBSTRING( 
								@var,
								CHARINDEX( CHAR(40),@var,0 ) + 1,
								@lenV1 - CHARINDEX( CHAR(40),@var,0 ) - 1 )
							
							set @valor = REPLACE( @valor,' ','' )							
							set @valor = CONVERT( varchar(max),CONVERT( numeric(28,13),@valor ) )							
						end								
					end
					else
					begin
						set @i = @lenV + 1		
					end
							
					set @i = @i + 1									
				end		
			end
		end
		else
		begin
			if( SUBSTRING( @sentencia,1,1 ) = 'N' )
			begin
				set @valor = SUBSTRING( @sentencia,2,@len - 1 )				
				set @valor = CONVERT( varchar(max),CONVERT( numeric(28,13),@valor ) )
			end
			else
			begin
				if( SUBSTRING( @sentencia,1,1 ) = 'S' )
				begin
					set @valor = SUBSTRING( @sentencia,2,@len - 1 )
				end
				else
				begin
					if( SUBSTRING( @sentencia,1,1 ) = 'F' )
					begin
						if( @modo = 'V' )
						begin
							set @valor = SUBSTRING( @sentencia,2,@len - 1 )
						end
						else
						begin
							if( SUBSTRING( @sentencia,1,4 ) = 'Fdbo' )
								begin
								set @len = LEN(@sentencia)
								set @sentencia = SUBSTRING( @sentencia,2,@len - 1 )				
								set @pos1 = 0									
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 )
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 + 1 )
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 + 1 )	
								set @tipoFecha =substring(@sentencia,@pos1+2,@len-8)
								SET @tipoFecha = replace(@tipoFecha,@char,'')
								SET @tipoFecha = replace(@tipoFecha,char(44),'')
								SET @tipoFecha = replace(@tipoFecha,convert(varchar(50),@empresa),'')
								set @valor = SUBSTRING( @sentencia,1,@pos1 )	
								
								if (@tipoFecha='FAN')
									set @fecha= dateadd(day,-1,@fecha)

								if (@tipoFecha='FSI')
									set @fecha= dateadd(day,1,@fecha)
							
								set @valor = @valor + @char + CONVERT( varchar(50),@fecha ) + @char	+ char(44)+ @char + convert(varchar(50),@empresa) + @char
							
							end
							else
							begin
								set @valor = SUBSTRING( @sentencia,2,@len - 1 )
							end								
						end							
						end
						else if( SUBSTRING( @sentencia,1,1 ) = 'L' )
						begin
						
							if( SUBSTRING( @sentencia,1,4 ) = 'Ldbo' )
							begin
								set @len = LEN(@sentencia)
								set @sentencia = SUBSTRING( @sentencia,2,@len - 1 )				
								set @pos1 = 0 
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 )
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 + 1 )
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 + 1 )									
								--set @pos1 = CHARINDEX( char(40),@sentencia,@pos1 + 1 )						
								set @valor = SUBSTRING( @sentencia,1,@pos1 )	   																				
								set @valor = @valor 					
							
							end
							
						end
				end
			end				
		end			
	end				

	return @valor
	
END


```

## `fValorFormulaP` (SQL_SCALAR_FUNCTION)

```sql
CREATE FUNCTION [dbo].[fValorFormulaP]
	( @sentencia	varchar(2500),
	  @objVar		varchar(5000),
	  @modo			char(1),
	  @fecha		date,
	   @empresa		int)
RETURNS varchar(2500)
AS
/***************************************************************************
Nombre: fValorFormulaP
Tipo: Función
Desarrollado: Infos Tacnologia SAS
Fecha: 06/02/2015

Argumentos de entrada: Jerarquia, sentencia, objeto variable, modo de operación,
					   fecha
Argumentos de salida: Valor sentencia
Descripción: Retorna el valor de la sentencia indicada.
***************************************************************************/
BEGIN

	declare @valor	varchar(max),
			@var	varchar(max),
			@tipoFecha varchar(50),
			@char	char(1),					
			@len	int,
			@lenV	int,
			@lenV1	int,
			@i		int,
			@pos1	int,
			@pos2	int
	
	set @valor = ''	
	set @len = LEN( @sentencia )
	set @lenV = LEN( @objVar )
	set @i = 0
	set @pos1 = 0
	set @pos2 = 0	
	set @char = CHAR(39)

	if( SUBSTRING( @sentencia,1,1 ) = 'V' )
		begin
			if( @modo = 'V' )
			begin
				set @valor = 1
			end				
			else
			begin
				while( @i <= @lenV )
				begin							
					select @pos1 = CHARINDEX( CHAR(124),@objVar,@pos2 + 1 )				
					select @pos2 = CHARINDEX( CHAR(124),@objVar,@pos1 + 1 )						
	
					if( @pos1 <> 0 )
					begin
						set @var = SUBSTRING( @objVar,@pos1 + 1,@pos2 - @pos1 - 1 )							
						set @lenV1 = LEN( @var )
						
						if( SUBSTRING( @var,1,CHARINDEX( CHAR(40),@var,0 ) - 1 ) = 
							REPlACE( REPLACE( SUBSTRING( @sentencia,2,LEN( @sentencia ) ),'(','' ),')','' ) )
						begin
							set @valor = SUBSTRING( 
								@var,
								CHARINDEX( CHAR(40),@var,0 ) + 1,
								@lenV1 - CHARINDEX( CHAR(40),@var,0 ) - 1 )
							
							set @valor = REPLACE( @valor,' ','' )							
							set @valor = CONVERT( varchar(max),CONVERT( numeric(28,13),@valor ) )							
						end								
					end
					else
					begin
						set @i = @lenV + 1		
					end
							
					set @i = @i + 1									
				end		
			end
		end
		else
		begin
			if( SUBSTRING( @sentencia,1,1 ) = 'N' )
			begin
				set @valor = SUBSTRING( @sentencia,2,@len - 1 )				
				set @valor = CONVERT( varchar(max),CONVERT( numeric(28,13),@valor ) )
			end
			else
			begin
				if( SUBSTRING( @sentencia,1,1 ) = 'S' )
				begin
					set @valor = SUBSTRING( @sentencia,2,@len - 1 )
				end
				else
				begin
					if( SUBSTRING( @sentencia,1,1 ) = 'F' )
					begin
						if( @modo = 'V' )
						begin
							set @valor = SUBSTRING( @sentencia,2,@len - 1 )
						end
						else
						begin
							if( SUBSTRING( @sentencia,1,4 ) = 'Fdbo' )
							begin
								set @len = LEN(@sentencia)
								set @sentencia = SUBSTRING( @sentencia,2,@len - 1 )				
								set @pos1 = 0									
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 )
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 + 1 )
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 + 1 )	
								set @tipoFecha =substring(@sentencia,@pos1+2,@len-8)
								SET @tipoFecha = replace(@tipoFecha,@char,'')
								SET @tipoFecha = replace(@tipoFecha,char(44),'')
								SET @tipoFecha = replace(@tipoFecha,convert(varchar(50),@empresa),'')
								set @valor = SUBSTRING( @sentencia,1,@pos1 )	
								
								if (@tipoFecha='FAN')
									set @fecha= dateadd(day,-1,@fecha)

								if (@tipoFecha='FSI')
									set @fecha= dateadd(day,1,@fecha)
							
								set @valor = @valor + @char + CONVERT( varchar(50),@fecha ) + @char	+ char(44)+ @char + convert(varchar(50),@empresa) + @char
							
							end
							else
							begin
								set @valor = SUBSTRING( @sentencia,2,@len - 1 )
							end								
						end							
						end
						else if( SUBSTRING( @sentencia,1,1 ) = 'L' )
						begin
							if( SUBSTRING( @sentencia,1,4 ) = 'Ldbo' )
							begin
								set @len = LEN(@sentencia)
								set @sentencia = SUBSTRING( @sentencia,2,@len - 1 )				
								set @pos1 = 0 
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 )
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 + 1 )
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 + 1 )
								set @pos1 = CHARINDEX( char(44),@sentencia,@pos1 + 1 )									
								set @valor = SUBSTRING( @sentencia,1,@pos1 )	   																				
								set @valor = @valor 					
							
							end
							
						end
						
						
				end
			end				
		end			
		

	return @valor
	
END
```

## `f_numeroletras` (SQL_SCALAR_FUNCTION)

```sql
CREATE FUNCTION [dbo].[f_numeroletras] 
	( @Numero numeric )
RETURNS varchar(500)
AS
/***************************************************************************
Nombre: f_numeroletras
Tipo: Función
Desarrollado: Infos Tecnologia SAS

Argumentos de entrada: Valor Numérico
Argumentos de salida: Valor en letras
Descripción: Obtiene el valor en letras de una cantidad numérica
***************************************************************************/
BEGIN
  DECLARE @lnEntero INT,
    @lcRetorno VARCHAR(512),
    @lnTerna INT,
    @lcMiles VARCHAR(512),
    @lcCadena VARCHAR(512),
    @lnUnidades INT,
    @lnDecenas INT,
    @lnCentenas INT,
    @lnFraccion INT
 
  SELECT @lnEntero = CAST(@Numero AS INT),
    @lnFraccion = (@Numero - @lnEntero) * 100,
    @lcRetorno = '',
    @lnTerna = 1
 
  WHILE @lnEntero > 0
  BEGIN /* WHILE */
 
    -- Recorro columna por columna
    SELECT @lcCadena = ''
    SELECT @lnUnidades = @lnEntero % 10
    SELECT @lnEntero = CAST(@lnEntero/10 AS INT)
    SELECT @lnDecenas = @lnEntero % 10
    SELECT @lnEntero = CAST(@lnEntero/10 AS INT)
    SELECT @lnCentenas = @lnEntero % 10
    SELECT @lnEntero = CAST(@lnEntero/10 AS INT)
 
    -- Analizo las unidades
    SELECT @lcCadena =
    CASE /* UNIDADES */
      WHEN @lnUnidades = 1 AND @lnTerna = 1 THEN 'UNO ' + @lcCadena
      WHEN @lnUnidades = 1 AND @lnTerna <> 1 THEN 'UN ' + @lcCadena
      WHEN @lnUnidades = 2 THEN 'DOS ' + @lcCadena
      WHEN @lnUnidades = 3 THEN 'TRES ' + @lcCadena
      WHEN @lnUnidades = 4 THEN 'CUATRO ' + @lcCadena
      WHEN @lnUnidades = 5 THEN 'CINCO ' + @lcCadena
      WHEN @lnUnidades = 6 THEN 'SEIS ' + @lcCadena
      WHEN @lnUnidades = 7 THEN 'SIETE ' + @lcCadena
      WHEN @lnUnidades = 8 THEN 'OCHO ' + @lcCadena
      WHEN @lnUnidades = 9 THEN 'NUEVE ' + @lcCadena
      ELSE @lcCadena
    END /* UNIDADES */
 
    -- Analizo las decenas
    SELECT @lcCadena =
    CASE /* DECENAS */
      WHEN @lnDecenas = 1 THEN
        CASE @lnUnidades
          WHEN 0 THEN 'DIEZ '
          WHEN 1 THEN 'ONCE '
          WHEN 2 THEN 'DOCE '
          WHEN 3 THEN 'TRECE '
          WHEN 4 THEN 'CATORCE '
          WHEN 5 THEN 'QUINCE '
          ELSE 'DIECI' + @lcCadena
        END
      WHEN @lnDecenas = 2 AND @lnUnidades = 0 THEN 'VEINTE ' + @lcCadena
      WHEN @lnDecenas = 2 AND @lnUnidades <> 0 THEN 'VEINTI' + @lcCadena
      WHEN @lnDecenas = 3 AND @lnUnidades = 0 THEN 'TREINTA ' + @lcCadena
      WHEN @lnDecenas = 3 AND @lnUnidades <> 0 THEN 'TREINTA Y ' + @lcCadena
      WHEN @lnDecenas = 4 AND @lnUnidades = 0 THEN 'CUARENTA ' + @lcCadena
      WHEN @lnDecenas = 4 AND @lnUnidades <> 0 THEN 'CUARENTA Y ' + @lcCadena
      WHEN @lnDecenas = 5 AND @lnUnidades = 0 THEN 'CINCUENTA ' + @lcCadena
      WHEN @lnDecenas = 5 AND @lnUnidades <> 0 THEN 'CINCUENTA Y ' + @lcCadena
      WHEN @lnDecenas = 6 AND @lnUnidades = 0 THEN 'SESENTA ' + @lcCadena
      WHEN @lnDecenas = 6 AND @lnUnidades <> 0 THEN 'SESENTA Y ' + @lcCadena
      WHEN @lnDecenas = 7 AND @lnUnidades = 0 THEN 'SETENTA ' + @lcCadena
      WHEN @lnDecenas = 7 AND @lnUnidades <> 0 THEN 'SETENTA Y ' + @lcCadena
      WHEN @lnDecenas = 8 AND @lnUnidades = 0 THEN 'OCHENTA ' + @lcCadena
      WHEN @lnDecenas = 8 AND @lnUnidades <> 0 THEN 'OCHENTA Y ' + @lcCadena
      WHEN @lnDecenas = 9 AND @lnUnidades = 0 THEN 'NOVENTA ' + @lcCadena
      WHEN @lnDecenas = 9 AND @lnUnidades <> 0 THEN 'NOVENTA Y ' + @lcCadena
      ELSE @lcCadena
    END /* DECENAS */
 
    -- Analizo las centenas
    SELECT @lcCadena =
    CASE /* CENTENAS */
      WHEN @lnCentenas = 1 AND @lnUnidades = 0 AND @lnDecenas = 0 THEN 'CIEN ' + @lcCadena
      WHEN @lnCentenas = 1 AND NOT(@lnUnidades = 0 AND @lnDecenas = 0) THEN 'CIENTO ' + @lcCadena
      WHEN @lnCentenas = 2 THEN 'DOSCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 3 THEN 'TRESCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 4 THEN 'CUATROCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 5 THEN 'QUINIENTOS ' + @lcCadena
      WHEN @lnCentenas = 6 THEN 'SEISCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 7 THEN 'SETECIENTOS ' + @lcCadena
      WHEN @lnCentenas = 8 THEN 'OCHOCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 9 THEN 'NOVECIENTOS ' + @lcCadena
      ELSE @lcCadena
    END /* CENTENAS */
 
    -- Analizo los millares
    SELECT @lcCadena =
    CASE /* TERNA */
      WHEN @lnTerna = 1 THEN @lcCadena
      WHEN @lnTerna = 2 AND (@lnUnidades + @lnDecenas + @lnCentenas <> 0) THEN @lcCadena + ' MIL '
      WHEN @lnTerna = 3 AND (@lnUnidades + @lnDecenas + @lnCentenas <> 0) AND
        @lnUnidades = 1 AND @lnDecenas = 0 AND @lnCentenas = 0 THEN @lcCadena + ' MILLON '
      WHEN @lnTerna = 3 AND (@lnUnidades + @lnDecenas + @lnCentenas <> 0) AND
        NOT (@lnUnidades = 1 AND @lnDecenas = 0 AND @lnCentenas = 0) THEN @lcCadena + ' MILLONES '
      WHEN @lnTerna = 4 AND (@lnUnidades + @lnDecenas + @lnCentenas <> 0) THEN @lcCadena + ' MIL MILLONES '
      ELSE ''
    END /* MILLARES */
 
    -- Armo el retorno columna a columna
    SELECT @lcRetorno = @lcCadena + @lcRetorno
    SELECT @lnTerna = @lnTerna + 1
 
  END /* WHILE */
 
  IF @lnTerna = 1
  begin
    SELECT @lcRetorno = 'CERO'
  end

  SELECT @lcRetorno = RTRIM(@lcRetorno) --+ ' PESOS MCTE.'
 
  
	return 	 @lcRetorno

END
```

## `f_numeroletras_Pago` (SQL_SCALAR_FUNCTION)

```sql
CREATE FUNCTION [dbo].[f_numeroletras_Pago] 
	( @Numero numeric )
RETURNS varchar(500)
AS
/***************************************************************************
Nombre: f_numeroletras
Tipo: Función
Desarrollado: Infos Tecnologia SAS

Argumentos de entrada: Valor Numérico
Argumentos de salida: Valor en letras
Descripción: Obtiene el valor en letras de una cantidad numérica
***************************************************************************/
BEGIN
  DECLARE @lnEntero INT,
    @lcRetorno VARCHAR(512),
    @lnTerna INT,
    @lcMiles VARCHAR(512),
    @lcCadena VARCHAR(512),
    @lnUnidades INT,
    @lnDecenas INT,
    @lnCentenas INT,
    @lnFraccion INT
 
  SELECT @lnEntero = CAST(@Numero AS INT),
    @lnFraccion = (@Numero - @lnEntero) * 100,
    @lcRetorno = '',
    @lnTerna = 1
 
  WHILE @lnEntero > 0
  BEGIN /* WHILE */
 
    -- Recorro columna por columna
    SELECT @lcCadena = ''
    SELECT @lnUnidades = @lnEntero % 10
    SELECT @lnEntero = CAST(@lnEntero/10 AS INT)
    SELECT @lnDecenas = @lnEntero % 10
    SELECT @lnEntero = CAST(@lnEntero/10 AS INT)
    SELECT @lnCentenas = @lnEntero % 10
    SELECT @lnEntero = CAST(@lnEntero/10 AS INT)
 
    -- Analizo las unidades
    SELECT @lcCadena =
    CASE /* UNIDADES */
      WHEN @lnUnidades = 1 AND @lnTerna = 1 THEN 'UNO ' + @lcCadena
      WHEN @lnUnidades = 1 AND @lnTerna <> 1 THEN 'UN ' + @lcCadena
      WHEN @lnUnidades = 2 THEN 'DOS ' + @lcCadena
      WHEN @lnUnidades = 3 THEN 'TRES ' + @lcCadena
      WHEN @lnUnidades = 4 THEN 'CUATRO ' + @lcCadena
      WHEN @lnUnidades = 5 THEN 'CINCO ' + @lcCadena
      WHEN @lnUnidades = 6 THEN 'SEIS ' + @lcCadena
      WHEN @lnUnidades = 7 THEN 'SIETE ' + @lcCadena
      WHEN @lnUnidades = 8 THEN 'OCHO ' + @lcCadena
      WHEN @lnUnidades = 9 THEN 'NUEVE ' + @lcCadena
      ELSE @lcCadena
    END /* UNIDADES */
 
    -- Analizo las decenas
    SELECT @lcCadena =
    CASE /* DECENAS */
      WHEN @lnDecenas = 1 THEN
        CASE @lnUnidades
          WHEN 0 THEN 'DIEZ '
          WHEN 1 THEN 'ONCE '
          WHEN 2 THEN 'DOCE '
          WHEN 3 THEN 'TRECE '
          WHEN 4 THEN 'CATORCE '
          WHEN 5 THEN 'QUINCE '
          ELSE 'DIECI' + @lcCadena
        END
      WHEN @lnDecenas = 2 AND @lnUnidades = 0 THEN 'VEINTE ' + @lcCadena
      WHEN @lnDecenas = 2 AND @lnUnidades <> 0 THEN 'VEINTI' + @lcCadena
      WHEN @lnDecenas = 3 AND @lnUnidades = 0 THEN 'TREINTA ' + @lcCadena
      WHEN @lnDecenas = 3 AND @lnUnidades <> 0 THEN 'TREINTA Y ' + @lcCadena
      WHEN @lnDecenas = 4 AND @lnUnidades = 0 THEN 'CUARENTA ' + @lcCadena
      WHEN @lnDecenas = 4 AND @lnUnidades <> 0 THEN 'CUARENTA Y ' + @lcCadena
      WHEN @lnDecenas = 5 AND @lnUnidades = 0 THEN 'CINCUENTA ' + @lcCadena
      WHEN @lnDecenas = 5 AND @lnUnidades <> 0 THEN 'CINCUENTA Y ' + @lcCadena
      WHEN @lnDecenas = 6 AND @lnUnidades = 0 THEN 'SESENTA ' + @lcCadena
      WHEN @lnDecenas = 6 AND @lnUnidades <> 0 THEN 'SESENTA Y ' + @lcCadena
      WHEN @lnDecenas = 7 AND @lnUnidades = 0 THEN 'SETENTA ' + @lcCadena
      WHEN @lnDecenas = 7 AND @lnUnidades <> 0 THEN 'SETENTA Y ' + @lcCadena
      WHEN @lnDecenas = 8 AND @lnUnidades = 0 THEN 'OCHENTA ' + @lcCadena
      WHEN @lnDecenas = 8 AND @lnUnidades <> 0 THEN 'OCHENTA Y ' + @lcCadena
      WHEN @lnDecenas = 9 AND @lnUnidades = 0 THEN 'NOVENTA ' + @lcCadena
      WHEN @lnDecenas = 9 AND @lnUnidades <> 0 THEN 'NOVENTA Y ' + @lcCadena
      ELSE @lcCadena
    END /* DECENAS */
 
    -- Analizo las centenas
    SELECT @lcCadena =
    CASE /* CENTENAS */
      WHEN @lnCentenas = 1 AND @lnUnidades = 0 AND @lnDecenas = 0 THEN 'CIEN ' + @lcCadena
      WHEN @lnCentenas = 1 AND NOT(@lnUnidades = 0 AND @lnDecenas = 0) THEN 'CIENTO ' + @lcCadena
      WHEN @lnCentenas = 2 THEN 'DOSCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 3 THEN 'TRESCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 4 THEN 'CUATROCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 5 THEN 'QUINIENTOS ' + @lcCadena
      WHEN @lnCentenas = 6 THEN 'SEISCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 7 THEN 'SETECIENTOS ' + @lcCadena
      WHEN @lnCentenas = 8 THEN 'OCHOCIENTOS ' + @lcCadena
      WHEN @lnCentenas = 9 THEN 'NOVECIENTOS ' + @lcCadena
      ELSE @lcCadena
    END /* CENTENAS */
 
    -- Analizo los millares
    SELECT @lcCadena =
    CASE /* TERNA */
      WHEN @lnTerna = 1 THEN @lcCadena
      WHEN @lnTerna = 2 AND (@lnUnidades + @lnDecenas + @lnCentenas <> 0) THEN @lcCadena + ' MIL '
      WHEN @lnTerna = 3 AND (@lnUnidades + @lnDecenas + @lnCentenas <> 0) AND
        @lnUnidades = 1 AND @lnDecenas = 0 AND @lnCentenas = 0 THEN @lcCadena + ' MILLON '
      WHEN @lnTerna = 3 AND (@lnUnidades + @lnDecenas + @lnCentenas <> 0) AND
        NOT (@lnUnidades = 1 AND @lnDecenas = 0 AND @lnCentenas = 0) THEN @lcCadena + ' MILLONES '
      WHEN @lnTerna = 4 AND (@lnUnidades + @lnDecenas + @lnCentenas <> 0) THEN @lcCadena + ' MIL MILLONES '
      ELSE ''
    END /* MILLARES */
 
    -- Armo el retorno columna a columna
    SELECT @lcRetorno = @lcCadena + @lcRetorno
    SELECT @lnTerna = @lnTerna + 1
 
  END /* WHILE */
 
  IF @lnTerna = 1
  begin
    SELECT @lcRetorno = 'CERO'
  end

  SELECT @lcRetorno = RTRIM(@lcRetorno) + ' PESOS CON 00/100 M/L.'
 
  
	return 	 @lcRetorno

END
```

## `f_retornCodigoMenu` (SQL_SCALAR_FUNCTION)

```sql
create FUNCTION [dbo].[f_retornCodigoMenu] 
	( @rowid int )
RETURNS varchar(50)
AS
begin
declare @padre varchar(50), @codigo varchar(50)
	select @padre = padre , @codigo = id from sMenus
	where rowid=@rowid
	while(@padre is not null)
	begin
		set @codigo = (select id from sMenus
		where rowid=@padre) + @codigo
		set @padre =  (select padre from sMenus
		where rowid=@padre)
	end
  
	return 	 @codigo
END


```

## `fn_diagramobjects` (SQL_SCALAR_FUNCTION)

```sql

	CREATE FUNCTION dbo.fn_diagramobjects() 
	RETURNS int
	WITH EXECUTE AS N'dbo'
	AS
	BEGIN
		declare @id_upgraddiagrams		int
		declare @id_sysdiagrams			int
		declare @id_helpdiagrams		int
		declare @id_helpdiagramdefinition	int
		declare @id_creatediagram	int
		declare @id_renamediagram	int
		declare @id_alterdiagram 	int 
		declare @id_dropdiagram		int
		declare @InstalledObjects	int

		select @InstalledObjects = 0

		select 	@id_upgraddiagrams = object_id(N'dbo.sp_upgraddiagrams'),
			@id_sysdiagrams = object_id(N'dbo.sysdiagrams'),
			@id_helpdiagrams = object_id(N'dbo.sp_helpdiagrams'),
			@id_helpdiagramdefinition = object_id(N'dbo.sp_helpdiagramdefinition'),
			@id_creatediagram = object_id(N'dbo.sp_creatediagram'),
			@id_renamediagram = object_id(N'dbo.sp_renamediagram'),
			@id_alterdiagram = object_id(N'dbo.sp_alterdiagram'), 
			@id_dropdiagram = object_id(N'dbo.sp_dropdiagram')

		if @id_upgraddiagrams is not null
			select @InstalledObjects = @InstalledObjects + 1
		if @id_sysdiagrams is not null
			select @InstalledObjects = @InstalledObjects + 2
		if @id_helpdiagrams is not null
			select @InstalledObjects = @InstalledObjects + 4
		if @id_helpdiagramdefinition is not null
			select @InstalledObjects = @InstalledObjects + 8
		if @id_creatediagram is not null
			select @InstalledObjects = @InstalledObjects + 16
		if @id_renamediagram is not null
			select @InstalledObjects = @InstalledObjects + 32
		if @id_alterdiagram  is not null
			select @InstalledObjects = @InstalledObjects + 64
		if @id_dropdiagram is not null
			select @InstalledObjects = @InstalledObjects + 128
		
		return @InstalledObjects 
	END
	
```
