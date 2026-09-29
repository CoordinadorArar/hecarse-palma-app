-- Procedimiento: spCalculaTipoLiquidacion
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura


CREATE proc [dbo].[spCalculaTipoLiquidacion] 
 @JD int,
 @salario money,
 @tipoLiquidacion int,
 @noDiasLiquidacion int,
 @FI date,
 @FF date,
 @FIPN date,
 @FFPN date,
 @ND int output,
 @VU money output
 as

	declare @salarioDiario money= 0, @salarioHora money
	-- esta demas
	 set @ND = @noDiasLiquidacion
	-- valor del salario diario
	set @salarioDiario = @salario/30
	-- valor del salario por hora
	set @salarioHora = @salarioDiario / @JD

	if (@tipoLiquidacion=1)
	begin		
		set @VU = @salarioHora 
	end

	if (@tipoLiquidacion=2)
	begin	 			
		set @VU = @salarioDiario		  	
	end
