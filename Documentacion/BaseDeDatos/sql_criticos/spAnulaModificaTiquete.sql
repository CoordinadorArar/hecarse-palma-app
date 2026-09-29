-- Procedimiento: spAnulaModificaTiquete
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE PROCEDURE [dbo].[spAnulaModificaTiquete]
	@tiquete		varchar(50),
	@tipoTiquete	varchar(50),
	@tipoTrans		varchar(50),
	@fechaProceso	datetime,
	@pesoBruto		float,
	@pesoTara		float,
	@pesoDes		float,
	@vehiculo		varchar(50),
	@remolque		varchar(50),
	@producto		varchar(50),
	@procedencia	varchar(50),
	@finca			varchar(50),
	@racimos		int,
	@sacos			int,
	@pesoSacos		float,
	@sellos			varchar(250),
	@funcionario	varchar(50),
	@empresa		int,
	@nombreConductor varchar(50),
	@codigoConductor varchar(550),
	@remisionPlanta varchar(50),
	@remisionComercializadora varchar(50),
	@anulaTiquete bit,
	@tiqueteN		varchar(50) output,
	@tipoRemision varchar(50),
	@observacion varchar(550),
	@retorno		int output
AS
/***************************************************************************
Nombre: spAnulaModificaTiquete
Tipo: Procedimiento Almacenado
Desarrollado: Infos Tecnologia SAS

Argumentos de entrada: Número de tiquete, tipo tiquete
Argumentos de salida: 0 Si es exitoso,
					  1 Si es exitoso.
Descripción: Realiza la modificación y anulación del tiquete de báscula seleccionado.
*****************************************************************************/

	declare @error		int,
			@tipoTransN	varchar(50),
			@tipo		varchar(50),
			@numero		varchar(50),
			@tipoRemPlanta varchar(50),
			@tipoAnulado varchar(50),
			@tipoRemCom varchar(50),
			@remisionComerAnt varchar(50)
	
	set @error = 0

	select @tipo = tipo,@numero = numero
	from bRegistroBascula
	where
	tiquete = @tiquete and empresa=@empresa

	set @tipoRemPlanta = @tipoRemision
	set @tipoAnulado = isnull((select anulado from gParametrosGenerales where empresa=@empresa),'')
	set @tipoRemCom = isnull((select remisionComer from gParametrosGenerales where empresa=@empresa),'')

	

	if @anulaTiquete = 1
	begin
	execute spRetornaConsecutivoTransaccion @tipoTiquete,@empresa,@tiqueteN output
	end
	else
	begin
		set @tiqueteN = @tiquete
		execute spRetornaConsecutivoTransaccion @tipoRemPlanta,@empresa,@remisionPlanta output
	end 

	execute spRetornaConsecutivoTransaccion @tipoAnulado,@empresa,@tipoTransN output
	
	begin tran Actualiza		
	
		insert bRegistroBascula(
		empresa,
		fecha,
		tipo,
		numero,
		tiquete,
		remision,
		pesoBruto,
		pesodescuento,
		pesoTara,
		pesoNeto,
		fechaBruto,
		fechaTara,
		fechaNeto,
		estado,
		tipoVehiculo,
		vehiculo,
		remolque,
		item,
		procedencia,
		finca,
		usuario,
		fechaProceso,
		racimos,
		bodega,
		sacos,
		urlTiquete,
		analisisRegistrado,
		pesoSacos,
		sellos,
		tipoDescargue,
		codigoConductor,
		nombreConductor,
		tercero,
		vehiculoInterno,
		remisionPlanta,
		remisionComercializadora,
		observacion )
		select
		empresa,
		fecha,
		'ANULADO',
		@tipoTransN,
		tiquete,
		remision,
		pesoBruto,
		pesodescuento,
		pesoTara,
		pesoNeto,
		fechaBruto,
		fechaTara,
		fechaNeto,
		estado,
		tipoVehiculo,
		vehiculo,
		remolque,
		item,
		procedencia,
		finca,
		usuario,
		fechaProceso,
		racimos,
		bodega,
		sacos,
		urlTiquete,
		analisisRegistrado,
		pesoSacos,
		sellos,
		tipoDescargue,
		codigoConductor,
		nombreConductor,
		tercero,
		vehiculoInterno,
		remisionPlanta,
		remisionComercializadora,
		@observacion
		from bRegistroBascula
		where
		tipo = @tipo and
		numero = @numero and empresa=@empresa

		set @error = ( @@ERROR )	
	
		if @anulaTiquete=0
		begin
		update gTipoTransaccion
		set
		actual = actual + 1
		where
		codigo = @tipoRemPlanta
		and empresa=@empresa
		end
		
		 
		update bRegistroBascula
		set
		tiquete = @tiqueteN,
		fechaProceso = @fechaProceso,
		pesoBruto = @pesoBruto,
		pesoTara = @pesoTara,
		pesoDescuento=@pesoDes,
		pesoNeto = ( @pesoBruto - @pesoTara -@pesoDes ),
		vehiculo = @vehiculo,
		remolque = @remolque,
		item = @producto,
		procedencia = @procedencia,
		finca = @finca,
		racimos = @racimos,
		sacos = @sacos,
		pesoSacos = @pesoSacos,
		sellos = @sellos,
		usuario = @funcionario,
		codigoConductor=@codigoConductor,
		nombreConductor=@nombreConductor,
		remisionPlanta=@remisionPlanta,
		remisionComercializadora=@remisionComercializadora,
		observacion=@observacion
		where
		tipo = @tipo and
		numero = @numero 
		and empresa=@empresa
		
		set @error = ( @error + @@ERROR )
		
		if @anulaTiquete = 1
		begin
		update gTipoTransaccion
		set
		actual = actual + 1
		where
		codigo = @tipoTiquete
		and empresa=@empresa
		end
		else
		
		set @error = ( @error + @@ERROR )
		
		update gTipoTransaccion
		set
		actual = actual + 1
		where
		codigo = @tipoTrans		
		and empresa=@empresa
		
		set @error = ( @error + @@ERROR )	
		
		update gTipoTransaccion
		set
		actual = actual + 1
		where
		codigo = 'ANULADO'	
		and empresa=@empresa
		
		set @error = ( @error + @@ERROR )		
		
	if( @error = 0 )
	begin
		set @retorno = 0
		commit tran Actualiza
	end		
	else
	begin
		set @retorno = 1
		rollback tran Actualiza
	end





