-- Procedimiento: SpSeleccionaPreLiquidacionHora
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

-- Batch submitted through debugger: SQLQuery12.sql|0|0|C:\Users\DESARR~1\AppData\Local\Temp\~vsB378.sql
CREATE proc [dbo].[SpSeleccionaPreLiquidacionHora]
@FechaI date,
@FechaF date,
@empresa int,
@Codfuncionario varchar(50)
as	
	declare @horaTurno int,
			@fechaActual datetime = NULL,
			@i int, @horasTurnoN int,
			@horaIdiurno int, @horaFdiurno int,
			@horaInocturno int, @horaFnocturno int,
			@hEntrada datetime, @hSalida datetime,
			@hTurno int, @hExtras int,
			@funcionario varchar(50), @hInicio int,
			@fechaP date, @HE int, @HS int,@HD INT ,@HEDD INT,@HEND INT,@RND INT,
			@HDsw bit ,@HEDDsw bit,@HENDsw bit,@RNDsw bit,
			@HT int, @RNF float,@RN float,
			@HED float,	@HEDF float,@HEN float,
			@HENF float,@HF float,@HFsw bit,
			@HEsw bit,@HSsw bit,@HTsw bit,
			@RNFsw bit,@RNsw bit,@HEDsw bit,
			@HEDFsw bit,@HENsw bit,	@HENFsw bit,@sw bit, @ME int, @HorasTotales float=-1 , 
			@CodTurno varchar(50),@cuadrilla varchar(50)
	delete from tmpLiquidacionHoras
	where empresa =@empresa
	
	
	declare curMov insensitive cursor for
	select horaentrada,horasalida,horasturno,(isnull(a.horasextras,0) + isnull(b.cantidad,0)),
	a.funcionario,horaInicio,a.fecha,a.turno,a.cuadrilla  from nprogramacion a
	left join nhorasextras b on b.fecha=a.fecha and b.turno=a.turno and b.funcionario=a.funcionario
	where a.fecha between @fechaI and @fechaF and a.empresa=@empresa
	and estado in ('P','S') and a.funcionario = @CodFuncionario
	open curMov
	fetch curMov into @hEntrada,@hSalida,@horasTurnoN,@hExtras,@funcionario,@hInicio,@fechaP,@CodTurno,@cuadrilla
	
	while( @@FETCH_STATUS = 0 )
	begin
	select @horasTurnoN = jornadaDiaria, @horaIdiurno = horaInicioDiurna, @horaInocturno=horaInicioNocturna , @horaFdiurno =horaFinalDiurna, @horaFnocturno=horaFinalNocturna 
	from nParametrosGeneral where empresa=@empresa		
	select @RNF =0,	@RN =0, @HED =0, @HEDF =0,@HD=0,@RND=0,@HEDD=0,@HEND=0,			@HENF =0,	@HEN =0, @HF =0, @sw = 0, @HorasTotales=-1

	if (datepart(HOUR,@hEntrada)<(@hInicio/100))
	begin
		set @HE = @hInicio/100
		set @ME = DATEPART(minute,@hsalida)
	end
	else
	begin
		set @HE = datepart(HOUR,@hEntrada)
		set @ME = DATEPART(minute,@hsalida)-DATEPART(minute,@hEntrada)
	end

	if (CONVERT(date,@hSalida) > CONVERT(date,@hEntrada))
	begin
		if (datepart(hour,@hSalida)+24 > DATEPART(hour,@hEntrada)+@horasTurnoN+@hExtras)
			set @HS = DATEPART(hour,@hEntrada)+@horasTurnoN+@hExtras
		else
			set @HS = datepart(HOUR,@hSalida) + 24
	end
	else
	begin
		if (datepart(hour,@hSalida) > DATEPART(hour,@hEntrada)+@horasTurnoN+@hExtras)
			set @HS = DATEPART(hour,@hEntrada) + @horasTurnoN + @hExtras
		else
			set @HS = datepart(HOUR,@hSalida)
	end
	
	SELECT  @i = @HE, @HT = 0, @fechaActual = dateadd(HOUR,@HE,convert(datetime,@fechaP))
	
	--select @HS, @i,@horaFdiurno
	WHILE (@i <= @HS)
	BEGIN
	
	select @RNFsw =0,	@RNsw =0, @HEDsw =0, @HEDFsw =0,@HDsw=0,@RNDsw=0,@HEDDsw=0,@HENDsw=0,@HENFsw =0,	@HENsw =0, @HFsw =0
			
	-- Calculo de Recargos
	if (@HT <= @horasTurnoN and @HT>0)
	begin
		if (@i > @horaInocturno and @i <= @horaFnocturno )
		begin
		if exists( select fecha from nFestivo where convert(date,fecha) = convert(DATE,@fechaActual) ) 
		Begin
			if (datepart(WEEKDAY,@fechaActual)=7)
			begin
				set @RND += 1
				set @RNDsw = 1
			end
			else
			begin
				set @RNF += 1
				set @RNFsw = 1
			end
		end		
		else
		begin
			set @RN +=1
			set @RNsw = 1
			--select @i,@HT,@horaFnocturno
		end
		end
	end
	-- Horas extras diurnas
	if (@HT > @horasTurnoN)
	begin
		if ( @i > @horaIdiurno and @i <= @horaFdiurno)
		begin
			if exists( select fecha from nFestivo where convert(date,fecha) = convert(DATE,@fechaActual) )
			begin
				if (datepart(WEEKDAY,@fechaActual)=7)
				begin
					set @HEDD += 1
					set @HEDDsw = 1
				end
				else
				begin
					set @HEDF += 1
					set @HEDFsw = 1
				end
			end
			else
			begin
				set @HED += 1
				set @HEDsw=1
			end
		end
	end
	-- Horas festivos
	if (@HT < @horasTurnoN)
	begin
		if ( @i >= @horaIdiurno and @i <= @horaFdiurno) 
		begin
			if exists( select fecha from nFestivo where convert(date,fecha) = convert(DATE,@fechaActual)) or (datepart(WEEKDAY,@fechaActual)=7)
			begin
				if (datepart(WEEKDAY,@fechaActual)=7)
				begin
						set @HD += 1
						set @HDsw = 1
				end
				else
				begin
						set @HF += 1
						set @HFsw = 1
				end
			end
		end
	end
	
	-- Horas extras nocturna
	if (@HT > @horasTurnoN)
	begin
		if ( @i > @horaInocturno and @i <= @horaFnocturno)
		begin
		
			if exists( select fecha from nFestivo where convert(date,fecha) = convert(DATE,@fechaActual) )
			begin
				if (datepart(WEEKDAY,@fechaActual)=7)
				begin
					set @HENDsw = 1
					set @HEND  += 1
				end
				else
				begin
					set @HENFsw = 1
					set @HENF  += 1
				end
			end
			else
			begin
				set @HENsw = 1
				set @HEN += 1
				
			end
		end
	end
	IF @i=24
		set @fechaActual = DATEADD(DAY,1,@fechaActual)
	set @i += 1
	set @HT += 1
	set @HorasTotales+=1
		
		
	if @i = 24 and @sw =0
	begin
		set @sw = 1
		set @horaIdiurno +=24
		set @horaFdiurno +=24
	end
	END
	if (@ME>0)
	begin
		if (ABS(@ME)>30)
		begin
			set @HorasTotales+=0.5
			if @RNFsw = 1 and @HF<@horasTurnoN 
				set @RNF =@RNF + 0.5
			if @RNsw = 1 and @HF<@horasTurnoN 
				set @RN =@RN + 0.5
			if @HEDsw = 1 and @HED<@hExtras
				set @HED =@HED + 0.5
			if @HEDFsw = 1 and @HEDF<@hExtras
				set @HEDF =@HEDF + 0.5
			if @HENFsw = 1 and @HENF<@hExtras
				set @HENF =@HENF + 0.5
			if @HENsw = 1 and @HEN<@hExtras
				set @HEN =@HEN + 0.5
			if @HFsw = 1 and @HF<@horasTurnoN 
				set @Hf =@HF + 0.5
		end
	end
	else
	begin
		if (ABS(@ME)>30)
		begin
		set @HorasTotales-=0.5
			if @RNFsw = 1 
				set @RNF =@RNF - 0.5
			if @RNsw = 1
				set @RN =@RN - 0.5
			if @HEDsw = 1
				set @HED =@HED - 0.5
			if @HEDFsw = 1
				set @HEDF =@HEDF - 0.5
			if @HENFsw = 1
				set @HENF =@HENF - 0.5
			if @HENsw = 1
				set @HEN =@HEN - 0.5
			if @HFsw = 1 and @HF<@horasTurnoN
				set @Hf =@HF + 0.5
		end
	end
		
	insert tmpLiquidacionHoras
	select @empresa, @fechaP fechaP, @funcionario funcionario,@horasTurnoN hTurno,@hExtras hExtras,@hEntrada horaEntrada,@hSalida horaSalida,@HED HED, @HEN HEN, @RN RN,@HD HD,@HEDD , @HEND , @RND ,@HF + @RNF ,@HEDF , @HENF , @RNF , DATEDIFF(HOUR,dateadd(hour,1,@hEntrada),@hSalida) HTS, @CodTurno turno,@cuadrilla
		
	set @HorasTotales=0
	fetch curMov into  @hEntrada,@hSalida,@horasTurnoN,@hExtras,@funcionario,@hInicio,@fechaP,@CodTurno,@cuadrilla
	end
	close curMov
	deallocate curMov


   --select a.*, DATENAME(weekday,fechaP) diaSemana , c.descripcion  descripcion, 
   -- f.descripcion nombreCuadrilla,e.descripcion DesTurno,
   -- e.horaInicio horaInicio,'PROGRAMACIÓN' Tipo,0 tipoP,a.hTurno+a.nExtra THP, (a.hTurno+a.nExtra)-a.HTL as DIF   from tmpLiquidacionHoras  a
   --join nFuncionario c on a.funcionario=c.tercero and a.empresa=c.empresa
   --join nTurno e on a.CodTurno=e.codigo and e.empresa=a.empresa
   --LEFT join nCuadrilla f on f.codigo=a.cuadrilla and f.empresa=a.empresa
   --where a.empresa=@empresa
   
       
   --drop table #liquidacion
