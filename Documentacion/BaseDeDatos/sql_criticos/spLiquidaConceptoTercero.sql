-- Procedimiento: spLiquidaConceptoTercero
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE proc [dbo].[spLiquidaConceptoTercero]
@empresa int,
@año int,
@concepto varchar(50),
@tercero int,
@cantidad decimal(18,2),
@valor money,
@ND int,
@fechaNovedad date,
@formaPago int,
@valorResultado money output,
@cantidadResultado decimal(18,3) output,
@porcentajeResultado decimal(18,3) output
as
declare @SMLV money,@SMLVD money,@JD int,@Salario money
declare @base money,@conceptoBase varchar(50),@mPorcentaje bit, @valorPorcentaje decimal(18,3),@tipoUnidad int,@domingo date,@lunes date,@conceptoTransporte varchar(50),@signo int
declare @salarioDiario money= 0, @salarioHora money,@VU money,@controlConcepto int,@restaTransporte bit,@quitaDomingo bit,@conceptoSueldo varchar(50),@conceptoDomingo varchar(50)

select @SMLV=vSalarioMinimo, @SMLVD=vSalarioMinimo/30 from nParametrosAno where empresa=@empresa and ano= @año

SET @lunes = DATEADD(WK, DATEDIFF(WK,0,dateadd(day,-1,@fechaNovedad)), 0)
SET @domingo = DATEADD(day,6,@lunes)

select @conceptoSueldo=sueldo, @conceptoDomingo=ganaDomingo, @conceptoTransporte=subsidioTransporte,@JD=jornadaDiaria from nParametrosGeneral where empresa=@empresa
select top 1 @Salario= salario from nContratos where empresa=@empresa and tercero=@tercero 
and id in (select max(id) from nContratos where empresa=@empresa and tercero=@tercero)
--and activo=1

select @conceptoBase= base,@mPorcentaje=validaPorcentaje,@valorPorcentaje=porcentaje,@tipoUnidad =tipoLiquidacion,@signo=signo,
@controlConcepto=controlConcepto, @restaTransporte=descuentaTransporte,@quitaDomingo= descuentaDomingo
from nConcepto where codigo=@concepto and empresa=@empresa

if @valor= 0
begin
select @valor = valor
from nConcepto where codigo=@concepto and empresa=@empresa  -- no liquidaba con el valor del concepto registrado en la nomina ricardo gomez 25/03/2017
end

	
	set @salarioDiario = @Salario/30
	set @salarioHora = @salarioDiario / @JD

	if (@tipoUnidad=1)
	begin		
		set @VU = @salarioHora 
	end
	if (@tipoUnidad=2)
	begin	 			
		set @VU = @salarioDiario		  	
	end


if @conceptoBase is null
begin
	if @cantidad=0
	begin
		set @valorResultado=@valor
		set @cantidadResultado=1
		set @porcentajeResultado = 0
	end
	if @cantidad<>0
	begin
		set @valorResultado=@valor* @cantidad
		set @cantidadResultado=@cantidad
		set @porcentajeResultado = 0
	end

end
else
begin
	set @base = isnull((select SUM(valorTotal) from tmpliquidacionNomina where empresa=@empresa and tercero=@tercero and concepto=@conceptoBase),0)
	if @mPorcentaje=1 and @base<>0
	begin
		set @salarioDiario = @base/@ND
		set @salarioHora = @salarioDiario / @JD
		if (@tipoUnidad=1)
			set @VU = @salarioHora 
		if (@tipoUnidad=2)
			set @VU = @salarioDiario		  	
		if exists(select * from nParametrosGeneral where ganaDomingo =@concepto and empresa=@empresa)
		begin
			set @salarioDiario = @Salario/30
			set @salarioHora = @salarioDiario / @JD
			if (@tipoUnidad=1)
				set @VU = @salarioHora 
			if (@tipoUnidad=2)
				set @VU = @salarioDiario		  	
		end
		set @valorResultado = (@VU * (@valorPorcentaje/100))*@cantidad
		set @cantidadResultado=@cantidad
		set @porcentajeResultado = @valorPorcentaje
	end
	else
	begin
		if @mPorcentaje=1
		begin
			set @valorResultado = (@VU * (@valorPorcentaje/100))*@cantidad
			set @cantidadResultado=@cantidad
			set @porcentajeResultado = @valorPorcentaje
		end
	end
end

	if(@controlConcepto=1)
	begin
		update tmpliquidacionNomina set
		valorTotal=valorTotal+@valorResultado,
		cantidad=cantidad+@cantidad
		where tercero=@tercero and empresa=@empresa and concepto=@conceptoSueldo
		if @signo=2
			set @valorResultado=0
	end
	if(@controlConcepto=2)
	begin
			update tmpliquidacionNomina set
			valorTotal=valorTotal-@valorResultado,
			cantidad=cantidad-@cantidad
			where tercero=@tercero and empresa=@empresa and concepto=@conceptoSueldo
		if(@quitaDomingo=1)
		begin
			update tmpliquidacionNomina set
			valorTotal=valorTotal-@VU,
			cantidad=cantidad-1
			where tercero=@tercero and empresa=@empresa and concepto=@conceptoSueldo
		end
		if @signo=2
			set @valorResultado=0
	end
	if(@restaTransporte=1)
	begin
		update tmpliquidacionNomina set
		valorTotal=valorTotal- ((valorTotal/cantidad)*@cantidad),
		cantidad=cantidad-@cantidad
		where tercero=@tercero and empresa=@empresa and concepto=@conceptoTransporte
		if(@quitaDomingo=1)
		begin
			update tmpliquidacionNomina set
			valorTotal=valorTotal- ((valorTotal/cantidad)*@cantidad),
			cantidad=cantidad-@cantidad
			where tercero=@tercero and empresa=@empresa and concepto=@conceptoTransporte
		end
	end
