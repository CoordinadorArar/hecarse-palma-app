-- Procedimiento: spCompletaTiquete
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

create PROCEDURE [dbo].[spCompletaTiquete]
	@numero			varchar(50),
	@tipoTiquete	varchar(50),
	@tipoTrans		varchar(50),
	@fechaProceso	datetime,
	@pesoBruto		float,
	@pesoTara		float,	
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
	@tiqueteN		varchar(50) output,
	@retorno		int output,
	@empresa		int,
	@pesoDescuento	int
AS
/***************************************************************************
Nombre: spAnulaModificaTiquete
Tipo: Procedimiento Almacenado
Desarrollado: Infos Tecnologia SaS

Argumentos de entrada: Número de tiquete, tipo tiquete
Argumentos de salida: 0 Si es exitoso,
					  1 Si es exitoso.
Descripción: Realiza la modificación y anulación del tiquete de báscula seleccionado.
*****************************************************************************/

	declare @error		int,
			@tipoTransN	varchar(50),
			@tipo		varchar(50),
			@fechaTara	datetime,
			@fechaBruto	datetime,
			@fechaNeto	datetime
	
	set @error = 0
	set @fechaNeto = getdate()

	select @tipo = tipo
	from bRegistroBascula
	where	numero = @numero and empresa=@empresa
	
	if( ( select naturaleza from gTipoTransaccion where  codigo = @tipo and empresa=@empresa ) = 1 )
	begin
		select @fechaBruto = fechaBruto
		from bRegistroBascula
		where		tipo = @tipo and		numero = @numero and empresa=@empresa
		
		set @fechaTara = getdate()
	end
	else
	begin
		select @fechaTara = fechaTara
		from bRegistroBascula
		where
		tipo = @tipo and
		numero = @numero and empresa=@empresa
		
		set @fechaBruto = getdate()		
	end		  

	execute spRetornaConsecutivoTransaccion @tipoTiquete, @empresa ,@tiqueteN output
	
	begin tran Actualiza		
				
		update bRegistroBascula
		set
		tiquete = @tiqueteN,
		fechaProceso = @fechaProceso,
		pesoBruto = @pesoBruto,
		pesoTara = @pesoTara,
		pesoDescuento=@pesoDescuento,
		pesoNeto = ( @pesoBruto - @pesoTara  ),
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
		estado = 'SP',
		fechaNeto = @fechaNeto,
		fechaBruto = @fechaBruto,
		fechaTara = @fechaTara
		where
		tipo = @tipo and
		numero = @numero and empresa=@empresa
		
		set @error = ( @error + @@ERROR )
		
		update gTipoTransaccion		set
		actual = actual + 1
		where	codigo = @tipoTiquete	and empresa=@empresa		
		
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
