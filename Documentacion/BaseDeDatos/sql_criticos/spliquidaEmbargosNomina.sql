-- Procedimiento: spliquidaEmbargosNomina
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE proc [dbo].[spliquidaEmbargosNomina]
@empresa int,
@empleado int,
@valorBaseEmbarg money,
@pagominimo money,
@diasLiquidados int,
@periodo int,
@año int, 
@codigoEmbargo int,
@valor  money output
--@tipoEmbargo varchar(50) output, 
--@destipoEmbargo varchar(250) output
as

       declare @pagoSueldo money,
       @valorDescontar money=0,
       @diasFijos int=30,
       @tipo varchar(10),
       @manejaCuota int ,@manejaCuotaPosterior int, @manejaSaldo int,
       @valorFinal money, @valorCobroPosterior money, @pCobroPosterior decimal(18,3),
       @saldo money, @cobroPosterior bit, @valorBase money, @porcentaje money    , @valorCuota money ,
       @valorBaseParcialEmbargo money, @valorSalarioMinimo money, @SalarioMinimo bit, @tipoEmbargo varchar(50), @destipoembargo varchar(250)

	   select @valorSalarioMinimo = isnull(vSalarioMinimo,0) from nParametrosAno
	   where empresa=@empresa and ano=@año 

	   select @valorSalarioMinimo = @valorSalarioMinimo/@diasFijos
       set @valorBaseParcialEmbargo = @valorBaseEmbarg

       declare curEM insensitive cursor for
       select a.tipo,a.manejaCuota,a.manejaCuotaPosterior,a.valorBase, a.manejaSaldo,a.valorCuotas, a.porcentaje, a.valorfinal, a.valorCobroPosterior,a.pcobroPosterior, a.saldo, a.cobroPosterior,
	   a.tipo, b.descripcion , a.salarioMinimo from nEmbargos a join gTipoEmbargo b on a.tipo=b.codigo and a.empresa=b.empresa
       where a.empresa=@empresa and empleado=@empleado and a.activo=1 and a.codigo=@codigoEmbargo
       order by fecha

       open curEM                 
       fetch curEM into @tipo,@manejaCuota  ,@manejaCuotaPosterior ,@valorBase, @manejaSaldo ,@valorCuota, @porcentaje, @valorfinal, @valorCobroPosterior,@pcobroPosterior, @saldo, @cobroPosterior,
	   @tipoEmbargo, @desTipoEmbargo, @SalarioMinimo

       while( @@fetch_status = 0 )
       begin  


       --if @valorDescontar - @pagominimo <> 0
       --begin        
	   		--select @valorBaseParcialEmbargo
                    if @valorCuota > 0 
                    begin
					
                           if @valorBaseParcialEmbargo - @valorCuota <    @pagominimo
                           begin
							set @valorDescontar = 0
                           end          
                           else 
                           begin
                           set @valorDescontar = @valorDescontar+ @valorCuota
                           end          
                           set @valorBaseParcialEmbargo = @valorBaseParcialEmbargo - @valorDescontar

						   --select @valorBaseParcialEmbargo 
                    end

			

					if @SalarioMinimo=1
					begin
						if @porcentaje > 0 
						begin
								if @valorBaseParcialEmbargo - ( @valorBaseEmbarg - @valorSalarioMinimo*@diasLiquidados - @valorCuota)*@porcentaje/100 <    @pagominimo and @valorBaseParcialEmbargo - ( @valorBaseEmbarg -  @valorSalarioMinimo*@diasLiquidados - @valorCuota)*@porcentaje/100 > 0
							   begin
							   set @valorDescontar =  @valorDescontar+  @valorBaseParcialEmbargo -@valorSalarioMinimo*@diasLiquidados - @pagominimo
							   end          
							   else 
							   begin 
							   set @valorDescontar = @valorDescontar+  ( @valorBaseEmbarg- @valorSalarioMinimo*@diasLiquidados - @valorCuota)*@porcentaje/100
							   end
							   set @valorBaseParcialEmbargo = @valorBaseParcialEmbargo - @valorDescontar
						end

					end
					else
					begin
					if @porcentaje > 0 
                    begin
                            if @valorBaseParcialEmbargo - ( @valorBaseEmbarg - @valorCuota)*@porcentaje/100 <    @pagominimo and @valorBaseParcialEmbargo - ( @valorBaseEmbarg  - @valorCuota)*@porcentaje/100 > 0
                           begin
                           set @valorDescontar =  @valorDescontar+  @valorBaseParcialEmbargo - @pagominimo
                           end          
                           else 
                           begin 
                           set @valorDescontar = @valorDescontar+  ( @valorBaseEmbarg-  @valorCuota)*@porcentaje/100
                           end
                           set @valorBaseParcialEmbargo = @valorBaseParcialEmbargo - @valorDescontar
                    end
					end
             --end
       
	   --select @valorDescontar+@valorCobroPosterior

       set @valor= isnull(@valorDescontar,0) + isnull(@valorCobroPosterior,0)
             
       fetch curEM into   @tipo,@manejaCuota  ,@manejaCuotaPosterior ,@valorBase, @manejaSaldo ,@valorCuota, @porcentaje, @valorfinal, @valorCobroPosterior,@pcobroPosterior, @saldo, @cobroPosterior,
	   @tipoEmbargo, @desTipoEmbargo, @SalarioMinimo

       end

       close curEM
       deallocate curEM
       
	   --select @valorBaseParcialEmbargo, @valor

	   if  @valor<0 
	   begin
	   set @valor=0
	   end


