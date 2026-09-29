-- Procedimiento: spLiquidacionNominaPeriodo
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE proc [dbo].[spLiquidacionNominaPeriodo]
 @año int,
 @mes int,
 @periodo int,
 @empresa int,
@ccosto varchar(50),
@empleado varchar(50) ,
@fecheGeneral date,
@formaLiquidacion int,
@retorno int output,
@nombreTercero varchar(550) output
as
set @mes = (select top 1 mes from nPeriodoDetalle where noPeriodo=@periodo and año=@año and empresa=@empresa)
delete from tmpliquidacionNomina where empresa=@empresa
declare  @conceptosLiquidar table 
(acumulada bit,
concepto varchar(50),
tipoLiquidacion varchar(6),
signo int,
baseSeguridadSocial bit,
validaPorcentaje bit,
porcentaje float,
valor money,
baseEmbargo bit,
tipoNovedadLiquida  bit,
cantidadNovedad decimal(18,2),
fecha date,
prioridad int,
registro int
)
declare @tercero int, @codigo varchar(50),@validaTurno bit ,@departamento varchar(50), @centroCosto varchar(50), @tipoNomina varchar(10),
@tiempoBasico int, @fechaIngreso date,@fechaContratoHasta date, @salario money, @cantidadHoras int, 
@salarioIntegral bit, @terminoContrato varchar(50),@diasContrato int, @Contratofijo bit, 
@noContrato int,@conceptoGanaDomingo varchar(50),@tipoNovedadLiquida bit,@cantidadNovedad decimal(18,2),@porc decimal(18,3),@formaPago int ,  
@acumulada bit ,@concepto varchar(50),@SMLV money, @NSST int, @ST money,@FIPN date, @FFPN date,  
@NSI int, @JD int ,@fechaInicialLiquidacion date, @fechaFinalLiquidacion date, 
@fechaNovedadNomina date,@tipoLiquidacion int,  @signo int, @base varchar(50), 
@porcentajeConcepto decimal(18,3),@valor money, @basePrimas bit, 
@baseCajaCompensacion bit, @baseCesantias bit, @baseIntereses bit, @baseSeguridadSocial bit, 
@baseVacaciones bit,@controlConcepto int, @controlaSaldo bit, @manejaRango bit, @ingresoGravado bit, 
@noDias int,@noHoras int, @cantidad float, @valorUnitario money, @ValidaPorcentaje bit,
@valorBaseSeguridadSocial money,@valorBaseEmbargo money,@valorTotalDebengado money,@manejaPorcentaje bit,@valorConcepto money,@baseEmbargo bit,
@pagoMinimo money,@valorCuota money,@valorSaldo money,@valorDiaSueltoTercero money,@valorDiaSalarioMinimo money,@valorSTdiario money ,
@ganaTransporte int,@mFondoEmpleado bit,@mSindicato bit,@porSindicato float,@porFondoEmpleado float,@entidadFE varchar(50),
@entidadEPS varchar(50),@entidadPension varchar(50),@entidadSindicato varchar(50),@prioridadConcepto int,@conceptoPagaFestivo varchar(50),
@entidadarp varchar(50),@entidadCesantias varchar(50),@entidadCaja varchar(50),@entidadSena varchar(50),@entidadIcebf varchar(50),
@conceptoTransporte varchar(50),@tieneIncapacidad bit,@aprendizSena varchar(50),@electivaProduccion bit,@pEEP decimal(18,3),
@conceptoSueldo varchar(50),@conceptoPension varchar(50),@conceptoSalud varchar(50),@validaVacaciones int,@FIVacaciones date,
@FFVacaciones date,@NoDiasVacaciones int,@conceptoVacaciones varchar(50),@swfd bit,@fechaRetiro date,@Contratodefinitivo bit,
@conceptosVacaciones varchar(50),@pagaNominaVacaciones bit,@valorTotal money,@domingoPromedio bit,@festivoPromedio bit,@ValorDomingoPromedio money,
@RN float,@HED float,	@HEDF float,@HEN float,@HEDD float,@HENF float,@HF float,@HEND float,@HD float,@RND float,@RNF float,
@RNc varchar(50),@HEDc varchar(50),	@HEDFc varchar(50),@HENc varchar(50),@HEDDc varchar(50),@conceptoIncapacidad varchar(50),
@HENFc varchar(50),@HFc varchar(50),@HENDc varchar(50),@HDc varchar(50),@RNDc varchar(50),@RNFc varchar(50),
@lsalud bit,@lpension bit,@vacaciones varchar(50),@conceptoPrima varchar(50),@conceptoFondoSolidaridad varchar(50),@pierdeDomingo varchar(50),
@lPrimas bit, @lNovedades bit,@lHoras bit,@lAusentismo bit,@lVacanciones bit,@lPrestamo bit,@lEmbargo bit, @lOtros bit, @lNovedadesCredito bit, @lFondavi bit,
 @lDomingo bit, @lFestivo bit,@lSindicato bit
 declare @paga31 bit,@lDomingoCero bit,@noMes int=0,@contMes int=0, @noDiasHabiles int,@diasEmbargo int, @registroNovedad int
 declare @registroCuotaPrestamo int=0, @amortizacionPrestamo money, @saldoPrestamo money, @interesesPrestamo money

select  @retorno =20, @nombreTercero=''
delete from tmpliquidacionNomina where empresa=@empresa
	if exists(select * from nParametrosAno where empresa=@empresa and @año=@año)	
	begin
		set @SMLV = isnull((select vSalarioMinimo from nParametrosAno where ano=@año and empresa=@empresa),0)
		set @ST = isnull((select vAuxilioTransporte from nParametrosAno where ano=@año and empresa=@empresa),0)
		set @NSST = isnull((select noSueldoST from nParametrosAno where ano=@año and empresa=@empresa),0)
		set @valorSTdiario = @ST/30
	end 
	else
	begin
		set @retorno=0
		return
	end
	if exists(select * from nPeriodoDetalle	where cerrado=0 and empresa=@empresa and año=@año and mes=@mes and noPeriodo=@periodo)	
	begin
		select @FIPN= fechaInicial,@FFPN = fechaFinal from nPeriodoDetalle
		where cerrado=0 and empresa=@empresa
		and año=@año and mes=@mes and noPeriodo=@periodo
	end
	else
	begin
		set @retorno=1
		return
	end
	if not exists(select * from nConceptosFijosDetalle where empresa=@empresa and año=@año and mes=@mes and noPeriodo=@periodo)
	begin
		set @retorno=2
		return
	end
	if not exists(select * from nParametrosGeneral where empresa=@empresa )
		begin
			set @retorno=3
			return
		end
		else
		begin
			select @NSI = noSalarioIntegral,@JD= jornadaDiaria,@conceptoVacaciones=vacaciones,@festivoPromedio=promedioFestivo,
			@domingoPromedio = pGanaDomingo, @conceptoGanaDomingo =ganaDomingo,@conceptoPagaFestivo=pagoFestivo,@aprendizSena=aprendizSena,
			 @conceptoTransporte =subsidioTransporte,@conceptoSueldo=sueldo ,@conceptoSalud=salud, @conceptoPension=pension,
			 @RNc=HRN,@RNDc=HRD,@RNFc=HRF,@HEDc=HED,@HEDFc=HEDF,@HEDDc=HEDD,@HENc=HEN,@HENFc=HENF,@HENDc=HEND,@HDc=HD,@HFc=HF,@vacaciones=vacaciones,
			 @conceptoPrima=primas,@conceptoFondoSolidaridad=fondoSolidaridad,@pierdeDomingo=PrimasExtralegales,@conceptoIncapacidad=incapacidades,
			 @paga31=paga31
			  from nParametrosGeneral where empresa=@empresa
		end
	--  Pago minimo en la nomina
	set @pagoMinimo =	isnull((select distinct   vminimoperiodo from nParametrosAno where empresa=@empresa and ano=@año),0)
	-- Seleccion de funcionarios con contrato y activos	
	declare @ingreso int=0,@salida int=0
	if @paga31=1 
	begin
		set @ingreso=1
		set @salida =-1
	end
	else
	begin
		set @ingreso=0
		set @salida =0
	end

	declare cursorFuncionarios insensitive cursor for	
	select distinct  	a.tercero, 	a.codigo,	a.validaTurno, 	departamento , 	ccosto, 	b.tipoNomina,	tiempoBasico, 	fechaIngreso,	max(fechaContratoHasta) fechaContratoHasta,max(salario)	salario,	cantidadHoras,
	salarioIntegral,	terminoContrato,	b.diasContrato,	c.terminoFijo Contratofijo,	max(b.id) numeroContrato,	d.formaPago, f.tipoNomina,b.auxilioTransporte,b.mFondoEmpleado,b.mSindicato,
	b.pFondoEmpleado,b.pSindicato, h.tercero,g.tercero, i.tercero,j.tercero,c.electivaProduccion,c.porcentaje,f.diasNomina,b.fechaContratoHasta,c.terminoFijo,
	m.pension,m.salud
	from nFuncionario  a
	join nContratos  b on a.tercero = b.tercero and a.empresa = b.empresa and b.fechaIngreso <= @FFPN  --and a.activo=1
	join nClaseContrato c on b.claseContrato = c.codigo and b.empresa=c.empresa 
	join nConceptosFijos d on d.centroCosto = b.ccosto and d.empresa = b.empresa and d.año=@año
	join nPeriodoDetalle f on f.año=d.año and f.mes=d.mes and d.noPeriodo=f.noPeriodo and f.empresa=d.empresa
	join cCentrosCosto k on k.codigo=b.ccosto and k.empresa=b.empresa
	left join nEntidadEps g on g.codigo=b.entidadEps and g.empresa=a.empresa
	left join nEntidadFondoPension i on i.codigo=b.entidadPension and i.empresa=b.empresa
	left join nEntidadFondo j on j.codigo=b.entidadSindicato and j.empresa=b.empresa
	left join nEntidadFondo h on h.codigo=b.entidadFondoEmpleado and h.empresa=b.empresa
	left join nParametrosTipoCotizante m on m.tipoCotizante=b.tipoContizante and m.empresa=b.empresa and m.subTipoCotizante=b.subTipoCotizante
	where d.noPeriodo=@periodo  and a.empresa=@empresa and d.año=@año  -- funcionario activo ricardo 2017 17 01
	and (b.activo=1 or (b.fechaContratoHasta between f.fechaInicial and f.fechaFinal 
	or fechaRetiro is null or fechaRetiro > f.fechaFinal
	or b.fechaRetiro between f.fechaInicial and f.fechaFinal) )
	and  b.ccosto like (case when @formaLiquidacion=2 then '%' +@ccosto + '%' 	else '%%'  end)
	and  k.grupo like (case when @formaLiquidacion=5 then @ccosto	else '%%'  end)
	and convert(varchar(50),a.tercero) like (case when @formaLiquidacion=3 then @empleado else '%%' end)
	and  k.mayor like (case when @formaLiquidacion=4  then '%' +@ccosto + '%'	else '%%'  end)
	and convert(varchar(50), b.tercero)+ convert(varchar(50), b.id) not in
	 (select distinct convert(varchar(50), codTercero)+ convert(varchar(50), noContrato) 
	 from vSeleccionaLiquidacionDefinitiva where noPeriodo=@periodo and año=@año and anulado=0 and empresa=@empresa and tipo IN(select LQN from nParametrosGeneral where empresa=@empresa ) )
	 and convert(varchar(50), b.tercero)+ convert(varchar(50), b.id) not in
	 (select distinct convert(varchar(50), codTercero)+ convert(varchar(50), noContrato) 
	 from vSeleccionaLiquidacionDefinitiva where  anulado=0 and empresa=@empresa and tipo IN( select LQC from nParametrosGeneral where empresa=@empresa ) )   -- empanada ricardo 02/06/2016
	--and b.tercero not in (select distinct  tercero from vSeleccionaLiquidacionDefinitiva where noPeriodo=@periodo and año=@año and anulado=0 and empresa=@empresa) -- empanada ricardo 02/06/2016
	group by  a.tercero, 	a.codigo,	a.validaTurno, 	departamento , 	ccosto, 	b.tipoNomina,	tiempoBasico, 	fechaIngreso,			cantidadHoras,
	salarioIntegral,	terminoContrato,	b.diasContrato,	c.terminoFijo ,	d.formaPago, f.tipoNomina,b.auxilioTransporte,b.mFondoEmpleado,b.mSindicato,
	b.pFondoEmpleado,b.pSindicato, h.tercero,g.tercero, i.tercero,j.tercero,c.electivaProduccion,c.porcentaje,f.diasNomina,b.fechaContratoHasta,c.terminoFijo,
	m.pension,m.salud
	open cursorFuncionarios			
	fetch cursorFuncionarios into 	@tercero, 	@codigo,	@validaTurno, 	@departamento , 	@centroCosto, 	@tipoNomina,	@tiempoBasico,	@fechaIngreso,
	@fechaContratoHasta,	@salario,	@cantidadHoras,	@salarioIntegral,	@terminoContrato,	@diasContrato,	@Contratofijo,	@noContrato , @formaPago, @tipoNomina,@ganaTransporte,
	@mFondoEmpleado,@mSindicato,@porFondoEmpleado,@porSindicato,@entidadFE,@entidadEPS,@entidadPension,@entidadSindicato,@electivaProduccion,@pEEP,@noDias,@fechaRetiro,@Contratodefinitivo,@lpension,@lsalud

	while( @@fetch_status = 0 )
	begin	
	--- conceptos fijos a liquidar------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	if (@fechaRetiro <@FIPN or @fechaContratoHasta <@FIPN)  and @Contratodefinitivo=1
	begin
		set @retorno=55
		set @nombreTercero = (select descripcion from cTercero where empresa=@empresa and id=@tercero)
		delete from tmpliquidacionNomina where empresa=@empresa
		close cursorFuncionarios
		deallocate cursorFuncionarios 
		return 
	end
		delete @conceptosLiquidar
		insert @conceptosLiquidar
			select  acumulada, concepto,c.tipoLiquidacion, c.signo,c.baseSeguridadSocial,c.validaPorcentaje, c.porcentaje,c.valor, c.baseEmbargo,0,1,a.fechaRegistro,c.prioridad,0
			from nConceptosFijos a 
			join nConceptosFijosDetalle b on a.año=b.año and a.mes=b.mes and a.empresa = b.empresa and a.centroCosto=b.centroCosto and b.noPeriodo=a.noPeriodo
			join nConcepto c on c.codigo=b.concepto and c.empresa=b.empresa
			where a.centroCosto=@centroCosto and a.mes=@mes and a.año=@año and a.noPeriodo=@periodo and a.empresa=@empresa
			order by c.signo,c.prioridad
			 
			insert @conceptosLiquidar 
			select   0, b.concepto, d.tipoLiquidacion, d.signo, d.baseSeguridadSocial, d.validaPorcentaje, d.porcentaje, b.valor, d.baseEmbargo,1,b.cantidad,a.fecha,d.prioridad,b.registro
			from nnovedades a 
			join  nNovedadesDetalle b on a.tipo = b.tipo and a.numero = b.numero and a.empresa=b.empresa 
			join nConcepto d on d.codigo=b.concepto and d.empresa=b.empresa 
			where  a.empresa=@empresa and @año  between b.añoInicial and b.añoFinal
			and b.empleado=@tercero AND a.anulado=0 and b.anulado=0
			and (@periodo between   (case when b.añoInicial =@año then b.periodoInicial else 0 end) and 
			(case when b.añoFinal=@año then b.periodoFinal else 99999999 end))
			and b.concepto not in (select concepto from @conceptosLiquidar)

			select @lNovedades=  lNovedades,@lPrestamo= lPresamo,@lHoras= lHoras,@lVacanciones =lVacaciones,
			@lPrimas= lPrimas,@lAusentismo= lAusentismo,@lEmbargo= lEmbargo,@lSindicato=lSindicato,
			@lOtros= lOtros, @lNovedadesCredito =lNovedadesCredito, @lFondavi=lFondavi,@lDomingo=lDomingo,@lFestivo=lFestivo,
			@lDomingoCero=ldomingoCero,@domingoPromedio=lDomingoPromedio,@festivoPromedio=lFestivoPromedio
			from nConceptosFijos a
			where a.centroCosto=@centroCosto and a.mes=@mes and a.año=@año and a.noPeriodo=@periodo and a.empresa=@empresa
	
	---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	select  @valorDiaSueltoTercero = (@salario/30), @valorDiaSalarioMinimo =(@SMLV/30), @tieneIncapacidad=0,@validaVacaciones=0
	-- verifico las fechas del contrato esta entre mi periodo a liquidar
	if  @fechaIngreso <= @FIPN 
		set  @fechaInicialLiquidacion = @FIPN 
	else
	begin
	
		if (month(@FIPN) = 2 and MONTH(@FFPN)=3 and month(@fechaIngreso)=3)
			set @noDias = DATEDIFF(day,@fechaIngreso,@FFPN) +1
		else
			set @noDias = @noDias - (DATEDIFF(day,@FIPN,@fechaIngreso)) + @ingreso
			set  @fechaInicialLiquidacion = @fechaIngreso
	end
	-- verico si tiene prorroga o retiro y actualizo fecha final
	if exists(select * from nProrroga	where tercero=@tercero and empresa=@empresa and contrato=@noContrato )
	begin
		select @fechaContratoHasta= case when tipo ='R' then fechaRetiro else fechaFinal end  
		from nProrroga where tercero=@tercero and contrato = @noContrato 
		and id=(select max(id) from nProrroga where tercero=@tercero and empresa=@empresa) and empresa=@empresa 
		-- validacion de fechas para la liquidacion
		if @fechaContratoHasta >=@FFPN
			set @fechaFinalLiquidacion = @FFPN
		else
		begin
			if @fechaContratoHasta>= @FIPN
			begin
				set @fechaFinalLiquidacion = @fechaContratoHasta
				set @noDias = DATEDIFF(day, @fechaInicialLiquidacion, case when day(@fechaContratoHasta)=31 then dateadd(day,-1,@fechaContratoHasta) else @fechaContratoHasta end)+1 + @salida
				if @fechaFinalLiquidacion=@FIPN
					set @noDias=1
				if @fechaIngreso=@fechaRetiro
					set @noDias=1
			end
		end
		
	end
	else
	begin
		if  @fechaRetiro <= @FFPN and @Contratofijo in (1) and @fechaRetiro > @FIPN
		begin
			set  @fechaFinalLiquidacion = @fechaRetiro
			set @noDias = @noDias - (DATEDIFF(day,@fechaFinalLiquidacion,case when day(@FFPN)=31 then dateadd(day,-1,@FFPN) else @FFPN end))+@salida
		end
		else
		begin
			
			set @fechaFinalLiquidacion = @FFPN
		end
		
	end

	-- Valida Periodo de Vacaciones
	if @lVacanciones=1
	begin
		declare @liquidaVacaciones bit,@añoV int, @mesV int,@periodoV int,@cantV decimal(18,3),@tipoVacaciones int,@swVacaciones bit =0,@nodiasvaca int =0,
		@swControl bit=0,@contarVacaciones int=0,@noPV varchar(50)
		
		if exists(select * from vSeleccionaVacaciones where empresa=@empresa and empleado=@tercero and anulado=0
			and (fechaSalida between @FIPN and @FFPN  or fechaRetorno between @FIPN and @FFPN or (fechaSalida<@FIPN and fechaRetorno>@FFPN) or (añoPago=@año and periodo=@periodo)) )
		begin
		set @contarVacaciones = (select COUNT(fechaSalida) from vSeleccionaVacaciones 
		where empresa=@empresa and empleado=@tercero and anulado=0 and (fechaSalida between @fechaInicialLiquidacion and @FFPN  
		or fechaRetorno between @fechaInicialLiquidacion and @fechaFinalLiquidacion or (fechaSalida<@fechaInicialLiquidacion and fechaRetorno>@fechaFinalLiquidacion)
		 or (añoPago=@año and periodo=@periodo)) and concepto=@vacaciones and tipo=1 and fechaSalida between  @fechaInicialLiquidacion and @fechaFinalLiquidacion )

		declare cursorVaca insensitive cursor for	
		select fechaSalida,fechaRetorno,diasPagados,concepto,valorUnitario,valorTotal,pagaNomina,liquidada,añopago,mes,periodo, cantidad, tipo,noPrestamo from vSeleccionaVacaciones 
		where empresa=@empresa and empleado=@tercero and anulado=0 and (fechaSalida between @fechaInicialLiquidacion and @FFPN  
		or fechaRetorno between @fechaInicialLiquidacion and @fechaFinalLiquidacion or (fechaSalida<@fechaInicialLiquidacion and fechaRetorno>@fechaFinalLiquidacion) or (añoPago=@año and periodo=@periodo)) 
		order by fechaSalida asc
		open cursorVaca			
		fetch cursorVaca into 	@fiVacaciones,@ffvacaciones,@NoDiasVacaciones,@conceptosVacaciones,
		@valorUnitario,@valorTotal,@pagaNominaVacaciones,@liquidaVacaciones,@añoV,@mesV,@periodoV,@cantV,@tipoVacaciones,@noPV
		while( @@fetch_status = 0 )
		begin	
			if (@FIVacaciones<@fechaInicialLiquidacion and @FFVacaciones>@fechaFinalLiquidacion ) and @tipoVacaciones<>2 and @conceptosVacaciones=@vacaciones
			begin
				set @noDias = 0
				set @fechaFinalLiquidacion =null
				set @validaVacaciones=1
				set @swVacaciones=1
			end
			else
			begin
				if @FIVacaciones between @fechaInicialLiquidacion and @fechaFinalLiquidacion and  @tipoVacaciones<>2 and @conceptosVacaciones=@vacaciones
				begin
					set @nodiasvaca =  @nodiasvaca + (DATEDIFF(day,@fechainicialLiquidacion,@FIVacaciones)) 
					set @swVacaciones=1
				end
				if @FFVacaciones between @fechaInicialLiquidacion and @fechaFinalLiquidacion and @tipoVacaciones<>2 and @conceptosVacaciones=@vacaciones
				begin
					set @nodiasvaca =  @nodiasvaca +  (DATEDIFF(day,dateadd(day,-1,@FFVacaciones),case when day(@fechaFinalLiquidacion)=31 and @paga31=0  then dateadd(day,-1,@fechaFinalLiquidacion) else @fechaFinalLiquidacion end ))
					set @swVacaciones=1
				end
			
			if @pagaNominaVacaciones=1 and @liquidaVacaciones=0 and @añoV=@año and @periodoV=@periodo
			begin	
				insert  tmpliquidacionNomina 
				select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento,@conceptosVacaciones, @año, @mes, @periodo, @cantV,0, @valorUnitario  ,round(@valorTotal,0),signo, 0, 1, @fiVacaciones, @ffvacaciones,
				case when @conceptosVacaciones= @conceptoVacaciones and @tipoVacaciones=2 then 0 else  baseSeguridadSocial end,
				baseEmbargo,null,1,round(@valorTotal,0),@noContrato,@noPV, case when codigo = @conceptoVacaciones then @tipoVacaciones end , 
				case when @tipoVacaciones=1 and codigo = @conceptoVacaciones then 'Disfrutada'  else
				case when @tipoVacaciones=2  and codigo = @conceptoVacaciones then 'Compensada' else 
				case when @tipoVacaciones=3  and codigo = @conceptoVacaciones then 'Compensadas 8/7' end  end end
				from nConcepto where codigo=@conceptosVacaciones and empresa=@empresa
			end
			end
		fetch cursorVaca into @fiVacaciones,@ffvacaciones,@NoDiasVacaciones,@conceptosVacaciones,
		@valorUnitario,@valorTotal,@pagaNominaVacaciones,@liquidaVacaciones,@añoV,@mesV,@periodoV,@cantV,@tipoVacaciones,@noPV
		end
		close cursorVaca
		deallocate cursorVaca
		if (select count(concepto) from vSeleccionaVacaciones 
		where empresa=@empresa and empleado=@tercero and anulado=0 and (fechaSalida between @fechaInicialLiquidacion and @FFPN  
		or fechaRetorno between @fechaInicialLiquidacion and @fechaFinalLiquidacion or (fechaSalida<@fechaInicialLiquidacion and fechaRetorno>@fechaFinalLiquidacion)
		 or (añoPago=@año and periodo=@periodo))  and  concepto=@concepto)>1 
		begin
			set @noDias =@noDias - @nodiasvaca 
		end
		else
		begin
			if @swVacaciones=1 
			begin
				if  @formaPago =0 and @noDias < @nodiasvaca
				begin
					set @noDias = @noDias
				end
				else
				begin
					set @noDias = @nodiasvaca + (CASE WHEN @mes=2 AND DAY(@fechaFinalLiquidacion)=29 THEN 1 WHEN @mes=2 AND DAY(@fechaFinalLiquidacion)=28 THEN 2  ELSE 0 END)
				end
			end
		end
		--- Lo quite el dia 28/04/2016 llamada joe porque tiene dos periodos de vacaciones por pagar pero solo coupa 10 dias del periodo actual
		--Lo agregue el dia 13/05/2016 llamada de joe porque tiene dos periodos de vacaciones seguidos y no debe pagar ningun dia
		if @noDias=@nodiasvaca and @contarVacaciones>1
		--and @FFVacaciones>@fechaInicialLiquidacion and @FIVacaciones=@fechaInicialLiquidacion and @FFVacaciones>@fechaFinalLiquidacion
		begin
		--select 'entro'
			set @noDias=0
			
		end
	end
	end

	-- pregunto si hay fecha nulas para no liquidar
	if @fechaInicialLiquidacion  is not null  and  @fechaFinalLiquidacion is not null
	begin	
		-- pregunto la forma de pago para crear diferentes casos 
		if not exists (select distinct  formaPago, acumulada, concepto	from nConceptosFijos a 			
		join nConceptosFijosDetalle b on a.año=b.año and a.mes = b.mes and a.noPeriodo = b.noPeriodo and a.empresa = b.empresa			
		where a.centroCosto=@centroCosto and a.mes=@mes and a.año=@año and a.noPeriodo=@periodo)
		begin
				set @retorno=4
				return
		end
			--- calcula jornales cargadores
			--if exists(select distinct  * from vTransaccionesCampoLiquidacion where tercero=@tercero and  año=@año and periodo= @periodo and empresa=@empresa )
			--begin
			--	exec spRecalculaJornalesCargadores @empresa,@tercero,@fechaInicialLiquidacion, @fechaFinalLiquidacion  
			--end
			declare @sw bit = 0, @cont int =0, @diaTrabajado int = 0, @swHoras int = 0,@fijoL bit=0,@swControlNovedades bit=0

	    	select  @valorBaseSeguridadSocial = 0, @valorBaseEmbargo=0, @valorTotalDebengado=0,@swfd=0 

			------Recorrer cursor de conceptos a liquidad------------------------------------------------------------------------------------------------------------------------------------------------
			declare cursorConceptos insensitive cursor for
			select    acumulada,concepto, tipoLiquidacion,  signo, baseSeguridadSocial, validaPorcentaje, porcentaje, valor, baseEmbargo, tipoNovedadLiquida, cantidadNovedad,fecha,prioridad, registro
			from @conceptosLiquidar 
			order by signo,prioridad,concepto asc
			open cursorConceptos	
			fetch cursorConceptos into  @acumulada, @concepto, @tipoLiquidacion, @signo,@baseSeguridadSocial,@manejaPorcentaje, @porcentajeConcepto,@valorConcepto, @baseEmbargo, @tipoNovedadLiquida,@cantidadNovedad,@fechaNovedadNomina,@prioridadConcepto ,@registroNovedad
			while( @@fetch_status = 0 )
			begin	
			if @formaPago =0
			begin
			
			--Liquida Sueldo
				if exists (select * from nparametrosgeneral where sueldo=@concepto and empresa=@empresa ) and @noDias>0
				begin
				
					declare @valorSueldo money=0
					exec spCalculaTipoLiquidacion @JD , @salario , @tipoLiquidacion ,@noDias,  @fechaInicialLiquidacion , @fechaFinalLiquidacion ,@FIPN , @FFPN , @cantidad  output, @valorUnitario  output
					if @electivaProduccion=1
					begin
						set @concepto=@aprendizSena
						set @valorSueldo=(@valorUnitario*@cantidad) * @pEEP/100
					end
					else
						set @valorSueldo=(@valorUnitario*@cantidad)
					insert  tmpliquidacionNomina 
					select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo, @cantidad,@porcentajeConcepto, @valorUnitario,round(@valorSueldo,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,@valorSueldo,@noContrato,null,'',''
					from nConcepto where codigo=@concepto and empresa=@empresa
				end
			-- liquida auxilio de transporte administrativo
				if exists (select * from nparametrosgeneral where subsidioTransporte=@concepto and empresa=@empresa ) and @electivaProduccion=0 and @noDias>0
				begin
					if  @ganaTransporte =1
					begin
						if @salario < @SMLV * @NSST
						begin
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo, @noDias,@porcentajeConcepto, @valorSTdiario,round(@valorSTdiario*@noDias,0),@signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@noDias,@valorSTdiario*@noDias,@noContrato,null,'', ''
						end
					end
				end
			end
			if @formaPago =1
			begin
			if @lOtros=1
			begin
		
				-- Liquida Labores Agronomicas
				if exists (select distinct  * from vTransaccionesCampoLiquidacion where tercero=@tercero and periodo=@periodo and año=@año  and empresa=@empresa and empresa=@empresa  )
				or (datepart(WEEKDAY,@fechaInicialLiquidacion)=7)
				begin
				if @sw=0
				begin
					insert  tmpliquidacionNomina 
					--select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, concepto, @año, @mes, @periodo,round(sum(jornalesTercero),0), 0, 0 , 
					--round(sum(valorTotal),0) ,signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,
					--sum(cantidadTercero),sum(valorTotal),@noContrato,null,'',''	from vTransaccionesCampoLiquidacion						
					--where tercero=@tercero and 
					--año = @año and periodo=@periodo and empresa=@empresa
					--group by tercero, concepto, signo, baseSeguridadSocial, baseEmbargo
					select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, concepto, @año, @mes, @periodo,round(sum(jornalesTercero),0), 0, 0 , 
					round(sum(valorTotal),0) ,signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,
					sum(cantidadTercero),sum(valorTotal),@noContrato,null,'',''	from vTransaccionesCampoLiquidacion	 			
					where tercero=@tercero and  fecha between @fechaInicialLiquidacion and @fechaFinalLiquidacion and empresa=@empresa
					group by tercero, concepto, signo, baseSeguridadSocial, baseEmbargo
					
					DECLARE @lunes DATE,@domingo DATE, @i int = 0 ,@entro int =0,@diaPromedio int=0
					set @ValorDomingoPromedio=0
					set @cont=0
					if (datepart(WEEKDAY,@fechaInicialLiquidacion)=7)
					begin
						SET @lunes = DATEADD(WK, DATEDIFF(WK,0,dateadd(day,-1,@fechaInicialLiquidacion)), 0)
						SET @domingo = DATEADD(day,6,@lunes)
					end
					else
					begin
						SET @lunes = DATEADD(WK, DATEDIFF(WK,0,@fechaInicialLiquidacion), 0)
						SET @domingo = DATEADD(day,6,@lunes)
					end
					if @fechaIngreso>@lunes --Esto lo quite el dia 31 de enero del 2017 porque llamada joe liquidaba y salida festivo 
						set @i =@i + DATEDIFF(DAy, @lunes,@fechaIngreso) 

					while(@lunes<=@fechaFinalLiquidacion)
					begin
						if exists(select * from vTransaccioneLaboresDomingo where fechaNovedad =@lunes and tercero=@tercero and empresa=@empresa and contratista=0 )
						begin
							set @i= @i + isnull( convert(int,round(isnull(((select SUM(jornales) from vTransaccioneLaboresDomingo 
							where fechaNovedad =@lunes and tercero=@tercero and empresa=@empresa and manejaJornal=1 and contratista=0 )),0),0)),0)
							set @diaTrabajado = @diaTrabajado + 1 
							set @entro = 1
							set @diaPromedio=@diaPromedio + 1
							
							--select @i,@lunes 
							set @ValorDomingoPromedio = @ValorDomingoPromedio + 
							isnull((select SUM(isnull(valorTotal,0)) from vTransaccioneLaboresDomingo 
							where fechaNovedad =@lunes and tercero=@tercero and empresa=@empresa and noprestacional=0 and contratista=0
							and fechaNovedad >= @fechaIngreso ),0)
						
						end

						if exists(select * from nIncapacidad a join nConcepto b on b.codigo=a.concepto and b.empresa=a.empresa
						 where a.empresa=@empresa and tercero=@tercero and @lunes between fechaInicial and fechaFinal and b.descuentaDomingo=0 and a.anulado=0 )
						begin
							set @i=@i+1
							set @diaTrabajado = @diaTrabajado + 1 
							
						end
						if exists(select * from vSeleccionaVacaciones a where a.empresa=@empresa and empleado=@tercero and @lunes
						 between fechaSalida and dateadd(day,-1,fechaRetorno) and anulado=0  and empresa=@empresa and dateadd(day,-1,fechaRetorno) <@domingo)
						begin
							set @i=@i+1
							set @diaTrabajado = @diaTrabajado + 1 
						end
						
						if exists(select * from nFestivo where datepart(WEEKDAY,@lunes)<>7 and convert(date,fecha)=@lunes and empresa=@empresa) 
						and not exists(select * from nIncapacidad where empresa=@empresa and tercero=@tercero and @lunes between fechaInicial and fechaFinal and anulado=0)
						and not exists(select * from nVacaciones where empresa=@empresa and empleado=@tercero and @lunes between fechaSalida and fechaRetorno and anulado=0)
						and (@lunes <= @fechaContratoHasta or @fechaContratoHasta is null) and @lFestivo=1 
						begin
							set @i=@i+1
							if @entro=0
							set @diaTrabajado = @diaTrabajado + 1 
							exec spLiquidaConceptoTercero @empresa,@año,@conceptoPagaFestivo,@tercero,1,0,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porc output
							if @festivoPromedio=0 
								set @valorUnitario= round(@valorUnitario,0)
							else
							begin
								if  @festivoPromedio=0 
									set @valorUnitario = @valorDiaSueltoTercero
								else
								begin
									if @diaPromedio=0
										set @valorUnitario = @valorDiaSalarioMinimo
									else
										set @valorUnitario= round(@ValorDomingoPromedio/@diaPromedio,0)
									if @valorUnitario <@valorDiaSalarioMinimo
										set @valorUnitario = @valorDiaSalarioMinimo
								end
							end
						
							if  (@lunes between @fechaInicialLiquidacion and @fechaFinalLiquidacion)
							begin
								set @cont = @cont + 1
								
								if @lDomingoCero=1
									set @valorUnitario = 0

								insert  tmpliquidacionNomina 
								select @empresa, @tercero, @centroCosto, @lunes, @departamento, codigo, @año, @mes, @periodo, 1,0, round(@valorUnitario,0)  ,round(@valorUnitario,0),signo, 0, 1, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,1,@valorUnitario,@noContrato,null,'', ''
								from nConcepto where codigo=@conceptoPagaFestivo and empresa=@empresa
							end
							set @entro=0
						end


						select @noDiasHabiles=hasta from nDiasHabilesCc  -- dias habiles para generar los domingos ricardo gomez 22/04/2017
						where empresa=@empresa and @centroCosto =ccosto
						--select * from nDiasHabilesCc
						--select @lunes,@i
						if (@i=@noDiasHabiles and @lunes<@domingo and @sw=0 and (@domingo between @fechaInicialLiquidacion and @fechaFinalLiquidacion))
						and not exists(select * from nVacaciones where empresa=@empresa and empleado=@tercero and @domingo between fechaSalida and fechaRetorno and anulado=0)
						and not exists(select * from nIncapacidad where empresa=@empresa and tercero=@tercero and @domingo between fechaInicial and fechaFinal and anulado=0)
						and @lDomingo=1 
						begin
							set @i=@i+1
							set @diaTrabajado = @diaTrabajado + 1 
							set @cont = @cont + 1
							exec spLiquidaConceptoTercero @empresa,@año,@conceptoGanaDomingo,@tercero,1,0,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porc output
							if  @domingoPromedio=0
								set @valorUnitario = @valorDiaSueltoTercero
							else
							begin
								if @diaPromedio=0
									set @valorUnitario= @valorDiaSalarioMinimo
								else
								begin
									set @valorUnitario= round(@ValorDomingoPromedio/@diaPromedio,0)
									--select @valorUnitario, @ValorDomingoPromedio, @diaPromedio, @valorDiaSalarioMinimo

									if @valorUnitario <@valorDiaSalarioMinimo
										set @valorUnitario = @valorDiaSalarioMinimo
								end
							end
							if @lDomingoCero=1
									set @valorUnitario = 0

							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @domingo, @departamento, codigo, @año, @mes, @periodo, 1,0, @valorUnitario  ,round(@valorUnitario,0),signo, 0, 1, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,1,@valorUnitario,@noContrato,null, null, null
							from nConcepto where codigo=@conceptoGanaDomingo and empresa=@empresa
						end
						
						if @lunes=@domingo
						begin
							set @i=0
							SET @domingo = DATEADD(day,7,@lunes)
							set @entro=0
							set @diaPromedio=0
							set @ValorDomingoPromedio=0
						end
						set @lunes = DATEADD(DAY,1,@lunes)
					end
				end
					set @sw = 1
				end
				-- liquida auxilio de transporte campo
				if exists (select * from nparametrosgeneral where subsidioTransporte=@concepto and empresa=@empresa  ) and @electivaProduccion=0
				and not exists(select * from tmpliquidacionNomina where concepto=@concepto and empresa=@empresa and tercero=@tercero)
				begin
				if (exists(select * from vTransaccioneLaboresDomingo where 
				    periodo=@periodo and año=@año  and empresa=@empresa and contratista=0)	
				    or -- el domingo no lo liquidaba el auxilio  puse esta condicion para que tubiera en cuenta 
				   exists((select * from  tmpliquidacionNomina where empresa=@empresa and concepto = @conceptoGanaDomingo 
				   and convert(date, fecha) between @fechaInicialLiquidacion and @fechaFinalLiquidacion
				   )))                   
				   and @ganaTransporte =1
					begin
						
						declare @cantTransporte int
						set @cantTransporte =round((select sum(jornales) from vTransaccioneLaboresDomingo where fecha between @fechaInicialLiquidacion and @fechaFinalLiquidacion 
						and tercero=@tercero and periodo=@periodo and año=@año  and  empresa=@empresa and contratista=0 ),0)
						-- este verifica los domingos sin  labores de campo 17/01/2017 por Ricardo Gomez
						if not	exists(select * from vTransaccioneLaboresDomingo where fecha between @fechaInicialLiquidacion and @fechaFinalLiquidacion and tercero=@tercero
							and fechaNovedad between @fechaInicialLiquidacion and @fechaFinalLiquidacion and empresa=@empresa and contratista=0)
						begin
							if exists(select * from  tmpliquidacionNomina where empresa=@empresa and concepto = @conceptoGanaDomingo 
							and convert(date, fecha) between @fechaInicialLiquidacion and @fechaFinalLiquidacion)
								begin
								set @cantTransporte += (select sum(isnull(cantidad,0)) from  tmpliquidacionNomina where empresa=@empresa and concepto = @conceptoGanaDomingo 
								and convert(date, fecha) between @fechaInicialLiquidacion and @fechaFinalLiquidacion)
								end
						end

						set @cantTransporte = isnull(@cantTransporte,0) + isnull(@cont,0)
						set @cantTransporte = isnull(@cantTransporte,0) + isnull( (select sum(isnull(b.cantidad,0))
							from nnovedades a 
							join  nNovedadesDetalle b on a.tipo = b.tipo and a.numero = b.numero and a.empresa=b.empresa 
								join nConcepto d on d.codigo=b.concepto and d.empresa=b.empresa 
								where  a.empresa=@empresa and @año  between b.añoInicial and b.añoFinal
							and b.empleado=@tercero AND a.anulado=0 and b.anulado=0
							and (@periodo between   (case when b.añoInicial =@año then b.periodoInicial else 0 end) and 
						(case when b.añoFinal=@año then b.periodoFinal else 99999999 end)) and d.codigo=@concepto),0)

						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo, @cantTransporte,@porcentajeConcepto, 0,round(@cantTransporte * @valorSTdiario,0), @signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@cantTransporte,@cantTransporte * @valorSTdiario,@noContrato,null, null,null
					end
				end
			end
			end
			if @formaPago =2
			begin
			if @lOtros=1
			begin
				declare @cantDiasDestajo int =0,@cantidadTD int 
					--select 'paso'
				-- Liquida Labores Agronomicas y administrativas
				if exists (select distinct  * from vTransaccionesCampoLiquidacion where tercero=@tercero and periodo=@periodo and año=@año  and empresa=@empresa and empresa=@empresa  )
				begin
					if  @swfd=0
					begin
						set @cantidadTD =@noDias

						delete from tmpDescuentaAgro

						insert tmpDescuentaAgro
						select a.codEmpresa, a.codConcepto, SUM(a.valorTotalTercero) from vTransaccionAgronomico a
						join nVacaciones b on b.empleado=a.idTercero and b.empresa=a.codEmpresa 
						where b.empresa=@empresa and empleado=@tercero and b.anulado=0 and a.anulado=0  and a.ejecutado=0 
						and a.fechaTransaccion between b.fechaSalida and dateadd(day,-1,b.fechaRetorno) 
						and a.periodo = @periodo and a.año=@año
						group by a.codConcepto,a.codEmpresa

						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, a.codConcepto, @año, @mes, @periodo,sum(a.jornalTercero), 0, sum(a.valorTotalTercero) , 
						round(sum(a.valorTotalTercero),0) -isnull(b.valortotal,0) ,c.signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,sum(cantidadTercero),round(sum(a.valorTotalTercero),0) -isnull(b.valortotal,0),@noContrato,null, null,null
						from vTransaccionAgronomico	a
						left join tmpDescuentaAgro b on b.empresa=a.codEmpresa and b.concepto=a.codConcepto
						join nconcepto c on c.codigo=a.codconcepto and c.empresa=a.codempresa				
						where a.idtercero=@tercero and  
						((a.periodo =@periodo and a.año=@año) or (a.fechaTransaccion between @fechainicialliquidacion and @fechafinalLiquidacion)) 
						and anulado=0 and ejecutado=0 and a.codEmpresa=@empresa and contratista=0
						group by a.idtercero, a.codconcepto, c.signo, baseSeguridadSocial, baseEmbargo,b.valortotal, a.codEmpresa


						set @cantDiasDestajo = round(isnull((select sum(jornales) from vTransaccioneLaboresDomingo where periodo=@periodo and año=@año  and empresa=@empresa and
						tercero=@tercero and empresa=@empresa and ejecutado=0 and contratista=0),0),0)
					end
				end
				else
					set @cantidadTD =@noDias
				set @cantDiasDestajo = @noDias- @cantDiasDestajo  
				-- Liquida Sueldo
				if exists (select * from nparametrosgeneral where sueldo=@concepto and empresa=@empresa )
				begin
					exec spCalculaTipoLiquidacion @JD , @salario , @tipoLiquidacion ,@cantDiasDestajo,  @fechaInicialLiquidacion , @fechaFinalLiquidacion ,@FIPN , @FFPN , @cantidad  output, @valorUnitario  output
					if @electivaProduccion=1
					begin
						set @concepto=@aprendizSena
						set @valorSueldo=(@valorUnitario*@cantidad) * @pEEP/100
					end
					else
						set @valorSueldo=(@valorUnitario*@cantidad)
						set @noDias=@cantidad
					insert  tmpliquidacionNomina 
					select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo, @cantidad,@porcentajeConcepto, @valorUnitario,round(@valorSueldo,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,@valorSueldo,@noContrato,null,'', ''
					from nConcepto where codigo=@concepto and empresa=@empresa
				end
				-- Liquida auxilio de transporte administrativo
				if exists (select * from nparametrosgeneral where subsidioTransporte=@concepto and empresa=@empresa ) and @electivaProduccion=0
				begin
					if  @ganaTransporte =1
					begin
						if @salario < @SMLV * @NSST
						begin
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo, @cantidadTD,@porcentajeConcepto, @valorSTdiario,round(@valorSTdiario*@cantidadTD,0),@signo, 0, @cantidadTD, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@cantidadTD,round(@valorSTdiario*@cantidadTD,0),@noContrato,null, null,null
						end
					end
				end
				set @swfd=1
			end
			end
			-- Liquida novedades de nomina
			if @lNovedades=1 or @lNovedadesCredito=1
			begin
				set @noMes= isnull((select nomes from nConcepto where empresa=@empresa and codigo=@concepto),0)
				set @contMes = ISNULL((select count(*) from vSeleccionaLiquidacionDefinitiva 
				where empresa=@empresa and  mes=@mes and año=@año and codConcepto=@concepto and anulado=0 and codTercero=@tercero ),0)
				set @contMes = @contMes + ISNULL((select count(*) from tmpliquidacionNomina where empresa=@empresa and  mes=@mes and año=@año and concepto=@concepto  and tercero=@tercero),0)
				-- se corrigio el 17-02-2017 por que duplica los conceptos de liquidacion y vacaciones 
				-- set @contMes = @contMes + isnull((select count(*) from vSeleccionaVacaciones where empleado=@tercero and empresa=@empresa and anulado=0 and concepto=@concepto and month(fechaSalida)=@mes ),0)
			
			if (@tipoNovedadLiquida=1) 
			begin
				if exists(select signo from nConcepto where codigo=@concepto and empresa=@empresa and signo=2 and @lNovedadesCredito=1 and  @lNovedades=0 )
				begin
					if (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato)>@pagoMinimo
					begin
						if @noMes>@contMes
						begin
						if @lNovedadesCredito=1
						begin
							set @swControlNovedades=1
							exec spLiquidaConceptoTercero  @empresa,@año, @concepto,@tercero,@cantidadNovedad,@valorConcepto,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porcentajeConcepto output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaNovedadNomina, @departamento, @concepto, @año, @mes, @periodo, @cantidad,@porcentajeConcepto, round(@valorUnitario,0),round(@valorUnitario,0),@signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null, null,null
							end
						end
					end
				end
				else
				begin
					if exists(select signo from nConcepto where codigo=@concepto and empresa=@empresa and signo=1 and  @lNovedades=1 and @lNovedadesCredito=0)
					begin
						if @noMes>@contMes
						begin
						
							set @swControlNovedades=1
							exec spLiquidaConceptoTercero  @empresa,@año, @concepto,@tercero,@cantidadNovedad,@valorConcepto,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porcentajeConcepto output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaNovedadNomina, @departamento, @concepto, @año, @mes, @periodo, @cantidad,@porcentajeConcepto, round(@valorUnitario,0),round(@valorUnitario,0),@signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null,'',''
						end
					end
					else
					begin
					
						if exists(select signo from nConcepto where codigo=@concepto and empresa=@empresa and signo in(2) and  @lNovedades=1 and @lNovedadesCredito=1)
						begin
							if (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato )>@pagoMinimo
							begin
								if @noMes>@contMes
								begin
									if @lNovedadesCredito=1
									begin
										set @swControlNovedades=1
										exec spLiquidaConceptoTercero  @empresa,@año, @concepto,@tercero,@cantidadNovedad,@valorConcepto,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porcentajeConcepto output
										insert  tmpliquidacionNomina 
										select @empresa, @tercero, @centroCosto, @fechaNovedadNomina, @departamento, @concepto concepto, @año, @mes, @periodo, @cantidad cantidad,@porcentajeConcepto porcentaje, round(@valorUnitario,0) valorunitario,round(@valorUnitario,0) valorTotal,@signo signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null,'',''
									end
								end
							end
						end
						else
						begin
							if @noMes>@contMes
							begin
							if @lNovedadesCredito=1
							begin
								set @swControlNovedades=1
								exec spLiquidaConceptoTercero  @empresa,@año, @concepto,@tercero,@cantidadNovedad,@valorConcepto,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porcentajeConcepto output
								insert  tmpliquidacionNomina 
								select @empresa, @tercero, @centroCosto, @fechaNovedadNomina, @departamento, @concepto, @año, @mes, @periodo, @cantidad,@porcentajeConcepto, round(@valorUnitario,0),round(@valorUnitario,0),@signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null,'',''
							end
							end
						end
					end
					end
				end
			end

			-- Liquida Horas extras de modulo de programacion
			if @lHoras=1
			begin
			if exists(select * from nProgramacion where empresa=@empresa and funcionario=@tercero and fecha between @fechaInicialLiquidacion and @fechaFinalLiquidacion and estado in ('P','S')) and @swHoras=0
			begin
				declare @conceptoHora varchar(50)
				exec SpSeleccionaPreLiquidacionHora @fechaInicialLiquidacion,@fechaFinalLiquidacion,@empresa,@tercero
				declare @fechaP date
				declare curHoras insensitive cursor for
					select fechaP, HED, HEN, RN,HD, RND,HEDD, HEND, HF, RNF, HEDF, HENF from tmpLiquidacionHoras a 
					where a.funcionario=@tercero and a.empresa=@empresa 
					order by fechaP asc
					open curHoras			
					fetch curHoras into @fechaP, @HED, @HEN, @RN,@HD, @RND,@HEDD, @HEND, @HF, @RNF, @HEDF, @HENF
					while( @@fetch_status = 0 )
					begin	
						if @HED>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @HEDc,@tercero,@HED,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @HEDc, @año, @mes, @periodo, @HED,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null,'',''
							from nConcepto where codigo=@HEDc and empresa=@empresa
						end
						if @HEN>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @HENc,@tercero,@HEN,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @HENc, @año, @mes, @periodo, @HEN,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null,'',''
							from nConcepto where codigo=@HENc and empresa=@empresa
						end
						if @RN>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @RNc,@tercero,@RN,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @RNc, @año, @mes, @periodo, @RN,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null,'', ''
							from nConcepto where codigo=@RNc and empresa=@empresa
						end
						if @RND>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @RNDc,@tercero,@RND,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @RNDc, @año, @mes, @periodo, @RND,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null,'',''
							from nConcepto where codigo=@RNDc and empresa=@empresa
						end
						if @RNF>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @RNFc,@tercero,@RNF,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @RNFc, @año, @mes, @periodo, @RNF,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null, '', ''
							from nConcepto where codigo=@RNFc and empresa=@empresa
						end
						if @HEDD>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @HEDDc,@tercero,@HEDD,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @HEDDc, @año, @mes, @periodo, @HEDD,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null, '',''
							from nConcepto where codigo=@HEDDc and empresa=@empresa
						end
						if @HEND>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @HENDc,@tercero,@HEND,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @HENDc, @año, @mes, @periodo, @HEND,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null, null,null
							from nConcepto where codigo=@HENDc and empresa=@empresa
						end
						if @HD>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @HDc,@tercero,@HD,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @HDc, @año, @mes, @periodo, @HD,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null, null,null
							from nConcepto where codigo=@HDc and empresa=@empresa
						end
						if @HEDF>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @HEDFc,@tercero,@HEDF,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @HEDFc, @año, @mes, @periodo, @HEDF,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null, null,null
							from nConcepto where codigo=@HEDFc and empresa=@empresa
						end
						if @HENF>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @HENFc,@tercero,@HENF,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @HENFc, @año, @mes, @periodo, @HENF,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null, null,null
							from nConcepto where codigo=@HENFc and empresa=@empresa
						end
						if @HF>0
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @HFc,@tercero,@HF,0,@noDias,@fechaP,0,@Valorunitario output,@cantidad output,@conceptoHora output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaP, @departamento, @HFc, @año, @mes, @periodo, @HF,@conceptoHora, round(@valorUnitario,0),round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null, null,null
							from nConcepto where codigo=@HFc and empresa=@empresa
						end

					fetch curHoras into @fechaP, @HED, @HEN, @RN,@HD, @RND,@HEDD, @HEND, @HF, @RNF, @HEDF, @HENF
					end
					close curHoras
					deallocate curHoras

					set @swHoras=1
			end
			end
			--select 'cuando da cada giro' ,* from tmpliquidacionNomina
			--where  tercero=@tercero

			-- Liquida Seguridad Social Pension
			if exists (select * from nparametrosgeneral where  pension = @concepto and empresa=@empresa ) and @lpension=1 and  (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato)>@pagoMinimo
			begin
				if @manejaPorcentaje = 1
				begin
				
					if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa)>0
					begin
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo,1, @porcentajeConcepto, @valorBaseSeguridadSocial,round((SUM(valorSS)*@porcentajeConcepto/100),0), @signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,@entidadPension,1,round((SUM(valorSS)*@porcentajeConcepto/100),0),@noContrato,null,'',''
						from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1
					end
				end
				else
				begin					
					if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa)>0
					begin
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo,1, @porcentajeConcepto, @valorBaseSeguridadSocial,round((SUM(valorSS)*@porcentajeConcepto/100),0), @signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,@entidadPension,1,round((SUM(valorSS)*@porcentajeConcepto/100),0),@noContrato,null, '',''
						from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1
					end
				end
			end
			-- Liquida Seguridad Social EPS
			if exists (select * from nparametrosgeneral where  salud = @concepto and empresa=@empresa ) and @lsalud=1 
			and  (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato )>@pagoMinimo and @electivaProduccion=0
			begin
				if @manejaPorcentaje = 1
				begin
					if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa)>0
					begin
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo,1, @porcentajeConcepto, @valorBaseSeguridadSocial, round((SUM(valorSS)*@porcentajeConcepto/100),0), @signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,@entidadEPS,1,round((SUM(valorSS)*@porcentajeConcepto/100),0),@noContrato,null, null,null
						from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1
					end
				end
				else
				begin					
					if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa)>0
					begin
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo,1, @porcentajeConcepto, @valorBaseSeguridadSocial, round((SUM(valorSS)*@porcentajeConcepto/100),0), @signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,@entidadEPS,1,round((SUM(valorSS)*@porcentajeConcepto/100),0),@noContrato,null, null,null
						from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1
					end
				end
			end
			-- Liquida Seguridad Social APorte solidaridad Pension 
			if exists (select * from nparametrosgeneral where  fondoSolidaridad = @concepto and empresa=@empresa  ) and @lpension=1 and  (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato)>@pagoMinimo
			begin
				declare @valorbaseFS int=0
				set @valorbaseFS =isnull((select sum(valorSS)  from vSeleccionaRealLiquidacion where empresa=@empresa and baseSeguridadSocial=1 and tercero=@tercero),0)
				set @porcentajeConcepto = isnull((select top 1 porcentaje from nConceptoRango where empresa=@empresa and concepto=@concepto and (@valorbaseFS/30)*@noDias between (minimo/30)*@noDias and (maximo/30)*@noDias),0) -- divido los dias del mes y dias liquidados para fondo solidaridad
				set @manejaPorcentaje = isnull((select top 1 por from nConceptoRango where empresa=@empresa and concepto=@concepto and (@valorbaseFS/30)*@noDias between (minimo/30)*@noDias and (maximo/30)*@noDias),0) -- divido los dias del mes y dias liquidados para fondo solidaridad
			
				if @manejaPorcentaje = 1 and @porcentajeConcepto>0
				begin
					
					if exists(select *  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa)
					begin
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo,1, @porcentajeConcepto, @valorBaseSeguridadSocial, round((SUM(valorSS)*@porcentajeConcepto/100),0), @signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,@entidadEPS,1,round((SUM(valorSS)*@porcentajeConcepto/100),0),@noContrato,null, null,null
						from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1
					
					end
				end
				
			end
			fetch cursorConceptos into 	 @acumulada , @concepto,@tipoLiquidacion, @signo,@baseSeguridadSocial,@manejaPorcentaje, @porcentajeConcepto,@valorConcepto,@baseEmbargo, @tipoNovedadLiquida,@cantidadNovedad,@fechaNovedadNomina,@prioridadConcepto,@registronovedad
			end
		close cursorConceptos
		deallocate cursorConceptos
		-- Liquidacion de primas---------------------------------------------------------------------------------------
		if @lPrimas=1
		begin
			if exists(select * from nLiquidacionPrima where empresa=@empresa and anulado=0 and periodo=@periodo and año=@año)
			begin
				insert  tmpliquidacionNomina 
				select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @conceptoPrima, @año, @mes, @periodo,1, c.porcentaje, b.base,b.valorPrima, c.signo, 0, @noDias, b.fechaInicial,b.fechaFinal, c.baseSeguridadSocial, c.baseEmbargo,null,1,b.valorPrima,@noContrato,null, null,null
				from nLiquidacionPrima a join 
				nLiquidacionPrimaDetalle b on b.numero=a.numero and a.tipo=a.tipo and b.empresa=a.empresa
				join nConcepto c on c.codigo=@conceptoPrima and c.empresa=@empresa
				where a.empresa=@empresa and a.anulado=0 and a.periodo=@periodo and a.año=@año
				and b.tercero =@tercero
			end
		end
		-- Liquidacion de auisentismo----------------------------------------------------------------------------------------------------------------------------------------------------------
		if @lAusentismo=1
		begin
					declare @fi date, @ff date, @valorIncapacidad money, @diasIncapacidad int,@ii int,@cant int=0,@fd date=null,@ultimoDomingo date=null,@swCero bit=0,@diasDescontado int=0,
					@VID money,@VIDP money,@DI int,@VI money,@VIDE money,@VIDPE money, @TipoIncapacidad varchar(50), @desTipoIncapacidad varchar(200)
					DECLARE @fid date 
						if (datepart(WEEKDAY,@fechaInicialLiquidacion)=7)
						begin
							SET @fid = DATEADD(WK, DATEDIFF(WK,0,dateadd(day,-1,@fechaInicialLiquidacion)), 0)
						end
						else
							SET @fid = DATEADD(WK, DATEDIFF(WK,0,@fechaInicialLiquidacion), 0)
			
					declare curPrestamo insensitive cursor for
					select fechaInicial, fechaFinal,diasPagos,valorPagado, noDias,valor,concepto, a.tipoIncapacidad,b.descripcion  from nIncapacidad a  join nTipoIncapacidad b
					on a.tipoIncapacidad=b.codigo and a.empresa=b.empresa
						where a.tercero=@tercero and anulado=0 and a.empresa=@empresa and (fechaInicial between @fid and @fechaFinalLiquidacion or fechaFinal between @fid and @fechaFinalLiquidacion
						or fechaFinal>@fechaFinalLiquidacion)
						order by fechaInicial
					open curPrestamo			
					fetch curPrestamo into @fi,@ff,@diasIncapacidad,@valorIncapacidad,@DI,@VI,@concepto, @TipoIncapacidad, @desTipoIncapacidad
					while( @@fetch_status = 0 )
					begin	
						if @diasIncapacidad>0
							set @VID=@valorIncapacidad/@diasIncapacidad
						else
							set @VID=@valorIncapacidad
						if @DI>0
							set @VIDE=@VI/@DI
						else
							set @VIDE=@VI
						set @ii=0
						if (@ff>@fechaFinalLiquidacion)
						begin
							set @diasIncapacidad = @diasIncapacidad - case when datediff(DAY,@fechaFinalLiquidacion,@ff)<@diasIncapacidad then 0 
							when  datediff(DAY,@fechaFinalLiquidacion,@ff)= @diasIncapacidad then 0
							when datediff(DAY,@fechaFinalLiquidacion,@ff)>@diasIncapacidad then 0 
							else  datediff(DAY,@fechaFinalLiquidacion,@ff) end 
							set @DI = @DI - datediff(DAY,@fechaFinalLiquidacion,@ff)
							set @ff=@fechaFinalLiquidacion
						end
						if( @fi<@fechaInicialLiquidacion)
						begin
							set @diasIncapacidad = @diasIncapacidad - datediff(DAY,@fi,@fechaInicialLiquidacion)
							set @DI = @DI - datediff(DAY,@fi,@fechaInicialLiquidacion)
							if @diasIncapacidad<0
								set @diasIncapacidad=0
								--set @fi=@fechaInicialLiquidacion
						end

						set @cant=1
						set @fd=@fi
							while(@fi<=@ff)
							begin
							--select @ii,@diasIncapacidad,@fi,@fechaFinalLiquidacion
								if @ii>=@diasIncapacidad
								begin
									set @VID =0
									set @cant = 0
								end
									if @fi between @fid and @fechaFinalLiquidacion
									begin
										SET @lunes = DATEADD(WK, DATEDIFF(WK,0,dateadd(day,-1,@fi)), 0)
										SET @domingo = DATEADD(day,6,@lunes)
										
										if @fi between @fechaInicialLiquidacion and @fechaFinalLiquidacion
										begin
											insert  tmpliquidacionNomina 
											select @empresa, @tercero, @centroCosto, @fi, @departamento, @concepto, @año, @mes, @periodo, 1,0,@VIDE,round(@VIDE,0),signo, 0, @diasIncapacidad,
											@fechaInicialLiquidacion, @fechaFinalLiquidacion,baseSeguridadSocial, baseEmbargo,@entidadEPS,@cant,@VID,@noContrato,null, @TipoIncapacidad, @desTipoIncapacidad
											from nConcepto where empresa=@empresa and codigo=@conceptoIncapacidad

											if (select  cantidad from tmpliquidacionNomina where tercero=@tercero and empresa=@empresa and concepto in (@conceptoSueldo,@aprendizSena))>0
											and @swCero=0
											begin
												update tmpliquidacionNomina set
												valorTotal=valorTotal-(valorTotal/ cantidad),
												cantidad=cantidad-1,
												valorPadelma=valorPadelma-(valorPadelma/ cantidadPadelma),
												cantidadPadelma=cantidadPadelma-1
												where tercero=@tercero and empresa=@empresa and concepto in (@conceptoSueldo,@aprendizSena)
												set @diasDescontado+=1
												--select @diasDescontado, datediff(day,@fechaInicialLiquidacion,@fechaFinalLiquidacion) 
												if @diasDescontado >= datediff(day,@fechaInicialLiquidacion,@fechaFinalLiquidacion) 
												begin
													update tmpliquidacionNomina set
													valorTotal=0,
													cantidad=0,
													valorUnitario=0,
													valorPadelma=0,
													cantidadPadelma=0
													where tercero=@tercero and empresa=@empresa and concepto in (@conceptoSueldo,@aprendizSena)

													update tmpliquidacionNomina set
													valorTotal=0,
													cantidad=0,
													valorUnitario=0,
													valorPadelma=0,
													cantidadPadelma=0
													where tercero=@tercero and empresa=@empresa and concepto in (@conceptoTransporte)
													set @swCero=1

													delete  from tmpliquidacionNomina
													where tercero=@tercero and empresa=@empresa and concepto in (@conceptoSalud,@conceptoPension,@conceptoFondoSolidaridad)
												end
											end
											if exists(select * from  nConcepto where empresa=@empresa and codigo=@concepto and descuentaTransporte=1) and @formaPago <>1 
											begin
												update tmpliquidacionNomina set
												valorTotal=valorTotal- ((valorTotal/case when  cantidad=0 then 1 else cantidad end )),
												valorPadelma=valorPadelma- ((valorPadelma/case when  cantidad=0 then 1 else cantidad end )),
												cantidad= case when  cantidad=0 then 1 else cantidad end-1,
												cantidadPadelma= case when  cantidadPadelma=0 then 1 else cantidad end-1
												where tercero=@tercero and empresa=@empresa and concepto=@conceptoTransporte
											end
										end				
										if exists(select * from  nConcepto where empresa=@empresa and codigo =@concepto and descuentaDomingo=1) 
										begin
											if @fd<>@domingo and (@ultimoDomingo is null or @ultimoDomingo <> @domingo) and @domingo between @fechaInicialLiquidacion and @fechaFinalLiquidacion
											and not exists(select * from nIncapacidad where empresa=@empresa and tercero=@tercero and @domingo between fechaInicial and fechaFinal and anulado=0)
											and not exists(select * from nVacaciones where empresa=@empresa and empleado=@tercero and @domingo between fechaSalida and dateadd(day,-1,fechaRetorno) and anulado=0)
											begin
												update tmpliquidacionNomina set
												valorTotal=valorTotal-(valorTotal/cantidad),
												cantidad=cantidad-1,
													valorPadelma=valorPadelma-(valorPadelma/cantidadPadelma),
												cantidadPadelma=cantidadPadelma-1
												where tercero=@tercero and empresa=@empresa and concepto in (@conceptoSueldo,@aprendizSena)

												if @lDomingo=1
												begin
													insert  tmpliquidacionNomina 
													select @empresa, @tercero, @centroCosto, @domingo, @departamento, codigo, @año, @mes, @periodo, 1,0, 0  ,0,signo, 0, 1, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,1,0,@noContrato,null,'', ''
													from nConcepto where codigo=@pierdeDomingo and empresa=@empresa
												end

												set @fd=@domingo
												set @ultimoDomingo=@domingo

												if exists(select * from  nConcepto where empresa=@empresa and codigo=@concepto and descuentaTransporte=1) and @formaPago <>1
												begin
													update tmpliquidacionNomina set
													valorTotal=valorTotal- ((valorTotal/cantidad)),
													cantidad=cantidad-1,
													valorPadelma=valorPadelma- ((valorPadelma/cantidadPadelma)),
													cantidadPadelma=cantidadPadelma-1
													where tercero=@tercero and empresa=@empresa and concepto=@conceptoTransporte
												end
											end
										end
										else
										begin
												SET @lunes = DATEADD(WK, DATEDIFF(WK,0,dateadd(day,-1,@fi)), 0)
												SET @domingo = DATEADD(day,6,@lunes)

											if @fd <> @domingo and @formaPago=1 
											and not exists(select * from nFestivo where empresa=@empresa and convert(date,fecha)=convert(date,@domingo) )
											and not exists(select * from nIncapacidad where empresa=@empresa and tercero=@tercero and @domingo between fechaInicial and fechaFinal and anulado=0)
											and not exists(select * from nIncapacidad where empresa=@empresa and tercero=@tercero and DATEADD(day, -1, @domingo) between fechaInicial and fechaFinal and anulado=0)
											and not exists(select * from nVacaciones where empresa=@empresa and empleado=@tercero and @domingo between fechaSalida and dateadd(day,-1,fechaRetorno) and anulado=0)
											and @domingo between @fechaInicialLiquidacion and @fechaFinalLiquidacion
											and not exists(select * from tmpliquidacionNomina where empresa=@empresa and tercero=@tercero and concepto=@conceptoGanaDomingo and fecha=@domingo)
											and (exists (select * from nIncapacidad where empresa=@empresa and tercero=@tercero and @fi between fechaInicial and fechaFinal and anulado=0)
											or  exists(select * from nVacaciones where empresa=@empresa and empleado=@tercero and @fi between fechaSalida and dateadd(day,-1,fechaRetorno) and anulado=0))
											and @fi = DATEADD(day, -1, @domingo)
											begin
												exec spLiquidaConceptoTercero @empresa,@año,@conceptoGanaDomingo,@tercero,1,0,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porc output
												insert  tmpliquidacionNomina 
												select @empresa, @tercero, @centroCosto, @domingo, @departamento, codigo, @año, @mes, @periodo, 1,0, @valorUnitario  ,round(@valorUnitario,0),signo, 0, 1, @fechaInicialLiquidacion, @fechaFinalLiquidacion, baseSeguridadSocial, baseEmbargo,null,1,round(@valorUnitario,0),@noContrato,null,'', ''
												from nConcepto where codigo=@conceptoGanaDomingo and empresa=@empresa
												set @fd=@domingo

												update tmpliquidacionNomina set
												valorTotal=valorTotal+ ((valorTotal/cantidad)),
												cantidad=cantidad+1,
												valorPadelma=valorPadelma+ ((valorPadelma/cantidadPadelma)),
												cantidadPadelma=cantidadPadelma+1
												where tercero=@tercero and empresa=@empresa and concepto=@conceptoTransporte
											end
										end
										
									end
									
									if @fi between @fechaInicialLiquidacion and @fechaFinalLiquidacion
										set @ii=@ii+1
									set @fi = DATEADD(day,1,@fi)
							end
						set @tieneIncapacidad =1
					fetch curPrestamo into @fi,@ff,@diasIncapacidad,@valorIncapacidad,@DI,@VI,@concepto, @TipoIncapacidad, @desTipoIncapacidad
					end
					close curPrestamo
					deallocate curPrestamo
		end
		-- Liquida Sindicato
		if @lSindicato=1
		begin
			if @mSindicato=1 and  (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato )>@pagoMinimo
			begin
					if not exists(select sindicato from nParametrosGeneral where empresa=@empresa)
		
					begin
						set @retorno=5
						return
					end
					declare @conceptoSindicato varchar(50)=(select sindicato from nParametrosGeneral where empresa=@empresa)
					select @signo=signo from nConcepto where codigo=@conceptoSindicato and empresa=@empresa
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @conceptoSindicato, @año, @mes, @periodo,1, @porSindicato, @valorBaseSeguridadSocial, round(SUM(valorSS)*@porSindicato/100,0), @signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, 0 , 0,@entidadSindicato,1, round(SUM(valorSS)*@porSindicato/100,0),@noContrato,null, null,null
						from vSeleccionaRealLiquidacion where signo=1 and tercero=@tercero and empresa=@empresa
			end
		end
		--Fondo solidaridad
		if exists (select * from tmpliquidacionNomina where  concepto = @conceptoFondoSolidaridad and empresa=@empresa and tercero=@tercero ) and @lpension=1 
	    begin
				set @valorbaseFS =0
				set @valorbaseFS =isnull((select sum(valorSS) from vSeleccionaRealLiquidacion where empresa=@empresa and baseSeguridadSocial=1 and tercero=@tercero),0)
				set @porcentajeConcepto = isnull((select top 1 porcentaje from nConceptoRango where empresa=@empresa and concepto=@conceptoFondoSolidaridad and (@valorbaseFS) between (minimo/30)*@noDias and (maximo/30)*@noDias),0) -- divido los dias del mes y dias liquidados para fondo solidaridad
				set @manejaPorcentaje = isnull((select top 1  por from nConceptoRango where empresa=@empresa and concepto=@conceptoFondoSolidaridad and (@valorbaseFS) between (minimo/30)*@noDias and (maximo/30)*@noDias),0) -- divido los dias del mes y dias liquidados para fondo solidaridad
				
				if @manejaPorcentaje = 1 and @porcentajeConcepto>0
				begin
					if exists(select *  from tmpliquidacionNomina where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa)
					begin
						update  tmpliquidacionNomina  set
						valorTotal = round(((select round(sum(valorSS),0) from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1)*@porcentajeConcepto/100),0),
						valorPadelma = round(((select round(sum(valorSS),0) from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1)*@porcentajeConcepto/100),0)
						where concepto=@conceptoFondoSolidaridad and empresa=@empresa and tercero=@tercero
					end
				end
				else
				begin
				--select 'paso'
					delete from tmpliquidacionNomina where concepto = @conceptoFondoSolidaridad and empresa=@empresa and tercero=@tercero
				end 
				
			end
			else
			begin
				set @valorbaseFS =0
				set @valorbaseFS =isnull((select sum(valorSS) from vSeleccionaRealLiquidacion where empresa=@empresa and baseSeguridadSocial=1 and tercero=@tercero),0)
				set @porcentajeConcepto = isnull((select porcentaje from nConceptoRango where empresa=@empresa and concepto=@concepto and (@valorbaseFS) between (minimo/30)*@noDias and (maximo/30)*@noDias),0) -- divido los dias del mes y dias liquidados para fondo solidaridad
				set @manejaPorcentaje = isnull((select por from nConceptoRango where empresa=@empresa and concepto=@concepto and (@valorbaseFS) between (minimo/30)*@noDias and (maximo/30)*@noDias),0) -- divido los dias del mes y dias liquidados para fondo solidaridad
				if @manejaPorcentaje = 1 and @porcentajeConcepto>0
				begin
					
					if exists(select *  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa)
					begin
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto, @año, @mes, @periodo,1, @porcentajeConcepto, @valorBaseSeguridadSocial, round((SUM(valorSS)*@porcentajeConcepto/100),0), @signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,@entidadEPS,1,round((SUM(valorSS)*@porcentajeConcepto/100),0),@noContrato,null, null,null
						from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1
					end
				end
			end
		-- Recorrer cursor de conceptos a liquidad------------------------------------------------------------------------------------------------------------------------------------------------
		if @swControlNovedades=0
		begin
			delete @conceptosLiquidar
			insert @conceptosLiquidar
			select 0, b.concepto, d.tipoLiquidacion, d.signo, d.baseSeguridadSocial, d.validaPorcentaje, d.porcentaje, b.valor, d.baseEmbargo,1,b.cantidad,a.fecha,d.prioridad,0
			from nnovedades a 
			join  nNovedadesDetalle b on a.tipo = b.tipo and a.numero = b.numero and a.empresa=b.empresa 
			join nConcepto d on d.codigo=b.concepto and d.empresa=b.empresa 
			where  a.empresa=@empresa and @año  between b.añoInicial and b.añoFinal
			and b.empleado=@tercero AND a.anulado=0 and b.anulado=0
			and (@periodo between   (case when b.añoInicial =@año then b.periodoInicial else 0 end) and 
			(case when b.añoFinal=@año then b.periodoFinal else 99999999 end))
			and b.concepto not in (select concepto from tmpliquidacionNomina where empresa=@empresa and tercero=@tercero)

			declare cursorConceptos insensitive cursor for
			select    acumulada,concepto, tipoLiquidacion,  signo, baseSeguridadSocial, validaPorcentaje, porcentaje, valor, baseEmbargo, tipoNovedadLiquida, cantidadNovedad,fecha,prioridad,registro
			from @conceptosLiquidar 
			order by signo,prioridad,concepto asc
			open cursorConceptos	
			fetch cursorConceptos into  @acumulada, @concepto, @tipoLiquidacion, @signo,@baseSeguridadSocial,@manejaPorcentaje, @porcentajeConcepto,@valorConcepto, @baseEmbargo, @tipoNovedadLiquida,@cantidadNovedad,@fechaNovedadNomina,@prioridadConcepto , @registronovedad
			while( @@fetch_status = 0 )
			begin	
			-- Liquida novedades de nomina
			if @lNovedades=1 or @lNovedadesCredito=1
			begin
				set @noMes= isnull((select nomes from nConcepto where empresa=@empresa and codigo=@concepto),0)
			set @contMes = ISNULL((select count(*) from vSeleccionaLiquidacionDefinitiva where empresa=@empresa and  mes=@mes and año=@año and codConcepto=@concepto and anulado=0 and codTercero=@tercero ),0)
			set @contMes = @contMes +  ISNULL((select count(*) from tmpliquidacionNomina where empresa=@empresa and  mes=@mes and año=@año and concepto=@concepto  and tercero=@tercero),0)
			set @contMes = @contMes + isnull((select count(*) from vSeleccionaVacaciones where empleado=@tercero and empresa=@empresa and anulado=0 and concepto=@concepto and month(fechaSalida)=@mes ),0)
			
			if (@tipoNovedadLiquida=1) 
			begin
				if exists(select signo from nConcepto where codigo=@concepto and empresa=@empresa and signo=2 and  @lNovedadesCredito=1 and @lNovedades=0 )
				begin
					if (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato )>@pagoMinimo
					begin
						if @noMes>@contMes
							begin
							if @lNovedadesCredito=1
							begin
								exec spLiquidaConceptoTercero  @empresa,@año, @concepto,@tercero,@cantidadNovedad,@valorConcepto,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porcentajeConcepto output
								insert  tmpliquidacionNomina 
								select @empresa, @tercero, @centroCosto, @fechaNovedadNomina, @departamento, @concepto, @año, @mes, @periodo, @cantidad,@porcentajeConcepto, round(@valorUnitario,0),round(@valorUnitario,0),@signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null, null,null
							end
						end
					end
				end
				else
				begin
				if exists(select signo from nConcepto where codigo=@concepto and empresa=@empresa and signo=1 and  @lNovedades=1  and @lNovedadesCredito=0)
				begin
					if @noMes>@contMes
					begin
						exec spLiquidaConceptoTercero  @empresa,@año, @concepto,@tercero,@cantidadNovedad,@valorConcepto,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porcentajeConcepto output
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fechaNovedadNomina, @departamento, @concepto, @año, @mes, @periodo, @cantidad,@porcentajeConcepto, round(@valorUnitario,0),round(@valorUnitario,0),@signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null,'',''
					end
				end
				else
				if exists(select signo from nConcepto where codigo=@concepto and empresa=@empresa and signo in (1,2) and  @lNovedades=1 and @lNovedadesCredito=1 )
				begin
					if (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato )>@pagoMinimo
					begin
					if @noMes>@contMes
						begin
						if @lNovedadesCredito=1
						begin
							exec spLiquidaConceptoTercero  @empresa,@año, @concepto,@tercero,@cantidadNovedad,@valorConcepto,@noDias,@fechaNovedadNomina,@formaPago,@Valorunitario output,@cantidad output,@porcentajeConcepto output
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fechaNovedadNomina, @departamento, @concepto, @año, @mes, @periodo, @cantidad,@porcentajeConcepto, round(@valorUnitario,0),round(@valorUnitario,0),@signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, @baseSeguridadSocial, @baseEmbargo,null,@cantidad,round(@valorUnitario,0),@noContrato,null,'',''
						end
						end
					end 
				end

				end
			end
			end
			fetch cursorConceptos into 	 @acumulada , @concepto,@tipoLiquidacion, @signo,@baseSeguridadSocial,@manejaPorcentaje, @porcentajeConcepto,@valorConcepto,@baseEmbargo, @tipoNovedadLiquida,@cantidadNovedad,@fechaNovedadNomina,@prioridadConcepto, @registronovedad
			end
		close cursorConceptos
		deallocate cursorConceptos
		end
		-- Liquida Seguridad Social Pension
		if exists (select * from tmpliquidacionNomina where tercero=@tercero and empresa=@empresa and concepto =@conceptoPension  and noContrato=@noContrato) and @lpension=1
			begin
			
				if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa and noContrato=@noContrato)>0
				begin
					update  tmpliquidacionNomina  set
					valorTotal = round(((select round(sum(valorSS),0) from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1 and noContrato=@noContrato)*porcentaje/100),0),
					valorPadelma = round(((select round(sum(valorSS),0) from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1 and noContrato=@noContrato)*porcentaje/100),0)
					where concepto=@conceptoPension and empresa=@empresa and tercero=@tercero
				end
				if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa and noContrato=@noContrato)=0
				begin
					delete from tmpliquidacionNomina
					where concepto=@conceptoPension and empresa=@empresa and tercero=@tercero
				end

			end
			else
			begin
				if @lpension=1 and  (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato)>@pagoMinimo
				begin
					if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa and noContrato=@noContrato)>0
					begin
					if exists (select * from nConceptosFijosDetalle where concepto=@conceptoSalud and noPeriodo=@periodo and año=@año and centroCosto=@centroCosto)
					 begin
						set @porcentajeConcepto = (select top 1 porcentaje from nConcepto where codigo= @conceptoPension and empresa=@empresa)
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @conceptoPension, @año, @mes, @periodo,1, @porcentajeConcepto, @valorBaseSeguridadSocial, round((SUM(valorSS)*@porcentajeConcepto/100),0), b.signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, b.baseSeguridadSocial, 
						b.baseEmbargo,@entidadEPS,1,round((SUM(valorSS)*@porcentajeConcepto/100),0),@noContrato,null,'',''
						from vSeleccionaRealLiquidacion a
						left join nConcepto b on b.empresa=@empresa and codigo=@conceptoPension
						where tercero=@tercero and a.empresa=@empresa and a.baseSeguridadSocial=1  and noContrato=@noContrato
						group by b.baseEmbargo,b.baseSeguridadSocial,b.signo
					end
					end
				end
			end
		-- Liquida Seguridad Social EPS
		if exists (select * from tmpliquidacionNomina where tercero=@tercero and empresa=@empresa and concepto =@conceptoSalud  and noContrato=@noContrato)  and @lsalud=1 and @electivaProduccion=0
			begin
				if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa and noContrato=@noContrato)>0
				begin
					update  tmpliquidacionNomina  set
					valorTotal = round(((select sum(valorSS) from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1  and noContrato=@noContrato)* porcentaje/100),0),
					valorPadelma = round(((select sum(valorSS) from vSeleccionaRealLiquidacion where tercero=@tercero and empresa=@empresa and baseSeguridadSocial=1  and noContrato=@noContrato)* porcentaje/100),0)
					where concepto=@conceptoSalud and empresa=@empresa and tercero=@tercero
				end

				if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa and noContrato=@noContrato)=0
				begin
					delete from   tmpliquidacionNomina 
					where concepto=@conceptoSalud and empresa=@empresa and tercero=@tercero
				end
			end
			else
			begin
				if @electivaProduccion=0 and  (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato )>@pagoMinimo and @lsalud=1
				begin
					if (select sum(valorTotal)  from tmpliquidacionNomina	where baseSeguridadSocial=1 and tercero=@tercero and empresa=@empresa and noContrato=@noContrato)>0
					begin
					 if exists (select * from nConceptosFijosDetalle where concepto=@conceptoSalud and noPeriodo=@periodo and año=@año and centroCosto=@centroCosto)
					 begin
						set @porcentajeConcepto = (select porcentaje from nConcepto where codigo= @conceptoSalud and empresa=@empresa)
						insert  tmpliquidacionNomina 
						select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @conceptoSalud, @año, @mes, @periodo,1, @porcentajeConcepto, @valorBaseSeguridadSocial, round((SUM(valorSS)*@porcentajeConcepto/100),0), b.signo, 0,
						 @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion,b.baseSeguridadSocial, b.baseEmbargo,@entidadEPS,1,round((SUM(valorSS)*@porcentajeConcepto/100),0),@noContrato,null,'',''
						from vSeleccionaRealLiquidacion a
						left join nConcepto b on b.empresa=@empresa and codigo=@conceptoSalud
						 where tercero=@tercero and a.empresa=@empresa and a.baseSeguridadSocial=1
						 group by b.baseEmbargo,b.baseSeguridadSocial,b.signo
					end
					end
				end
			end
		-- Liquidacion de prestamos	-----------------------------------------------------------------------------------------------------------------------------------------------------------	
		if @lPrestamo=1 
		begin

				declare @noPrestamo varchar(50),@conceptoInteresPrestamo varchar(50)
				declare curPrestamo insensitive cursor for
					select valorCuotas,	a.valorSaldo, a.concepto,signo,a.codigo,a.conceptoInteres	from nPrestamo a 
					join nConcepto b on a.concepto=b.codigo and a.empresa=b.empresa
					where a.empleado=@tercero and a.empresa=@empresa and valorSaldo>0 and (convert(varchar,año)+rtrim(RIGHT('00' + rtrim(periodoInicial), 2))) <= ( convert(varchar,@año)+rtrim(RIGHT('00' + convert(varchar,@periodo), 2)))  -- le fataba el rigth para completar los ceros del periodo ricardo gomez 14/02/2017
					and b.activo=1
					ORDER BY prioridad ASC
					open curPrestamo			
					fetch curPrestamo into @valorCuota, @valorSaldo, @concepto,@signo,@noPrestamo,@conceptoInteresPrestamo
					while( @@fetch_status = 0 )
					begin	
						if @valorCuota> @valorSaldo
							set @valorUnitario = @valorSaldo
						else
							set @valorUnitario = @valorCuota
							if @valorUnitario>(select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato)
							begin
								set @valorUnitario = (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa and noContrato=@noContrato) -@pagoMinimo
							end

							if (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa )>@pagoMinimo
							begin

							if (select tipoPrestamo from nPrestamo where empresa=@empresa and codigo=@noPrestamo) =0
							begin
							    insert  tmpliquidacionNomina 
								select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, concepto, @año, @mes, @periodo, 0,0, valorCuotas,round(valorCuotas,0),signo,valorSaldo - valorCuotas, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion,baseSeguridadSocial, baseEmbargo,null,1,round(valorCuotas,0),@noContrato,@noPrestamo,'',''
								from nConcepto a 
								join nPrestamo b on b.empresa=a.empresa and a.codigo=b.concepto  
								where  a.empresa=@empresa and b.codigo=@noPrestamo  --and b.registroCuota in (select min(registroCuota) from nPrestamoDetalle where codigo=@noPrestamo and empresa=@empresa and pagado=0 )
							end
							else
							begin
							
								set @registroCuotaPrestamo =(select min(registroCuota) from nPrestamoDetalle where codigo=@noPrestamo and empresa=@empresa and pagado=0 )

								select @amortizacionPrestamo= amortizacion, @saldoPrestamo=final, @interesesPrestamo=interes from nPrestamo a join nPrestamoDetalle b on a.codigo=b.codigo and a.empresa=b.empresa 
								where a.codigo=@noPrestamo and b.registroCuota= @registroCuotaPrestamo

								-- concepto del prestamo
								insert  tmpliquidacionNomina 
								select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @concepto , @año, @mes, @periodo, @registroCuotaPrestamo,0, @amortizacionPrestamo,round(@amortizacionPrestamo,0),signo,@saldoPrestamo, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion,baseSeguridadSocial, baseEmbargo,null,1,round(@amortizacionPrestamo,0),@noContrato,@noPrestamo,'',''
								from nConcepto
								where codigo=@concepto and empresa=@empresa
								-- concepto de intereses del prestao
								insert  tmpliquidacionNomina 
								select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @conceptoInteresPrestamo, @año, @mes, @periodo, @registroCuotaPrestamo,0, @interesesPrestamo ,round(@interesesPrestamo,0),signo,0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion,baseSeguridadSocial, baseEmbargo,null,1,round(@interesesPrestamo,0),@noContrato,@noPrestamo,'',''
								from nConcepto
								where codigo=@conceptoInteresPrestamo and empresa=@empresa
							

								end
							end
					fetch curPrestamo into @valorCuota, @valorSaldo, @concepto,@signo,@noPrestamo,@conceptoInteresPrestamo
					end
					close curPrestamo
					deallocate curPrestamo
		end
		-- Liquida embargos
		if @lEmbargo=1
		begin
			if exists (select * from nEmbargos where  empleado=@tercero and empresa=@empresa and activo=1 ) and (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa)>@pagoMinimo
				begin
					if (select embargos from nParametrosGeneral where empresa=@empresa) is not null
					begin
						declare @tipoEmbargo varchar(50),  @destipoEmbargo varchar(200),@codigoEmbargo int
						declare @conceptoEmbargo varchar(50) = (select embargos from nParametrosGeneral where empresa=@empresa)

							set @valorBaseEmbargo = (select sum(case when signo = 1 then 
							case when concepto=@conceptoPrima and @tipoEmbargo='EC' then 0 else  valorTotal end 
							else (-1)*case when concepto=@conceptoPrima and @tipoEmbargo='EC' then 0 else  valorTotal end end) 
							from tmpliquidacionNomina where baseEmbargos = 1 and tercero=@tercero and empresa=@empresa)

							set @diasEmbargo = (select sum(diasNomina) from nPeriodoDetalle where empresa=@empresa and noPeriodo=@periodo and año=@año)

						declare cursorEmbargo insensitive cursor for
						select    a.codigo,tipo,b.descripcion from nEmbargos a join gTipoEmbargo b on b.codigo=a.tipo and b.empresa=a.empresa
						 where a.empresa=@empresa and empleado=@tercero and a.activo=1 
						order by b.codigo asc
						open cursorEmbargo	
						fetch cursorEmbargo into @codigoEmbargo,@TipoEmbargo,@desTipoEmbargo
						while( @@fetch_status = 0 )
						begin
						--select @valorBaseEmbargo, @codigoEmbargo, @diasEmbargo
							exec spliquidaEmbargosNomina @empresa , @tercero,@valorBaseEmbargo ,@pagominimo,@diasEmbargo, @periodo,@año,@codigoEmbargo,@valorUnitario output 
							insert  tmpliquidacionNomina 
							select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @conceptoEmbargo, @año, @mes, @periodo, 1,0, @valorUnitario,round(@valorUnitario,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion,baseSeguridadSocial, @baseEmbargo,null,1,round(@valorUnitario,0),
							@noContrato,@codigoEmbargo,@tipoEmbargo, @desTipoEmbargo
							from nConcepto where empresa=@empresa and codigo=@conceptoEmbargo
							fetch cursorEmbargo into 	 @codigoEmbargo,@TipoEmbargo,@desTipoEmbargo
						end
						end
						close cursorEmbargo
						deallocate cursorEmbargo
				end
		end
		-- Liquida Fondo Empleado
		if @lFondavi = 1
		begin

		if @mFondoEmpleado=1 and (select sum(valortotal) from vSeleccionaDiferenciaLiquidacionTercero where tercero=@tercero and empresa=@empresa  and noContrato=@noContrato)>@pagoMinimo
		begin
			declare @valorFondavi float=0,@conceptoFondo varchar(50)
					if not exists(select fondoEmpleado from nParametrosGeneral where empresa=@empresa)
					begin
						set @retorno=6
						return
					end
					set @conceptoFondo =(select fondoEmpleado from nParametrosGeneral where empresa=@empresa)
					if @noDias=0
						set @valorFondavi =(round((@valorDiaSueltoTercero* @cantTransporte)*@porFondoEmpleado/100,0)) 
					else
						set @valorFondavi =(round((@valorDiaSueltoTercero* @noDias)*@porFondoEmpleado/100,0)) 
					insert  tmpliquidacionNomina 
					select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, @conceptoFondo, @año, @mes, @periodo,1, @porSindicato, @valorDiaSueltoTercero, round(@valorFondavi,0), signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion, 0 , 0,@entidadFE,1, (@valorDiaSueltoTercero* @noDias)*@porFondoEmpleado/100,@noContrato,null,'',''
					from nConcepto where codigo=@conceptoFondo and empresa=@empresa
			end
		end

		if ( select SUM(case when signo=1 then valorTotal else valorTotal*-1 end) from tmpliquidacionNomina where empresa=@empresa and tercero=@tercero and año=@año and noPeriodo=@periodo )<0
		begin

			declare @valorAutomatico float, @descuentoAutomatico varchar(50),@devengoAutomatico varchar(50)
			
			set @valorAutomatico = abs((select SUM(case when signo=1 then valorPadelma else valorPadelma*-1 end) from tmpliquidacionNomina where empresa=@empresa and tercero=@tercero and año=@año and noPeriodo=@periodo ))
			select @descuentoAutomatico=descuentoAutomatico,@devengoAutomatico=devengoAutomatico from gParametros where empresa=@empresa

			insert  tmpliquidacionNomina 
			select @empresa, @tercero, @centroCosto, @fecheGeneral, @departamento, codigo, @año, @mes, @periodo, 1,0, round(@valorAutomatico,0),round(@valorAutomatico,0),signo, 0, @noDias, @fechaInicialLiquidacion, @fechaFinalLiquidacion,baseSeguridadSocial, @baseEmbargo,null,1,round(@valorAutomatico,0),
			@noContrato,@codigoEmbargo,@tipoEmbargo, @desTipoEmbargo
			from nConcepto where empresa=@empresa and codigo=@devengoAutomatico

		end

		end
		else
		begin
			if @validaVacaciones=0
			begin
				set @retorno=55
				set @nombreTercero = (select descripcion from cTercero where empresa=@empresa and id=@tercero)
				delete from tmpliquidacionNomina where empresa=@empresa
				close cursorFuncionarios
				deallocate cursorFuncionarios 
				return 
			end 
		end	
		fetch cursorFuncionarios into 	@tercero, 	@codigo,	@validaTurno, 	@departamento , 	@centroCosto, 	@tipoNomina,	@tiempoBasico,	@fechaIngreso,
		@fechaContratoHasta,	@salario,	@cantidadHoras,	@salarioIntegral,	@terminoContrato,	@diasContrato,	@Contratofijo,	@noContrato, @formaPago, @tipoNomina,@ganaTransporte,
		@mFondoEmpleado,@mSindicato,@porFondoEmpleado,@porSindicato,@entidadFE,@entidadEPS,@entidadPension,@entidadSindicato,@electivaProduccion,@pEEP,@noDias,@fechaRetiro,@Contratodefinitivo,@lpension,@lsalud
	end	
	
	close cursorFuncionarios
	deallocate cursorFuncionarios 

--go
--declare @retorno int,
--@nombreTercero varchar(550),
--@empresa int =1,
--@periodo int=9,
--@tercero varchar(50)='238'
--exec spLiquidacionNominaPeriodo 2018,1,@periodo,@empresa,'',@tercero,'15-02-2016',3,@retorno output,@nombreTercero output
--select b.descripcion,c.descripcion,a.* from tmpliquidacionNomina a
--join cTercero b on b.id=a.tercero and b.empresa=a.empresa
--join nConcepto c on c.codigo=a.concepto and c.empresa=a.empresa
--where a.empresa=@empresa


