-- Procedimiento: spGuardaContabilizacion
-- Extraido de AppPalma (172.28.254.26) via consultaweb, solo lectura

CREATE proc [dbo].[spGuardaContabilizacion]
 @año int,
 @periodo int,
 @tipo varchar(4),
 @empresa int,
 @noComprobante int,
 @nota varchar(2000),
 @consecutivocruce int,
 @fecha date,
 @numero varchar(50)
 as

 --// AREA TABLAS TEMPORALES

create table #causacionContableTemppr (
tipo varchar(50),
comprobante varchar(50),
Nocomprobante int,
fechaComprobante date,
registro int,
Identificacion varchar(50),
CuentaContable varchar(50),
fecha date,
mayorCcostoSigo varchar(50),
ccostoSig varchar(50),
nota varchar(1000),
Naturaleza varchar(1),
valorTotal money,
clase int,
cuentaPuenta varchar(50),
tercero int
)
create table #causacionContableFinalPR (
tipo varchar(50),
comprobante varchar(50),
Nocomprobante int,
registro int,
Identificacion varchar(50),
CuentaContable varchar(50),
fecha date,
mayorCcostoSigo varchar(50),
ccostoSig varchar(50),
nota varchar(1000),
Naturaleza varchar(1),
valorTotal money
)
create table  #causacionNomina  (
año int,
mes int , 
noPeriodo int,
fecha date,
identificacion varchar(50),
empleado varchar(200),
concepto varchar(50),
nombreConcepto varchar(200),
valorTotal money,
ccosto varchar(50),
empresa int,
entidadEPS varchar(50),
entidadPension varchar(50),
naturaleza int,
desarrollo bit,
tercero int,
mccostoSiigo varchar(50),
accostoSiigo varchar(50)
)
create table #causacionContableTemp (
tipo varchar(50),
comprobante varchar(50),
Nocomprobante int,
fechaComprobante date,
registro int,
Identificacion varchar(50),
CuentaContable varchar(50),
fecha date,
mayorCcostoSigo varchar(50),
ccostoSig varchar(50),
nota varchar(1000),
Naturaleza varchar(1),
valorTotal money,
clase int,
cuentaPuenta varchar(50),
tercero int,
concepto varchar(50),
ccosto varchar(50),
departamento varchar(50)
)
create table #causacionContableFinal (
tipo varchar(50),
comprobante varchar(50),
Nocomprobante int,
registro int,
Identificacion varchar(50),
CuentaContable varchar(50),
fecha date,
mayorCcostoSigo varchar(50),
ccostoSig varchar(50),
nota varchar(1000),
Naturaleza varchar(1),
valorTotal money
)
create table  #causacionNominapr  (
año int,
mes int , 
noPeriodo int,
fecha date,
identificacion varchar(50),
empleado varchar(200),
concepto varchar(50),
nombreConcepto varchar(200),
valorTotal money,
ccosto varchar(50),
empresa int,
entidadEPS varchar(50),
entidadPension varchar(50),
naturaleza int,
desarrollo bit,
tercero int,
baseCesantias bit,
basePrimas bit,
baseInteresesCesantias bit,
baseVacaciones bit,
mccostoSiigo varchar(50),
accostoSiigo varchar(50)

)
create table  #Provision  (
año int,
mes int , 
noPeriodo int,
fecha date,
identificacion varchar(50),
empleado varchar(200),
concepto varchar(50),
nombreConcepto varchar(200),
valorTotal money,
ccosto varchar(50),
empresa int,
entidadEPS varchar(50),
entidadPension varchar(50),
naturaleza int,
desarrollo bit,
tercero int,
baseCesantias bit,
basePrimas bit,
baseInteresesCesantias bit,
baseVacaciones bit,
mccostoSiigo varchar(50),
accostoSiigo varchar(50)
)
create table  #causacionNominaSS  (
año int,
mes int , 
noPeriodo int,
fecha date,
tercero	 int,
identificacion varchar(50),
empleado varchar(200),
concepto varchar(50),
nombreConcepto varchar(200),
valorTotal money,
ccosto varchar(50),
empresa int,
naturaleza int,
desarrollo bit,
entidadEPS varchar(50),
entidadPension varchar(50),
entidadCaja varchar(50),
entidadARP varchar(50),
entidadFondoSolidaridad varchar(50),
entidadICBF varchar(50),
entidadSena varchar(50),
departamento varchar(50)
)

 --//
 --// PPROCESOS

 --// provision de nomina
 -- variables
 declare @porTercero bit =0,  @cuentaCruce varchar(50) ='', @IdentificacionCursor varchar(50) ='',
@MccostoCruce varchar(50)='',@ccostoCruce varchar(50), @vDebito money=0, @vCredito money=0, @Curtipo varchar(50),	@Curcomprobante varchar(10) ,@CurNocomprobante int
	,	@Curregistro int,	@CurIdentificacion varchar(50),	@CurCuentaContable varchar(50),
		@Curfecha date,	@CurmayorCcostoSigo varchar(50),	@CurccostoSig varchar(50),	@Curnota Varchar(1000)	,
		@CurNaturaleza	 varchar(1), @CurvalorTotal money, @Curclase int, @CurcuentaPuente varchar(50), @ultimoDoc int,
		@contador int=0, @maximo int = 0, @valorDebito money, @valorCredito money,@contadorGeneral int =0, @tercerocruce varchar(50)

		declare @fi date, @ff date, @mes int
--- Seguridad Social

if @tipo =  'SS'
begin
declare @conceptoSalud varchar(50) = (select salud from nParametrosGeneral where empresa=@empresa)
declare @conceptoPension varchar(50) = (select pension from nParametrosGeneral where empresa=@empresa)
declare @conceptoCaja varchar(50) = (select cajaCompensacion from nParametrosGeneral where empresa=@empresa)
declare @conceptoARP  varchar(50) = (select ARP from nParametrosGeneral where empresa=@empresa)
declare @conceptoICBF  varchar(50) = (select ICBF from nParametrosGeneral where empresa=@empresa)
declare @conceptoSENA  varchar(50) = (select sena from nParametrosGeneral where empresa=@empresa)
declare @conceptoFondoSolidaridad  varchar(50) = (select fondoSolidaridad from nParametrosGeneral where empresa=@empresa)

--salud
insert #causacionNominaSS
select a.año, a.mes, a.mes, '', a.idTercero,a.codigoTercero,d.descripcion,
c.codigo, c.descripcion, 
case when h.electivaProduccion=0 then  a.IBCsalud * b.pEmpleador/100
else a.IBCsalud*h.porcentajeSS/100 end ValorSalud
,  g.ccosto, a.empresa, 
1,0, b.codigo,'','','','','','', g.departamento
 from nSeguridadSocial a join vEntidadEps b on a.terceroSalud = b.tercero and a.empresa=b.empresa
join nConcepto c on c.codigo=@conceptoSalud and c.empresa=a.empresa
join cTercero d on a.idTercero=d.id and a.empresa=d.empresa
join nContratos g on g.tercero=a.idTercero and g.empresa=c.empresa and g.id = (select max(z.id) from nContratos  z where idTercero= z.tercero and empresa=@empresa) 
join nClaseContrato h on g.claseContrato=h.codigo and g.empresa=h.empresa
where año=@año and mes=@periodo and a.valorSalud>0 and a.empresa=@empresa
-- pension
insert #causacionNominaSS
select a.año, a.mes, a.mes, '', a.idTercero,a.codigoTercero,d.descripcion,
c.codigo , c.descripcion, a.IBCpension*isnull( e.pEmpleador,b.pEmpleador)/100,g.ccosto, a.empresa, 
1,0, '',isnull(e.codigo,b.codigo) entidadPension,'','','','','',g.departamento
  from nSeguridadSocial a 
 left join vEntidadPension b on a.terceroPension = b.tercero and a.empresa=b.empresa
 join nContratos g on g.tercero=a.idTercero and g.empresa=a.empresa  and a.empresa=@empresa and g.id = (select max(z.id) from nContratos  z where idTercero= z.tercero and empresa=@empresa) 
join nConcepto c on c.codigo=@conceptoPension and c.empresa=a.empresa
join cTercero d on a.idTercero=d.id and a.empresa=d.empresa
left join vEntidadPension e on g.entidadPension=e.codigo and g.empresa=e.empresa
where año=@año and mes=@periodo  and a.valorPension>0 and a.empresa=@empresa
-- caja
insert #causacionNominaSS
select a.año, a.mes, a.mes, '',  a.idTercero,a.codigoTercero,d.descripcion,
c.codigo, c.descripcion, a.valorCaja, g.ccosto, a.empresa, 
1,0, '','',b.codigo,'','','','',g.departamento
 from nSeguridadSocial a join vEntidadCaja b on a.terceroCaja = b.tercero and a.empresa=b.empresa
join nConcepto c on c.codigo=@conceptoCaja and c.empresa=a.empresa
join cTercero d on a.idTercero=d.id and a.empresa=d.empresa
join nContratos g on g.tercero=a.idTercero and g.empresa=c.empresa  and g.id = (select max(z.id) from nContratos  z where idTercero= z.tercero and empresa=@empresa)  
where año=@año and mes=@periodo  and a.valorCaja>0 and a.empresa=@empresa
-- ARP
insert #causacionNominaSS
select a.año, a.mes, a.mes, '', a.idTercero,a.codigoTercero,d.descripcion,
c.codigo, c.descripcion, a.valorArp, g.ccosto, a.empresa, 
1,0, '','','',isnull(h.codigo,b.codigo),'','','',g.departamento
 from nSeguridadSocial a 
left join vEntidadArp b on a.terceroARP = b.tercero and a.empresa=b.empresa
join nConcepto c on c.codigo=@conceptoARP and c.empresa=a.empresa
join cTercero d on a.idTercero=d.id and a.empresa=d.empresa
join nContratos g on g.tercero=a.idTercero and g.empresa=c.empresa  and g.id = (select max(z.id) from nContratos  z where idTercero= z.tercero and empresa=@empresa) 
left join vEntidadArp h on g.entidadArp=h.codigo and g.empresa=h.empresa
where año=@año and mes=@periodo  and a.valorArp>0 and a.empresa=@empresa
--Fondo
insert #causacionNominaSS
select a.año, a.mes, a.mes, '', a.idTercero,a.codigoTercero,d.descripcion,
c.codigo, c.descripcion,isnull( a.valorFondo,0) + isnull(a.valorFondoSub,0),g.ccosto, a.empresa, 
1,0, '',b.codigo,'','',b.codigo,'','',g.departamento
 from nSeguridadSocial a join vEntidadPension b on a.terceroPension = b.tercero and a.empresa=b.empresa
join nConcepto c on c.codigo=@conceptoFondoSolidaridad and c.empresa=a.empresa
join cTercero d on a.idTercero=d.id and a.empresa=d.empresa
join nContratos g on g.tercero=a.idTercero and g.empresa=c.empresa  and g.id = (select max(z.id) from nContratos  z where idTercero= z.tercero and empresa=@empresa) 
where año=@año and mes=@periodo  and a.valorFondo>0 and a.valorFondoSub>0
and a.empresa=@empresa
-- ICBF
insert #causacionNominaSS
select a.año, a.mes, a.mes, '', a.idTercero,a.codigoTercero,d.descripcion,
c.codigo, c.descripcion,a.valorIcbf, g.ccosto, a.empresa, 
1,0, '','','','','',b.codigo,'', g.departamento
 from nSeguridadSocial a join vEntidadIcbf b on a.terceroICBF = b.tercero and a.empresa=b.empresa
join nConcepto c on c.codigo=@conceptoICBF and c.empresa=a.empresa
join cTercero d on a.idTercero=d.id and a.empresa=d.empresa
join nContratos g on g.tercero=a.idTercero and g.empresa=c.empresa 
where año=@año and mes=@periodo  and a.valorIcbf>0 and a.valorFondoSub>0
and a.empresa=@empresa
-- Sena
insert #causacionNominaSS
select a.año, a.mes, a.mes, '', a.idTercero,a.codigoTercero,d.descripcion,
c.codigo, c.descripcion,a.valorIcbf, g.ccosto, a.empresa, 
1,0, '','','','','',b.codigo,'',g.departamento
 from nSeguridadSocial a join vEntidadSena b on a.terceroSena = b.tercero and a.empresa=b.empresa
join nConcepto c on c.codigo=@conceptoSENA and c.empresa=a.empresa
join cTercero d on a.idTercero=d.id and a.empresa=d.empresa
join nContratos g on g.tercero=a.idTercero and g.empresa=c.empresa and g.id = (select max(z.id) from nContratos  z where idTercero= z.tercero and empresa=@empresa) 
join nFuncionario i on i.tercero=a.idTercero and i.empresa=a.empresa 
where año=@año and mes=@periodo  and a.valorIcbf>0 and a.valorFondoSub>0
and a.empresa=@empresa

delete #causacionNominaSS
where valorTotal=0


-- departamentos

insert #causacionContableTemp
select  distinct 
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
       case when a.entidad ='EEPS' then  q.codigo else 
	    case when a.entidad ='EP' then  r.codigo else 
		 case when a.entidad ='EARP' then  w.codigo else 
		 case when a.entidad ='ECAJA' then  x.codigo else 
		  case when a.entidad ='EICBF' then  y.codigo else
		  case when a.entidad ='ESENA' then  z.codigo else 
		  case when a.entidad ='EFONDO' then  aa.codigo else ''
		 end
		 end 
		 end
		 end
		 end
		 end
		 end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end ) end  Identificacion,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.cuenta else 
	    case when a.entidad ='EP' then  r.cuenta else 
		 case when a.entidad ='EARP' then  w.cuenta else 
		 case when a.entidad ='ECAJA' then  x.cuenta else 
		  case when a.entidad ='EICBF' then  y.cuenta else
		  case when a.entidad ='ESENA' then  z.cuenta else 
		  case when a.entidad ='EFONDO' then  aa.cuenta else ''
		 end
		 end 
		 end
		 end
		 end
		 end
		 end
	  )
else (case when m.desarrollo=1 then a.cuentaActivo else 
(case when len(rtrim(ltrim(a.cuentaGasto)))=0 then a.cuentaCredito    else a.cuentaGasto  end )
end ) end CuentaContable,
@fecha,
j.codigo idCcostoSigo,
k.codigo idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 2 then 'D' else 'C' end Naturaleza,
round( m.valorTotal,0),
a.clase,
g.cuentaPuente,
m.tercero,
a.concepto,
a.cCosto,
a.departamento
from  cParametroContaNomi  a 
join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa
join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo 
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
left join cClaseParametroContaNomi g on a.clase=g.codigo and g.empresa=a.empresa 
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
join #causacionNominaSS m on  m.empresa=a.empresa
and m.concepto = a.concepto and a.cCosto=m.ccosto and m.valorTotal>0
left join cTercero  n on m.tercero = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
left join nEntidadArp w on w.codigo = m.entidadARP and w.empresa=m.empresa
left join nEntidadCaja x on x.codigo = m.entidadCaja and x.empresa=m.empresa
left join nEntidadIcbf y on y.codigo = m.entidadICBF and y.empresa=m.empresa
left join nEntidadSena z on z.codigo =m.entidadSena and z.empresa=m.empresa
left join nEntidadFondoPension aa on aa.codigo=m.entidadFondoSolidaridad and aa.empresa=m.empresa
join nDepartamento ab on ab.codigo = a.departamento and ab.empresa=a.empresa
where a.tipo='PE' and g.tipo=@tipo and a.empresa=@empresa
and valorTotal>0 and len(ltrim(rtrim( a.departamento)))>0
group by g.tipoDocumento,g.comprobante,
d.codigo,dd.codigo,d.descripcion,dd.descripcion,c.codigo,c.descripcion,b.codigo,b.descripcion,a.concepto,a.cCosto,a.departamento,
a.manejaEntidad,a.entidad, q.codigo, aa.codigo, aa.descripcion, w.codigo, w.descripcion,
x.codigo,x.descripcion, y.codigo,y.descripcion, z.codigo,z.descripcion,
r.codigo,r.descripcion,a.cuentaActivo, a.cuentaCredito, a.cuentaGasto,
a.terceroCredito,a.tercero,m.identificacion,
q.descripcion, n.descripcion,m.tercero,
r.cuenta,q.cuenta, x.cuenta, y.cuenta, z.cuenta, aa.cuenta,
w.cuenta, m.desarrollo, s.nombre, t.nombre, u.nombre,i.nombre, l.nombre,e.nombre,
b.codigo,b.descripcion,c.codigo,c.descripcion,j.codigo,j.descripcion,
k.codigo,k.descripcion,m.naturaleza,m.ccosto,a.cCosto,a.cCostoMayor,a.clase,g.cuentaPuente, m.valorTotal
insert #causacionContableTemp
select  distinct 
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
       case when a.entidad ='EEPS' then  q.codigo else 
	    case when a.entidad ='EP' then  r.codigo else 
		 case when a.entidad ='EARP' then  w.codigo else 
		 case when a.entidad ='ECAJA' then  x.codigo else 
		  case when a.entidad ='EICBF' then  y.codigo else
		  case when a.entidad ='ESENA' then  z.codigo else 
		  case when a.entidad ='EFONDO' then  aa.codigo else ''
		 end
		 end 
		 end
		 end
		 end
		 end
		 end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end ) end  Identificacion,
 a.cuentaGasto CuentaContable,
@fecha,
j.codigo idCcostoSigo,
k.codigo idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 1 then 'D' else 'C' end Naturaleza,
round( m.valorTotal,0),
a.clase,
g.cuentaPuente,
m.tercero,
a.concepto,a.cCosto,a.departamento
from  cParametroContaNomi  a 
join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa
join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo 
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
left join cClaseParametroContaNomi g on a.clase=g.codigo and g.empresa=a.empresa 
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
join #causacionNominaSS m on  m.empresa=a.empresa
and m.concepto = a.concepto and a.cCosto=m.ccosto and m.valorTotal>0
left join cTercero  n on m.tercero = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
left join nEntidadArp w on w.codigo = m.entidadARP and w.empresa=m.empresa
left join nEntidadCaja x on x.codigo = m.entidadCaja and x.empresa=m.empresa
left join nEntidadIcbf y on y.codigo = m.entidadICBF and y.empresa=m.empresa
left join nEntidadSena z on z.codigo =m.entidadSena and z.empresa=m.empresa
left join nEntidadFondoPension aa on aa.codigo=m.entidadFondoSolidaridad and aa.empresa=m.empresa
join nDepartamento ab on ab.codigo = a.departamento and ab.empresa=a.empresa
where a.tipo='PE' and g.tipo=@tipo and a.empresa=@empresa
and valorTotal>0 and len(ltrim(rtrim( a.departamento)))>0
group by g.tipoDocumento,g.comprobante,
d.codigo,dd.codigo,d.descripcion,dd.descripcion,c.codigo,c.descripcion,b.codigo,b.descripcion,
a.manejaEntidad,a.entidad, q.codigo, aa.codigo, aa.descripcion, w.codigo, w.descripcion,
x.codigo,x.descripcion, y.codigo,y.descripcion, z.codigo,z.descripcion,
r.codigo,r.descripcion,a.cuentaActivo, a.cuentaCredito, a.cuentaGasto,
a.terceroCredito,a.tercero,m.identificacion,
q.descripcion, n.descripcion,m.tercero,
r.cuenta,q.cuenta, x.cuenta, y.cuenta, z.cuenta, aa.cuenta,
w.cuenta, m.desarrollo, s.nombre, t.nombre, u.nombre,i.nombre, l.nombre,e.nombre,
b.codigo,b.descripcion,c.codigo,c.descripcion,j.codigo,j.descripcion,
k.codigo,k.descripcion,m.naturaleza,m.ccosto,a.cCosto,a.cCostoMayor,a.clase,g.cuentaPuente,m.valorTotal,a.concepto,a.cCosto,a.departamento


--select * from #causacionNominaSS
insert #causacionContableTemp
select  distinct 
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
       case when a.entidad ='EEPS' then  q.codigo else 
	    case when a.entidad ='EP' then  r.codigo else 
		 case when a.entidad ='EARP' then  w.codigo else 
		 case when a.entidad ='ECAJA' then  x.codigo else 
		  case when a.entidad ='EICBF' then  y.codigo else
		  case when a.entidad ='ESENA' then  z.codigo else 
		  case when a.entidad ='EFONDO' then  aa.codigo else ''
		 end
		 end 
		 end
		 end
		 end
		 end
		 end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end ) end  Identificacion,
case 
when a.manejaEntidad=1 
then (
     case when a.entidad ='EEPS' then  q.cuenta else 
	    case when a.entidad ='EP' then  r.cuenta else 
		 case when a.entidad ='EARP' then  w.cuenta else 
		 case when a.entidad ='ECAJA' then  x.cuenta else 
		  case when a.entidad ='EICBF' then  y.cuenta else
		  case when a.entidad ='ESENA' then  z.cuenta else 
		  case when a.entidad ='EFONDO' then  aa.cuenta else ''
		 end
		 end 
		 end
		 end
		 end
		 end
		 end
	  )
else (case when m.desarrollo=1 then a.cuentaActivo else 
(case when len(rtrim(ltrim(a.cuentaGasto)))=0 then a.cuentaCredito    else a.cuentaGasto  end )
end ) end CuentaContable,
@fecha,
isnull( j.codigo,'') idCcostoSigo,
isnull(k.codigo,'') idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 2 then 'D' else 'C' end Naturaleza,
round( m.valorTotal,0),
a.clase,
g.cuentaPuente,
m.tercero,
a.concepto,a.cCosto,a.departamento
from  cParametroContaNomi  a 
join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa
join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo 
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
left join cClaseParametroContaNomi g on a.clase=g.codigo and g.empresa=a.empresa 
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
join #causacionNominaSS m on  m.empresa=a.empresa
and m.concepto = a.concepto and a.cCosto=m.ccosto and m.valorTotal>0
left join cTercero  n on m.tercero = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
left join nEntidadArp w on w.codigo = m.entidadARP and w.empresa=m.empresa
left join nEntidadCaja x on x.codigo = m.entidadCaja and x.empresa=m.empresa
left join nEntidadIcbf y on y.codigo = m.entidadICBF and y.empresa=m.empresa
left join nEntidadSena z on z.codigo =m.entidadSena and z.empresa=m.empresa
left join nEntidadFondoPension aa on aa.codigo=m.entidadFondoSolidaridad and aa.empresa=m.empresa
left join nDepartamento ab on ab.codigo = a.departamento and ab.empresa=a.empresa
where a.tipo='PE' and g.tipo=@tipo and a.empresa=@empresa
  and isnull(a.cCosto,'')  + a.concepto + convert(varchar(50), m.tercero)  not in 
(select isnull(aa.ccosto,'')  + aa.concepto +convert(varchar(50), aa.tercero) from #causacionContableTemp aa where len(ltrim(rtrim(aa.departamento)))>0) 
group by g.tipoDocumento,g.comprobante,
d.codigo,dd.codigo,d.descripcion,dd.descripcion,c.codigo,c.descripcion,b.codigo,b.descripcion,
a.manejaEntidad,a.entidad, q.codigo, aa.codigo, aa.descripcion, w.codigo, w.descripcion,
x.codigo,x.descripcion, y.codigo,y.descripcion, z.codigo,z.descripcion,
r.codigo,r.descripcion,a.cuentaActivo, a.cuentaCredito, a.cuentaGasto,
a.terceroCredito,a.tercero,m.identificacion,
q.descripcion, n.descripcion,m.tercero,
r.cuenta,q.cuenta, x.cuenta, y.cuenta, z.cuenta, aa.cuenta,
w.cuenta, m.desarrollo, s.nombre, t.nombre, u.nombre,i.nombre, l.nombre,e.nombre,
b.codigo,b.descripcion,c.codigo,c.descripcion,j.codigo,j.descripcion,
k.codigo,k.descripcion,m.naturaleza,m.ccosto,a.cCosto,a.cCostoMayor,a.clase,g.cuentaPuente, m.valorTotal,a.concepto,a.cCosto,a.departamento

insert #causacionContableTemp
select  distinct 
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
       case when a.entidad ='EEPS' then  q.codigo else 
	    case when a.entidad ='EP' then  r.codigo else 
		 case when a.entidad ='EARP' then  w.codigo else 
		 case when a.entidad ='ECAJA' then  x.codigo else 
		  case when a.entidad ='EICBF' then  y.codigo else
		  case when a.entidad ='ESENA' then  z.codigo else 
		  case when a.entidad ='EFONDO' then  aa.codigo else ''
		 end
		 end 
		 end
		 end
		 end
		 end
		 end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end ) end  Identificacion,
 a.cuentaGasto  CuentaContable,
@fecha,
j.codigo idCcostoSigo,
k.codigo idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 1 then 'D' else 'C' end Naturaleza,
round( m.valorTotal,0),
a.clase,
g.cuentaPuente,
m.tercero,
a.concepto,a.cCosto,a.departamento
from  cParametroContaNomi  a 
join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa
join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo 
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
left join cClaseParametroContaNomi g on a.clase=g.codigo and g.empresa=a.empresa 
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
join #causacionNominaSS m on  m.empresa=a.empresa
and m.concepto = a.concepto and a.cCosto=m.ccosto and m.valorTotal>0
left join cTercero  n on m.tercero = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
left join nEntidadArp w on w.codigo = m.entidadARP and w.empresa=m.empresa
left join nEntidadCaja x on x.codigo = m.entidadCaja and x.empresa=m.empresa
left join nEntidadIcbf y on y.codigo = m.entidadICBF and y.empresa=m.empresa
left join nEntidadSena z on z.codigo =m.entidadSena and z.empresa=m.empresa
left join nEntidadFondoPension aa on aa.codigo=m.entidadFondoSolidaridad and aa.empresa=m.empresa
left join nDepartamento ab on ab.codigo = a.departamento and ab.empresa=a.empresa
where a.tipo='PE' and g.tipo=@tipo and a.empresa=@empresa
and valorTotal>0    and isnull(a.cCosto,'')  + a.concepto + convert(varchar(50), m.tercero)  not in 
(select isnull(aa.ccosto,'')  + aa.concepto +convert(varchar(50), aa.tercero) from #causacionContableTemp aa where len(ltrim(rtrim(aa.departamento)))>0) 
group by g.tipoDocumento,g.comprobante,
d.codigo,dd.codigo,d.descripcion,dd.descripcion,c.codigo,c.descripcion,b.codigo,b.descripcion,a.concepto,a.cCosto,
a.manejaEntidad,a.entidad, q.codigo, aa.codigo, aa.descripcion, w.codigo, w.descripcion,a.departamento,
x.codigo,x.descripcion, y.codigo,y.descripcion, z.codigo,z.descripcion,
r.codigo,r.descripcion,a.cuentaActivo, a.cuentaCredito, a.cuentaGasto,
a.terceroCredito,a.tercero,m.identificacion,
q.descripcion, n.descripcion,m.tercero,
r.cuenta,q.cuenta, x.cuenta, y.cuenta, z.cuenta, aa.cuenta,
w.cuenta, m.desarrollo, s.nombre, t.nombre, u.nombre,i.nombre, l.nombre,e.nombre,
b.codigo,b.descripcion,c.codigo,c.descripcion,j.codigo,j.descripcion,
k.codigo,k.descripcion,m.naturaleza,m.ccosto,a.cCosto,a.cCostoMayor,a.clase,g.cuentaPuente,m.valorTotal


--select * from #causacionNominaSS
--select * from #causacionContableTemp

DECLARE CurCont CURSOR 
FOR 
select tipo,	comprobante	,Nocomprobante	,	registro,	Identificacion,	CuentaContable,	fecha,	mayorCcostoSigo,	ccostoSig,	nota	,Naturaleza	, valorTotal total, clase, cuentaPuenta
 from #causacionContableTemp
 WHERE #causacionContableTemp.valorTotal>0

 
OPEN CurCont

FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente

WHILE @@FETCH_STATUS = 0
BEGIN

set @contador = @contador+1
set @contadorGeneral = @contadorGeneral +1

 if exists ( select * from cpuc where codigo = @CurcuentaPuente and tercero=1 and empresa=@empresa )
 begin
	set @tercerocruce = isnull((select rtrim(ltrim(nit))  from gEmpresa where id=@empresa),'')
 end

if @consecutivocruce = @contador
 begin 
 set @valorDebito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinalPR where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinalPR where Naturaleza='C'and Nocomprobante = @noComprobante),0)
 
 insert #causacionContableFinalPR
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinalPR
 where  Nocomprobante = @noComprobante

 

 insert #causacionContableFinalPR
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante+1	,	1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'SALDO DOCUMENTO ANTERIOR'	,
 case when @valorDebito>@valorCredito then 'D' else 'C' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinalPR
 where  Nocomprobante = @noComprobante
	set @noComprobante = @noComprobante +1 
	set @contador=2
 end 
	insert #causacionContableFinalPR 
	select  @Curtipo,	@Curcomprobante,@noComprobante	,	@contador,	
	@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
	@CurccostoSig,	@Curnota	,@CurNaturaleza	, round( @CurvalorTotal,0)


 if @maximo = @contadorGeneral and @contador<> @consecutivocruce
 begin 

 set @valorDebito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='C'and Nocomprobante = @noComprobante),0)

 insert #causacionContableFinalPR
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador+1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinalPR
 where  Nocomprobante = @noComprobante
 end 



FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente
end 
  CLOSE CurCont
    DEALLOCATE CurCont

	--select * from #causacionContableFinalPR


	select isnull(convert(char(1),tipo),'')+
	isnull(convert(char(3),comprobante),'')+
	RIGHT(replicate('0',11) + cast((isnull(Nocomprobante,'')) as varchar),11)+
	RIGHT(replicate('0',5) +cast ((isnull(registro,'')) as varchar),5)+
	RIGHT(replicate('0',13) +cast (((isnull(Identificacion,''))) as varchar),13)+
	'000' +
	rtrim(ltrim(isnull(CuentaContable,''))) + REPLICATE('0', 10 -len(isnull(rtrim(ltrim(CuentaContable)),''))) +
	 REPLICATE('0', 13)+ --producto
	CONVERT(VARCHAR(8), convert(datetime,@fecha), 112)+
	RIGHT(replicate('0',4) + cast((isnull(mayorCcostoSigo,'')) as varchar),4)+
	RIGHT(replicate('0',3) +cast((isnull(ccostoSig,'')) as varchar),3)+
	convert(char(50),isnull(nota,''))+
	isnull(convert(char(1),Naturaleza),'')+
	RIGHT(replicate('0',13) + cast((substring( convert(varchar(5000),ROUND(valorTotal,0)), 1,charindex('.',convert(varchar(5000),ROUND(valorTotal,0)))-1)) as varchar),13)
	+replicate('0',13)
	+replicate('0',171)
 from #causacionContableFinalPR
 where valorTotal>0


	drop table #causacionNominapr
	drop table #causacionContableTemppr
	drop table #causacionContableFinalPR
	

end
--- Pago
if @tipo ='PA' or @tipo ='IN'
begin

if @tipo='PA'
BEGIN

--select * from vSeleccionaPagosNomina
--where noPeriodo=@periodo and año=@año and empresa=@empresa

declare @tipoTransaccion varchar(50) = (select distinct isnull(tipoTransaccion,'') from cParametroContaNomi a join cClaseParametroContaNomi b 
on a.clase=b.codigo  where a.empresa=@empresa and b.tipo=@tipo  )

insert #causacionNomina
select distinct a.año, a.mes, a.noPeriodo,'', c.codigo identificacion, c.descripcion empleado,   '', '',
d.valorPago valorTotal, 
e.mayor , a.empresa,'','', 1, 0,a.tercero, null,null
from  vSeleccionaLiquidacionDefinitivaCont  a join
nConcepto b on a.concepto=b.codigo and a.empresa=b.empresa and noperiodo=@periodo and año=@año
join cTercero c on c.id = a.tercero and c.empresa = b.empresa
join vSeleccionaPagosNomina d on a.noPeriodo=d.noPeriodo and a.tercero=d.tercero
and d.año=a.año and d.identificacion=a.codigoTercero and d.empresa=a.empresa
join cCentrosCosto e on a.ccosto=e.codigo and a.empresa=b.empresa
where a.empresa =@empresa
and a.anulado=0 and valorTotal>0   and a.tipo=@tipoTransaccion
end

if @tipo='IN'
begin
declare @conceptoIncapacidades varchar(50)
select @conceptoIncapacidades = isnull(incapacidades,'') from nParametrosGeneral
where empresa=@empresa

insert #causacionNomina
select distinct a.año, a.mes, b.noPeriodo,'', f.codigo identificacion, f.descripcion empleado,   '', '',
b.valorTotalR-b.valorTotal valorTotal, e.mayor , a.empresa,'','', 1, 0,b.tercero, null,null from nLiquidacionNomina a join nLiquidacionNominaDetalle b 
on a.tipo=b.tipo and a.numero= b.numero and b.empresa=a.empresa join nConcepto c on b.concepto=c.codigo and a.empresa=c.empresa
join cTercero d on d.id = b.tercero and c.empresa = d.empresa
join cCentrosCostoSigo e on e.codigo=b.ccosto and e.empresa=b.empresa
join cTercero f on f.id=b.entidad and f.empresa=b.entidad 
where a.empresa=@empresa and concepto=@conceptoIncapacidades
and a.año=@año and noPeriodo=@periodo and a.anulado=0 and
a.tipo
in (select tipoTransaccion from cParametroContaNomi a join cClaseParametroContaNomi b 
on a.clase=b.codigo  where a.empresa=@empresa and b.tipo=@tipo  ) and valorTotalR- valorTotal >0
--group by d.codigo, d.id, d.razonSocial 
end



insert #causacionContableTemp
select  distinct 
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.codigo else 
	    (case when a.entidad ='EP' then  r.codigo else ''
		end
		)
	  end
	  )
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end    Identificacion,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.cuenta else 
	    (case when a.entidad ='EP' then  r.cuenta else ''
		end
		)
	  end
	  )
	  
else (case when m.desarrollo=1 then a.cuentaActivo else 
(case when len(rtrim(ltrim(a.cuentaGasto)))=0 then a.cuentaCredito    else a.cuentaGasto  end )
end ) end CuentaContable,
m.fecha,
j.codigo idCcostoSigo,
k.codigo idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 1 then 'D' else 'C' end Naturaleza,
round(m.valorTotal,0),
a.clase,
g.cuentaPuente,
m.tercero,
a.concepto,a.cCosto,A.departamento
from  cParametroContaNomi  a join #causacionNomina m on  m.empresa=a.empresa and m.ccosto=a.cCostoMayor
left join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa
left join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
 join cClaseParametroContaNomi g on a.clase=g.codigo and g.empresa=a.empresa
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
left join cTercero  n on m.empleado = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
where m.valorTotal>0 and g.tipo =@tipo and a.tipo='PE'  and a.empresa=@empresa  and a.empresa=@empresa and cuentaGasto<>''
insert #causacionContableTemp
select   distinct
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.codigo else 
	    (case when a.entidad ='EP' then  r.codigo else ''
		end
		)
	  end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end )end) end identificacion,
a.cuentaCredito CuentaContable,
m.fecha,
j.codigo idCcostoSigo,
k.codigo idSubCentroCostoSigo,
@nota,
 'C'  Naturaleza,
round(m.valorTotal,0),
a.clase,
g.cuentaPuente,
m.tercero,
a.concepto,a.cCosto,A.departamento
from  cParametroContaNomi  a 
left join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa
left join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
 join cClaseParametroContaNomi g on a.clase=g.codigo and g.empresa=a.empresa
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
join #causacionNomina m on  m.empresa=a.empresa and m.ccosto=a.cCostoMayor
left join cTercero  n on m.empleado = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
where m.valorTotal>0 and g.tipo=@tipo and a.cuentaCredito<>'' and a.tipo='PE'  and a.empresa=@empresa

--select * from #causacionContableTemp



DECLARE CurCont CURSOR 
FOR 
select distinct tipo,	comprobante	,Nocomprobante	,	registro,	Identificacion,	CuentaContable,	fecha,	mayorCcostoSigo,	ccostoSig,	nota	,Naturaleza	, valorTotal total, clase, cuentaPuenta
 from #causacionContableTemp

 
OPEN CurCont

FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente

WHILE @@FETCH_STATUS = 0
BEGIN

set @contador = @contador+1
set @contadorGeneral = @contadorGeneral +1

if @consecutivocruce = @contador
 begin 
 set @valorDebito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinalPR where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinalPR where Naturaleza='C'and Nocomprobante = @noComprobante),0)
 

 if exists ( select * from cpuc where codigo = @CurcuentaPuente and tercero=1 and empresa=@empresa )
 begin
	set @tercerocruce = isnull((select rtrim(ltrim(nit))  from gEmpresa where id=@empresa),'')
 end

 insert #causacionContableFinalPR
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinalPR 
 where  Nocomprobante = @noComprobante

 insert #causacionContableFinalPR
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante+1	,	1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'SALDO DOCUMENTO ANTERIOR'	,
 case when @valorDebito>@valorCredito then 'D' else 'C' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinalPR
 where  Nocomprobante = @noComprobante
	set @noComprobante = @noComprobante +1 
	set @contador=2
 end 
	insert #causacionContableFinalPR 
	select  @Curtipo,	@Curcomprobante,@noComprobante	,	@contador,	
	@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
	@CurccostoSig,	@Curnota	,@CurNaturaleza	, round( @CurvalorTotal,0)


 if @maximo = @contadorGeneral and @contador<> @consecutivocruce
 begin 

 set @valorDebito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='C'and Nocomprobante = @noComprobante),0)

 insert #causacionContableFinalPR
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador+1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinalPR
 where  Nocomprobante = @noComprobante
 end 



FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente
end 
  CLOSE CurCont
    DEALLOCATE CurCont

	--select  * from #causacionContableFinalPR

	select isnull(convert(char(1),tipo),'')+
	isnull(convert(char(3),comprobante),'')+
	RIGHT(replicate('0',11) + cast((isnull(Nocomprobante,'')) as varchar),11)+
	RIGHT(replicate('0',5) +cast ((isnull(registro,'')) as varchar),5)+
	RIGHT(replicate('0',13) +cast (((isnull(Identificacion,''))) as varchar),13)+
	'000' +
	rtrim(ltrim(CuentaContable)) + REPLICATE('0', 10 -len(isnull(rtrim(ltrim(CuentaContable)),''))) +
	 REPLICATE('0', 13)+ --producto
	CONVERT(VARCHAR(8), convert(datetime,@fecha), 112)+
	RIGHT(replicate('0',4) + cast((isnull(mayorCcostoSigo,'')) as varchar),4)+
	RIGHT(replicate('0',3) +cast((isnull(ccostoSig,'')) as varchar),3)+
	convert(char(50),isnull(nota,''))+
	isnull(convert(char(1),Naturaleza),'')+
	RIGHT(replicate('0',13) + cast((substring( convert(varchar(5000),ROUND(valorTotal,0)), 1,charindex('.',convert(varchar(5000),ROUND(valorTotal,0)))-1)) as varchar),13)
	+replicate('0',13)
	+replicate('0',171)
 from #causacionContableFinalPR
 where valorTotal>0


	drop table #causacionNominapr
	drop table #causacionContableTemppr
	drop table #causacionContableFinalpr
end
--- Contratistas
 if @tipo= 'CC'
begin

select @mes=mes,@fi=fechaInicial,@ff=fechaFinal  from nPeriodoDetalle
where año=@año and noPeriodo=@periodo and empresa=@empresa

insert #causacionNomina
select 0,0,0,'', a.nit, a.razonSocial, 'L'+a.codigo , a.descripcion,
sum(valorTotal), '',a.empresa,'','',b.naturaleza, c.desarrollo,a.tercero,i.mCcostoSigo,i.aCcostoSigo
 from vSeleccionaLiquidacionContratista a join aNovedad b on a.codigo=b.codigo and a.empresa=b.empresa
 left join  aLotes c on c.codigo=a.lote and c.empresa=a.empresa
 join cTercero d on a.nit = d.codigo and a.empresa=d.empresa
 left join aloteCcostoSigo i on i.lote = a.lote and i.empresa=c.empresa
 where a.fechaT between @fi and @ff and a.empresa=@empresa
 and a.anulado=0
 group by a.tercero, a.nit, a.razonSocial, a.codigo , a.descripcion, a.empresa,b.naturaleza,c.desarrollo,
 i.mCcostoSigo,i.aCcostoSigo
insert #causacionContableTemp
select  distinct
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.codigo else 
	    (case when a.entidad ='EP' then  r.codigo else ''
		end
		)
	  end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end ) end  Identificacion,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.cuenta else 
	    (case when a.entidad ='EP' then  r.cuenta else ''
		end
		)
	  end
	  )
else (case when m.desarrollo=1 then a.cuentaActivo else 
(case when len(rtrim(ltrim(a.cuentaGasto)))=0 then a.cuentaCredito    else a.cuentaGasto  end )
end ) end CuentaContable,
m.fecha,
j.codigo idCcostoSigo,
k.codigo idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 1 then 'D' else 'C' end Naturaleza,
round(m.valorTotal,0),
a.clase,
g.cuentaPuente,
m.tercero,
a.concepto,a.cCosto,A.departamento
from  cParametroContaNomi  a join #causacionNomina m  on m.empresa=a.empresa and m.concepto = a.concepto
left join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa
left join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
left join cClaseParametroContaNomi g on a.clase=g.codigo
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
left join cTercero  n on m.empleado = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
where g.tipo =@tipo  and a.tipo='PC'

select @porTercero =portercero, @cuentaCruce =cuentaCruce from cClaseParametroContaNomi where tipo=@tipo
and empresa=@empresa




if  @cuentaCruce<>''
begin
DECLARE CurContI CURSOR FOR 
select distinct #causacionContableTemp.Identificacion
 from #causacionContableTemp --join nFuncionario 
 --on #causacionContableTemp.tercero= nFuncionario.tercero and empresa=@empresa
 where valorTotal>0

  OPEN CurContI

FETCH NEXT FROM CurContI 
INTO  @IdentificacionCursor

WHILE @@FETCH_STATUS = 0
BEGIN

 set @vDebito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableTemp where Naturaleza='D' and Identificacion=@IdentificacionCursor),0)
 set @vCredito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableTemp where Naturaleza='C'and Identificacion = @IdentificacionCursor),0)
 insert #causacionContableTemp
 select distinct tipo,	comprobante	,Nocomprobante,@fecha,	0,Identificacion,@cuentaCruce,	@fecha,	isnull(@MccostoCruce,''), isnull( @ccostoCruce,'')	,	'CRUCE DE NOMINA'	,
 case when @vDebito>@vCredito then 'C' else 'D' end ,
 case when @vDebito-@vCredito>=0  then @vDebito-@vCredito else -(@vDebito-@vCredito) end  total,
 clase, 
 cuentaPuenta,
0,
'','',''
 from #causacionContableTemp
 where #causacionContableTemp.Identificacion = @IdentificacionCursor
 


FETCH NEXT FROM CurContI INTO  @IdentificacionCursor
end
end

  CLOSE CurContI
    DEALLOCATE CurContI



DECLARE CurCont CURSOR FOR 
select tipo,	comprobante	,Nocomprobante	,	registro,	Identificacion,	CuentaContable,	fecha,	mayorCcostoSigo,	ccostoSig,	nota	,Naturaleza	, valorTotal total, clase, cuentaPuenta
 from #causacionContableTemp
 where valorTotal>0
  order by Identificacion,valorTotal


OPEN CurCont

FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente


set @maximo =(
select count(*) from(
select tipo,	comprobante	,Nocomprobante	,	registro,	Identificacion,	CuentaContable,	fecha,	mayorCcostoSigo,	ccostoSig,	nota	,Naturaleza	, valorTotal, clase, cuentaPuenta
 from #causacionContableTemp
 where valorTotal>0
   ) a
   )

WHILE @@FETCH_STATUS = 0
BEGIN

set @contador = @contador+1
set @contadorGeneral = @contadorGeneral +1

if @consecutivocruce = @contador
 begin 
 set @valorDebito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinal where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinal where Naturaleza='C'and Nocomprobante = @noComprobante),0)
 
 insert #causacionContableFinal
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador,	'',	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinal
 where  Nocomprobante = @noComprobante

 insert #causacionContableFinal
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante+1	,	1,	'',	@CurcuentaPuente,	@fecha,	'',	'',	'SALDO DOCUMENTO ANTERIOR'	,
 case when @valorDebito>@valorCredito then 'D' else 'C' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinal
 where  Nocomprobante = @noComprobante
	set @noComprobante = @noComprobante +1 
	set @contador=2
 end 
	insert #causacionContableFinal 
	select  @Curtipo,	@Curcomprobante,@noComprobante	,	@contador,	
	@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
	@CurccostoSig,	@Curnota	,@CurNaturaleza	, round( @CurvalorTotal,0)


 if @maximo = @contadorGeneral and @contador<> @consecutivocruce
 begin 

 if exists ( select * from cpuc where codigo = @CurcuentaPuente and tercero=1 and empresa=@empresa )
 begin
	set @tercerocruce = isnull((select rtrim(ltrim(nit))  from gEmpresa where id=@empresa),'')
 end

 set @valorDebito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='C'and Nocomprobante = @noComprobante),0)

 insert #causacionContableFinal
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador+1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinal
 where  Nocomprobante = @noComprobante
 end 



FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente
end 
  CLOSE CurCont
    DEALLOCATE CurCont

	--select * from #causacionContableFinal

	select isnull(convert(char(1),tipo),'')+
	isnull(convert(char(3),comprobante),'')+
	RIGHT(replicate('0',11) + cast((isnull(Nocomprobante,'')) as varchar),11)+
	RIGHT(replicate('0',5) +cast ((isnull(registro,'')) as varchar),5)+
	RIGHT(replicate('0',13) +cast (((isnull(Identificacion,''))) as varchar),13)+
	'000' +
	rtrim(ltrim(CuentaContable)) + REPLICATE('0', 10 -len(isnull(rtrim(ltrim(CuentaContable)),''))) +
	 REPLICATE('0', 13)+ --producto
	CONVERT(VARCHAR(8), convert(datetime,@fecha), 112)+
	RIGHT(replicate('0',4) + cast((isnull(mayorCcostoSigo,'')) as varchar),4)+
	RIGHT(replicate('0',3) +cast((isnull(ccostoSig,'')) as varchar),3)+
	convert(char(50),isnull(nota,''))+
	isnull(convert(char(1),Naturaleza),'')+
	RIGHT(replicate('0',13) + cast((substring( convert(varchar(5000),ROUND(valorTotal,0)), 1,charindex('.',convert(varchar(5000),ROUND(valorTotal,0)))-1)) as varchar),13)
	+replicate('0',13)
	+replicate('0',171)
 from #causacionContableFinal
 where valorTotal>0


	drop table #causacionNomina
	drop table #causacionContableTemp
	drop table #causacionContableFinal
end

-- Provisiones
 if @tipo= 'PR'
begin

declare @conceptoCesantias varchar(50),
@conceptoVacaciones varchar(50),
@conceptoInteresCesantias varchar(50),
@conceptoPrimas varchar(50)

select @conceptoCesantias=cesantias, 
@conceptoVacaciones=vacaciones, @conceptoInteresCesantias=intereses,
@conceptoPrimas=primas  from nParametrosGeneral
where empresa=@empresa

insert #causacionNominapr
select a.año, a.mes, a.noPeriodo,a.fecha, c.codigo identificacion, c.descripcion empleado,   a.concepto, b.descripcion nombreConcepto,
sum(valorTotal) valorTotal, a.ccosto , a.empresa,a.entidadEps, a.entidadPension, a.signo,0, a.tercero,
a.baseCesantias, a.basePrimas, a.baseIntereses, a.baseVacaciones,null,null
from vSeleccionaLiquidacionDefinitivaCont a join
nConcepto b on a.concepto=b.codigo and a.empresa=b.empresa
join cTercero c on c.id = a.tercero and c.empresa = b.empresa
 join nContratos d on d.tercero=a.tercero and d.empresa=a.empresa and   d.id in  (select max(id) from nContratos where a.tercero=tercero and empresa=@empresa)
join nClaseContrato h on d.claseContrato=h.codigo and d.empresa=h.empresa
where a.año=@año and a.noPeriodo=@periodo and a.empresa=@empresa  and
a.tipo
in (select tipoTransaccion from cParametroContaNomi a join cClaseParametroContaNomi b 
on a.clase=b.codigo  where a.empresa=@empresa and b.tipo=@tipo  )
and a.anulado=0 and h.electivaProduccion=0
group by a.año, a.mes, a.noPeriodo,a.fecha, a.tercero, c.codigo, c.descripcion ,   a.concepto, b.descripcion,a.ccosto,a.empresa, a.entidad,a.signo,a.entidadEps, a.entidadPension,
a.baseCesantias, a.basePrimas, a.baseIntereses, a.baseVacaciones

declare @finicial date, @ffinal date
select @finicial=fechaInicial, @ffinal=fechaFinal from nPeriodoDetalle where año=@año and noPeriodo=@periodo 
and empresa=@empresa

delete #causacionNominapr
where concepto in (@conceptoVacaciones)


if exists(select distinct d.concepto
from aTransaccion a 
join aTransaccionNovedad b on a.numero=b.numero and a.tipo=b.tipo and a.empresa=b.empresa 
join aTransaccionTercero c on b.numero=c.numero and b.tipo=c.tipo and b.registro=c.registroNovedad and b.empresa=c.empresa 
join aNovedad d on b.novedad = d.codigo and b.empresa=d.empresa
join cTercero e on c.tercero=e.id and e.empresa=c.empresa
join nContratos g on g.tercero=c.tercero and g.empresa=c.empresa and g.id= (select max(id) from nContratos zz where c.tercero=zz.tercero and c.empresa=zz.empresa)
 left join aLotes h on h.codigo=c.lote and h.empresa=c.empresa
 join nConcepto i on i.codigo=d.concepto and i.empresa=d.empresa
where convert(date, a.fecha) between @finicial and @ffinal and a.empresa=@empresa
and d.concepto in (select distinct concepto from #causacionNominapr)
and g.ccosto in (select distinct ccosto from #causacionNominapr)
and a.anulado=0  and c.ejecutado=1
group by e.codigo, e.descripcion,i.codigo, i.descripcion,e.descripcion, g.ccosto, c.tercero,b.novedad, d.descripcion,d.uMedida, a.mes,a.empresa, d.naturaleza,h.desarrollo,
i.baseCesantias, i.basePrimas, i.baseIntereses, i.baseVacaciones, d.concepto
)
 begin

 create table #conceptos (concepto varchar(50))
insert #conceptos
select  d.concepto 
from aTransaccion a 
join aTransaccionNovedad b on a.numero=b.numero and a.tipo=b.tipo and a.empresa=b.empresa 
join aTransaccionTercero c on b.numero=c.numero and b.tipo=c.tipo and b.registro=c.registroNovedad and b.empresa=c.empresa 
join aNovedad d on b.novedad = d.codigo and b.empresa=d.empresa
join cTercero e on c.tercero=e.id and e.empresa=c.empresa
join nContratos g on g.tercero=c.tercero and g.empresa=c.empresa and g.id= (select max(id) from nContratos zz where c.tercero=zz.tercero and c.empresa=zz.empresa)
 left join aLotes h on h.codigo=c.lote and h.empresa=c.empresa
 join nConcepto i on i.codigo=d.concepto and i.empresa=d.empresa
where convert(date, a.fecha) between @finicial and @ffinal and a.empresa=@empresa
and g.ccosto in (select distinct ccosto from #causacionNominapr)
and a.anulado=0  and c.ejecutado=1


delete #causacionNominapr
where concepto in( SELECT concepto from #conceptos )

insert #causacionNominapr
select   a.año, a.mes,0, a.fecha, e.codigo identificacion,e.descripcion empleado,  'L'+d.codigo concepto, d.descripcion nombreConcepto,
sum(c.valorTotal) valorTotal, k.ccosto,a.empresa,'', '', i.signo,h.desarrollo,c.tercero,  
i.baseCesantias, i.basePrimas, i.baseIntereses, i.baseVacaciones, null, null
from aTransaccion a 
join aTransaccionNovedad b on a.numero=b.numero and a.tipo=b.tipo and a.empresa=b.empresa 
join aTransaccionTercero c on b.numero=c.numero and b.tipo=c.tipo and b.registro=c.registroNovedad and b.empresa=c.empresa 
join aNovedad d on b.novedad = d.codigo and b.empresa=d.empresa
join cTercero e on c.tercero=e.id and e.empresa=c.empresa
join nContratos g on g.tercero=c.tercero and g.empresa=c.empresa --and g.activo=1
 left join aLotes h on h.codigo=c.lote and h.empresa=c.empresa
 join nConcepto i on i.codigo=d.concepto and i.empresa=d.empresa
 left join aloteCcostoSigo ii on ii.lote = c.lote and i.empresa=c.empresa
join (select distinct tercero, ccosto, empresa from vSeleccionaLiquidacionDefinitivaCont where año=@año and noPeriodo=@periodo and empresa=@empresa) k  on k.tercero=c.tercero and k.empresa=a.empresa 
and a.anulado=0
where convert(date, a.fecha) between @finicial and @ffinal and a.empresa=@empresa
and g.ccosto in (select distinct ccosto from #causacionNominapr)
and a.anulado=0  and c.ejecutado=1
group by e.codigo, e.descripcion,i.codigo, i.descripcion,e.descripcion, g.ccosto, c.tercero,b.novedad, d.descripcion,d.uMedida, a.mes,a.empresa, d.naturaleza,h.desarrollo,
i.baseCesantias, i.basePrimas, i.baseIntereses, i.baseVacaciones ,ii.mCcostoSigo, ii.aCcostoSigo, a.año,a.fecha,
d.codigo, k.ccosto,i.signo
order by b.novedad
end

--select * from #causacionNominapr

insert #causacionNominapr
select 0, 0, 0,'',  c.codigo identificacion, c.descripcion empleado,   b.codigo, b.descripcion nombreConcepto,
aa.valorTotal valorTotal, d.ccosto , a.empresa,'', '', b.signo,0,a.empleado,
b.baseCesantias, b.basePrimas, b.baseIntereses, b.baseVacaciones,null,null
from nVacaciones a  join
nVacacionesDetalle aa on a.periodoInicial=aa.periodoInicial and a.periodoFinal=aa.periodoFinal and a.empleado=aa.empleado
and a.empresa=aa.empresa and a.registro=aa.registro   join 
nConcepto b on @conceptoVacaciones=b.codigo and a.empresa=b.empresa
  join cTercero c on c.id = a.empleado and c.empresa = a.empresa
  join nContratos d on d.tercero=a.empleado and d.empresa=a.empresa
where añoPago=@año and a.periodo=@periodo and a.empresa=@empresa and a.anulado=0
and a.tipo=1  and aa.concepto=@conceptoVacaciones 
group by  a.empleado, c.codigo, c.descripcion ,  c.codigo, b.codigo,b.descripcion,ccosto,a.empresa,b.signo,
b.baseCesantias, b.basePrimas, b.baseIntereses, b.baseVacaciones,aa.valorTotal

--select * from #causacionNominapr

--Cesantias
--////////////////////////////////////////////
insert #Provision
select año, mes,@periodo, fecha, identificacion, empleado, @conceptoCesantias, b.descripcion, sum(valorTotal) valorTotal, ccosto, a.empresa, entidadEPS,
entidadPension, naturaleza,desarrollo, tercero, a.baseCesantias, a.basePrimas, a.baseInteresesCesantias, a.baseVacaciones,null, null from #causacionNominapr a
join nConcepto b on b.codigo=@conceptoCesantias and b.empresa=a.empresa
where a.baseCesantias=1 and a.empresa=@empresa 
group by año, mes, fecha, identificacion, empleado, concepto,  b.descripcion,ccosto, a.empresa, entidadEPS,
entidadPension, naturaleza,desarrollo, tercero, a.baseCesantias, a.basePrimas, baseInteresesCesantias, a.baseVacaciones
order by tercero,concepto

--vacaciones
--////////////////////////////////////////////
insert #Provision
select año, mes,@periodo, fecha, identificacion, empleado, @conceptoVacaciones, b.descripcion, sum(valorTotal) valorTotal, ccosto, a.empresa, entidadEPS,
entidadPension, naturaleza,desarrollo, tercero, a.baseCesantias, a.basePrimas, a.baseInteresesCesantias, a.baseVacaciones,null,null from #causacionNominapr a
join nConcepto b on b.codigo=@conceptoVacaciones and b.empresa=@empresa
where a.baseVacaciones=1 and a.empresa=@empresa
group by año, mes, fecha, identificacion, empleado, concepto,  b.descripcion,ccosto, a.empresa, entidadEPS,
entidadPension, naturaleza,desarrollo, tercero, a.baseCesantias, a.basePrimas, baseInteresesCesantias, a.baseVacaciones
order by tercero,concepto

--Primas
--////////////////////////////////////////////
insert #Provision
select año, mes,@periodo, fecha, identificacion, empleado, @conceptoPrimas, b.descripcion, sum(valorTotal) valorTotal, ccosto, a.empresa, entidadEPS,
entidadPension, naturaleza,desarrollo, tercero, a.baseCesantias, a.basePrimas, a.baseInteresesCesantias, a.baseVacaciones,null,null from #causacionNominapr a
join nConcepto b on b.codigo=@conceptoPrimas and b.empresa=@empresa
where a.basePrimas=1 and a.empresa=@empresa
group by año, mes, fecha, identificacion, empleado, concepto,  b.descripcion,ccosto, a.empresa, entidadEPS,
entidadPension, naturaleza,desarrollo, tercero, a.baseCesantias, a.basePrimas, baseInteresesCesantias, a.baseVacaciones
order by tercero,concepto

--Intereses de cesantias
--////////////////////////////////////////////
insert #Provision
select año, mes,@periodo, fecha, identificacion, empleado, @conceptoInteresCesantias, b.descripcion, sum(valorTotal)*(case when c.tipoDato=2 then c.valorTipoDato/100 else 1 end) valorTotal, a.ccosto, a.empresa, entidadEPS,
entidadPension, naturaleza,desarrollo, a.tercero, a.baseCesantias, a.basePrimas, a.baseInteresesCesantias teresesCesantias, a.baseVacaciones, null,null from #Provision a
join cParametroContaNomi c on c.concepto=@conceptoCesantias  and c.ccosto=a.ccosto and a.empresa=c.empresa
join cClaseParametroContaNomi d on c.clase=d.codigo and d.empresa=c.empresa and d.tipo=@tipo
join nConcepto b on b.codigo= @conceptoInteresCesantias and b.empresa=@empresa
where a.concepto=@conceptoCesantias and a.empresa=@empresa and c.tipoTransaccion in
 (select tipoTransaccion from cParametroContaNomi a join cClaseParametroContaNomi b 
on a.clase=b.codigo  where a.empresa=@empresa and b.tipo=@tipo  ) 
group by año, mes, fecha, identificacion, empleado, a.concepto,  b.descripcion,a.ccosto, a.empresa, entidadEPS,
entidadPension, naturaleza,desarrollo, a.tercero, a.baseCesantias, a.basePrimas, baseInteresesCesantias, a.baseVacaciones,
C.tipoDato, C.valorTipoDato
order by tercero,a.concepto


--SELECT * FROM #Provision

insert #causacionContableTemppr
select   
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.codigo else 
	    (case when a.entidad ='EP' then  r.codigo else ''
		end
		)
	  end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end ) end   Identificacion,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.cuenta else 
	    (case when a.entidad ='EP' then  r.cuenta else ''
		end
		)
	  end
	  )
else (case when m.desarrollo=1 and a.empresa=1 then a.cuentaActivo else 
(case when len(rtrim(ltrim(a.cuentaGasto)))=0 then a.cuentaCredito    else a.cuentaGasto  end )
end ) end cuenta,
@fecha,
isnull( j.codigo,'') idCcostoSigo,
isnull( k.codigo,'') idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 1 then 'D' else 'C' end Naturaleza,
round(sum(m.valorTotal) * (case when tipoDato=2 then valorTipoDato/100 else 1 end),0) valorTotal ,
a.clase,
g.cuentaPuente,
m.tercero
from  cParametroContaNomi  a 
join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa
join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo and e.empresa=a.empresa
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
 join cClaseParametroContaNomi g on a.clase=g.codigo and g.empresa=a.empresa
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
join #provision m on m.ccosto = a.cCosto and m.empresa=a.empresa
and m.concepto = a.concepto
left join cTercero  n on m.tercero = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa 
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
left join cCentrosCostoSigo z on m.mccostoSiigo = z.codigo and a.empresa=z.empresa 
left join cCentrosCostoSigo zz on m.accostoSiigo =zz.codigo and a.empresa=zz.empresa and m.mccostoSiigo=zz.mayor
where 
m.valorTotal>0 and g.tipo=@tipo  and a.tipo='PE'  and a.empresa=@empresa
group by a.empresa,g.tipoDocumento,m.identificacion,m.tercero,a.tercero,
g.comprobante,a.manejaEntidad,a.entidad,q.codigo, r.codigo,d.codigo, 
a.cuentaContratista,a.cCostoCredito,a.cCostoMayor, a.cCosto, a.cCostoMayorCredito,a.cCostoMayorSigo,
a.cCostoSigo, a.cuentaContratista,a.cuentaCredito,a.terceroCredito,a.cuentaGasto,
q.cuenta, r.cuenta,m.desarrollo,
 t.nombre,u.nombre,e.nombre,l.nombre,j.codigo,j.descripcion,k.codigo,k.descripcion,m.naturaleza,
 a.valorTipoDato,tipoDato,l.codigo, a.clase, g.cuentaPuente,a.cuentaActivo
insert #causacionContableTemppr  
select   
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.codigo else 
	    (case when a.entidad ='EP' then  r.codigo else ''
		end
		)
	  end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end ) end identificacion,
a.cuentaCredito cuenta,
@fecha, 
isnull(m.mccostoSiigo,  j.codigo) idCcostoSigo,
isnull(m.accostoSiigo, k.codigo) idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 1 then 'C' else 'D' end Naturaleza,
round(sum(m.valorTotal) * (case when tipoDato=2 then valorTipoDato/100 else 1 end),0) valorTotal ,
a.clase,
g.cuentaPuente,
m.tercero
from  cParametroContaNomi  a 
join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa
join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo and e.empresa=a.empresa
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
 join cClaseParametroContaNomi g on a.clase=g.codigo and g.empresa=a.empresa
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
join #provision m on m.ccosto = a.cCosto and m.empresa=a.empresa
and m.concepto = a.concepto
left join cTercero  n on m.tercero = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa 
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
left join cCentrosCostoSigo z on m.mccostoSiigo = z.codigo and a.empresa=z.empresa 
left join cCentrosCostoSigo zz on m.accostoSiigo =zz.codigo and a.empresa=zz.empresa and m.mccostoSiigo=zz.mayor
where 
g.tipo =@tipo and m.valorTotal>0 and cuentaCredito<>'' 
group by g.tipoDocumento,m.identificacion,m.tercero,a.tercero,
g.comprobante,a.manejaEntidad,a.entidad,q.codigo, r.codigo,d.codigo, 
a.cuentaContratista,a.cCostoCredito,a.cCostoMayor, a.cCosto, a.cCostoMayorCredito,a.cCostoMayorSigo,
a.cCostoSigo, a.cuentaContratista,a.cuentaCredito,a.terceroCredito,a.cuentaGasto,
q.cuenta, r.cuenta,m.desarrollo,
 t.nombre,u.nombre,e.nombre,l.nombre,j.codigo,j.descripcion,k.codigo,k.descripcion,m.naturaleza,
 a.valorTipoDato,tipoDato,l.codigo, a.clase, g.cuentaPuente,a.cuentaActivo, m.accostoSiigo,m.mccostoSiigo

select @porTercero =portercero, @cuentaCruce =cuentaCruce from cClaseParametroContaNomi where tipo=@tipo
and empresa=@empresa

--select * from #causacionContableTemppr

DECLARE CurCont CURSOR 
FOR 
select tipo,	comprobante	,Nocomprobante	,	registro,	Identificacion,	CuentaContable,	fecha,	mayorCcostoSigo,	ccostoSig,	nota	,Naturaleza	, valorTotal total, clase, cuentaPuenta
 from #causacionContableTemppr
 where valorTotal>0

 
OPEN CurCont

FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente

WHILE @@FETCH_STATUS = 0
BEGIN

set @contador = @contador+1
set @contadorGeneral = @contadorGeneral +1
set @maximo =(select 
count(*) from #causacionContableTemppr where valorTotal>0)

if @consecutivocruce = @contador
 begin 
 set @valorDebito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinalPR where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinalPR where Naturaleza='C'and Nocomprobante = @noComprobante),0)
 
 insert #causacionContableFinalPR
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador,	'',	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinalPR
 where  Nocomprobante = @noComprobante

 insert #causacionContableFinalPR
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante+1	,	1,	'',	@CurcuentaPuente,	@fecha,	'',	'',	'SALDO DOCUMENTO ANTERIOR'	,
 case when @valorDebito>@valorCredito then 'D' else 'C' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinalPR
 where  Nocomprobante = @noComprobante
	set @noComprobante = @noComprobante +1 
	set @contador=2
 end 
	insert #causacionContableFinalPR 
	select  @Curtipo,	@Curcomprobante,@noComprobante	,	@contador,	
	@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
	@CurccostoSig,	@Curnota	,@CurNaturaleza	, round( @CurvalorTotal,0)

	if exists ( select * from cpuc where codigo = @CurcuentaPuente and tercero=1 and empresa=@empresa )
 begin
	set @tercerocruce = isnull((select rtrim(ltrim(nit))  from gEmpresa where id=@empresa),'')
 end



 if @maximo = @contadorGeneral and @contador<> @consecutivocruce
 begin 
 --select 'entro'

 set @valorDebito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinalPR where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinalPR where Naturaleza='C'and Nocomprobante = @noComprobante),0)

 --select @valorDebito, @valorCredito, @noComprobante, @noComprobante

 insert #causacionContableFinalPR
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador+1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinalPR
 where  Nocomprobante = @noComprobante
 end 



FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente
end 
  CLOSE CurCont
    DEALLOCATE CurCont
	

	 --select @maximo, @contadorGeneral, @consecutivocruce

	--select * from #causacionContableFinalPR
	--where valorTotal>0


	create table #plano
	(
	campo varchar(1000)
	)	
	insert #plano
	select isnull(convert(char(1),tipo),'')+
	isnull(convert(char(3),comprobante),'')+
	RIGHT(replicate('0',11) + cast((isnull(Nocomprobante,'')) as varchar),11)+
	RIGHT(replicate('0',5) +cast ((isnull(registro,'')) as varchar),5)+
	RIGHT(replicate('0',13) +cast (((isnull(Identificacion,''))) as varchar),13)+
	'000' +
	rtrim(ltrim(CuentaContable)) + REPLICATE('0', 10 -len(isnull(rtrim(ltrim(CuentaContable)),''))) +
	 REPLICATE('0', 13)+ --producto
	CONVERT(VARCHAR(8), convert(datetime,@fecha), 112)+
	RIGHT(replicate('0',4) + cast((isnull(mayorCcostoSigo,'')) as varchar),4)+
	RIGHT(replicate('0',3) +cast((isnull(ccostoSig,'')) as varchar),3)+
	convert(char(50),isnull(nota,''))+
	isnull(convert(char(1),Naturaleza),'')+
	RIGHT(replicate('0',13) + cast((substring( convert(varchar(5000),ROUND(valorTotal,0)), 1,charindex('.',convert(varchar(5000),ROUND(valorTotal,0)))-1)) as varchar),13)
	+replicate('0',13)
	+replicate('0',171)
 from #causacionContableFinalPR
 where valorTotal>0

	select * from  #plano


	drop table #plano
	drop table #causacionNominapr
	drop table #causacionContableTemppr
	drop table #causacionContableFinalpr
end

-- Causacion
 if @tipo= 'CA'
begin
insert #causacionNomina
select a.año, a.mes, a.noPeriodo,a.fecha, c.codigo identificacion, c.descripcion empleado,   a.concepto, b.descripcion nombreConcepto,
sum(valorTotal) valorTotal, ccosto , a.empresa,a.entidadEps, a.entidadPension, a.signo,0, a.tercero, null,null
from vSeleccionaLiquidacionDefinitivaCont a join
nConcepto b on a.concepto=b.codigo and a.empresa=b.empresa
join cTercero c on c.id = a.tercero and c.empresa = b.empresa
where a.año=@año and a.noPeriodo=@periodo and a.empresa=@empresa and 
a.tipo
in (select distinct tipoTransaccion from cParametroContaNomi a join cClaseParametroContaNomi b 
on a.clase=b.codigo  where a.empresa=@empresa and b.tipo=@tipo  )
and a.anulado=0
group by a.año, a.mes, a.noPeriodo,a.fecha, a.tercero, c.codigo, c.descripcion ,   a.concepto, b.descripcion,ccosto,a.empresa, a.entidad,a.signo,a.entidadEps, a.entidadPension,a.registro
select @fi=fechaInicial, @ff=fechaFinal from nPeriodoDetalle where año=@año and noPeriodo=@periodo 
and empresa=@empresa

insert #causacionNomina
select   a.año, a.mes,0, a.fecha, e.codigo identificacion,e.descripcion empleado,  'L'+d.codigo concepto, d.descripcion nombreConcepto,
sum(c.valorTotal) valorTotal, k.ccosto,a.empresa,'', '', j.signo,h.desarrollo,c.tercero, i.mCcostoSigo, i.aCcostoSigo 
 from aTransaccion a 
join aTransaccionNovedad b on a.numero=b.numero and a.tipo=b.tipo and a.empresa=b.empresa 
join aTransaccionTercero c on b.numero=c.numero and b.tipo=c.tipo and b.registro=c.registroNovedad and b.empresa=c.empresa 
join aNovedad d on b.novedad = d.codigo and b.empresa=d.empresa
join cTercero e on c.tercero=e.id and e.empresa=c.empresa
join nContratos g on g.tercero=c.tercero and g.empresa=c.empresa and g.id= (select max(id) from nContratos zz where c.tercero=zz.tercero and c.empresa=zz.empresa) --and g.activo=1
 left join aLotes h on h.codigo=c.lote and h.empresa=c.empresa
 left join aloteCcostoSigo i on i.lote = c.lote and i.empresa=c.empresa
  join (select distinct tercero, ccosto, empresa from vSeleccionaLiquidacionDefinitivaCont where año=@año and noPeriodo=@periodo and empresa=@empresa) k  on k.tercero=c.tercero and k.empresa=a.empresa 
and a.anulado=0
join nconcepto j on d.concepto=j.codigo and j.empresa=d.empresa
where convert(date, a.fecha) between @fi and @ff and a.empresa=@empresa
and d.concepto in (select distinct concepto from #causacionNomina)
and g.ccosto in (select distinct ccosto from #causacionNomina)
and a.anulado=0  and c.ejecutado=1
group by a.año, a.mes,a.fecha,e.codigo, e.descripcion,d.codigo, d.descripcion,e.descripcion, k.ccosto, c.tercero,b.novedad, d.descripcion,d.uMedida, a.mes,a.empresa, d.naturaleza,h.desarrollo,i.mCcostoSigo, i.aCcostoSigo,j.signo 
order by b.novedad

--select * from #causacionNomina

insert #causacionContableTemp
select   
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.codigo else 
	    (case when a.entidad ='EP' then  r.codigo else ''
		end
		)
	  end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end ) end  Identificacion,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.cuenta else 
	    (case when a.entidad ='EP' then  r.cuenta else ''
		end
		)
	  end
	  )
else (case when m.desarrollo=1  then a.cuentaActivo else 
(case when len(rtrim(ltrim(a.cuentaGasto)))=0 then a.cuentaCredito    else a.cuentaGasto  end )
end ) end CuentaContable,
m.fecha,
isnull(m.mccostoSiigo, isnull(j.codigo,w.codigo)) idCcostoSigo,
isnull(m.accostoSiigo,  isnull(k.codigo,y.codigo)) idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 1 then 'D' else 'C' end Naturaleza,
round(m.valorTotal,0),
a.clase,
g.cuentaPuente,
m.tercero,
a.concepto,
a.cCosto,
a.departamento
from  cParametroContaNomi  a 
left join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa 
left join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo and a.empresa=e.empresa
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
join cClaseParametroContaNomi g on a.clase=g.codigo and a.empresa=g.empresa
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
join #causacionNomina m on m.ccosto = a.cCosto and  m.empresa=a.empresa
and m.concepto = a.concepto  
join cTercero  n on m.tercero = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
left join cCentrosCostoSigo  w on a.cCostoMayorCredito = w.codigo and a.empresa=w.empresa
left join cCentrosCostoSigo y on a.cCostoCredito = y.codigo and a.empresa=y.empresa  and a.cCostoMayorCredito = y.mayor
where g.tipo =@tipo and m.valorTotal>0 and a.tipo='PE'
and a.empresa=@empresa 

select @porTercero =portercero, @cuentaCruce =cuentaCruce from cClaseParametroContaNomi where tipo=@tipo
and empresa=@empresa

--select * from #causacionContableTemp

if @porTercero = 1 and @cuentaCruce<>''
begin
DECLARE CurContI CURSOR FOR 
select distinct #causacionContableTemp.tercero
 from #causacionContableTemp join nFuncionario 
 on #causacionContableTemp.tercero= nFuncionario.tercero and empresa=@empresa
 where valorTotal>0

  OPEN CurContI

FETCH NEXT FROM CurContI 
INTO  @IdentificacionCursor

WHILE @@FETCH_STATUS = 0
BEGIN



 set @vDebito = isnull((select distinct  sum(isnull(valorTotal,0)) from #causacionContableTemp where Naturaleza='D' and tercero=@IdentificacionCursor),0)
 set @vCredito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableTemp where Naturaleza='C'and tercero = @IdentificacionCursor),0)
 insert #causacionContableTemp
 select distinct tipo,	comprobante	,Nocomprobante,@fecha,	0,nFuncionario.codigo,@cuentaCruce,	@fecha,	@MccostoCruce, @ccostoCruce	,	'CRUCE DE NOMINA'	,
 case when @vDebito>@vCredito then 'C' else 'D' end ,
 case when @vDebito-@vCredito>=0  then @vDebito-@vCredito else -(@vDebito-@vCredito) end  total,
 clase, 
 cuentaPuenta,
 #causacionContableTemp.tercero,
 ''
 ,'',''
 from #causacionContableTemp join nFuncionario 
 on #causacionContableTemp.tercero = nFuncionario.tercero and nFuncionario.empresa=@empresa
 where #causacionContableTemp.tercero = @IdentificacionCursor
 



FETCH NEXT FROM CurContI INTO  @IdentificacionCursor
end
 CLOSE CurContI
 DEALLOCATE CurContI
end


DECLARE CurCont CURSOR FOR 
select tipo,	comprobante	,Nocomprobante	,	registro,	Identificacion,	CuentaContable,	fecha,	mayorCcostoSigo,	ccostoSig,	nota	,Naturaleza	, valorTotal total, clase, cuentaPuenta
 from #causacionContableTemp
 where valorTotal>0
  order by Identificacion,valorTotal


OPEN CurCont

FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente


set @maximo =(
select count(*) from(
select tipo,	comprobante	,Nocomprobante	,	registro,	Identificacion,	CuentaContable,	fecha,	mayorCcostoSigo,	ccostoSig,	nota	,Naturaleza	, valorTotal, clase, cuentaPuenta
 from #causacionContableTemp
 where valorTotal>0
   ) a
   )

WHILE @@FETCH_STATUS = 0
BEGIN

set @contador = @contador+1
set @contadorGeneral = @contadorGeneral +1

 if exists ( select * from cpuc where codigo = @CurcuentaPuente and tercero=1 and empresa=@empresa )
 begin
	set @tercerocruce = isnull((select rtrim(ltrim(nit))  from gEmpresa where id=@empresa),'')
 end

if @consecutivocruce = @contador
 begin 
 set @valorDebito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinal where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinal where Naturaleza='C'and Nocomprobante = @noComprobante),0)
 
 insert #causacionContableFinal
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinal
 where  Nocomprobante = @noComprobante

 insert #causacionContableFinal
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante+1	,	1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'SALDO DOCUMENTO ANTERIOR'	,
 case when @valorDebito>@valorCredito then 'D' else 'C' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinal
 where  Nocomprobante = @noComprobante
	set @noComprobante = @noComprobante +1 
	set @contador=2
 end 
	insert #causacionContableFinal 
	select  @Curtipo,	@Curcomprobante,@noComprobante	,	@contador,	
	@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
	@CurccostoSig,	@Curnota	,@CurNaturaleza	, round( @CurvalorTotal,0)


 if @maximo = @contadorGeneral and @contador<> @consecutivocruce
 begin 

 set @valorDebito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='C'and Nocomprobante = @noComprobante),0)

 insert #causacionContableFinal
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador+1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinal
 where  Nocomprobante = @noComprobante
 end 



FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente


end 

CLOSE CurCont
    DEALLOCATE CurCont
  

	--select * from #causacionContableFinal
	--where cuentacontable=''

	select isnull(convert(char(1),tipo),'')+
	isnull(convert(char(3),comprobante),'')+
	RIGHT(replicate('0',11) + cast((isnull(Nocomprobante,'')) as varchar),11)+
	RIGHT(replicate('0',5) +cast ((isnull(registro,'')) as varchar),5)+
	ISNULL(RIGHT(replicate('0',13) +cast (((isnull(Identificacion,''))) as varchar),13),'')+
	'000' +
	ISNULL(rtrim(ltrim(ISNULL(CuentaContable,''))) + REPLICATE('0', 10 -len(isnull(rtrim(ltrim(ISNULL(CuentaContable,''))),''))),'') +
	 REPLICATE('0', 13)+ --producto
	CONVERT(VARCHAR(8), convert(datetime,@fecha), 112)+
	RIGHT(replicate('0',4) + cast((isnull(Convert(varchar(4),ISNULL(mayorCcostoSigo,'')),'')) as varchar),4)+
	RIGHT(replicate('0',3) +cast((isnull(ccostoSig,'')) as varchar),3)+
	convert(char(50),isnull(nota,''))+
	isnull(convert(char(1),Naturaleza),'')+
	RIGHT(replicate('0',13) + cast((substring( convert(varchar(5000),ROUND(valorTotal,0)), 1,charindex('.',convert(varchar(5000),ROUND(valorTotal,0)))-1)) as varchar),13)
	+replicate('0',13)
	+replicate('0',171)
 from #causacionContableFinal
 where valorTotal>0 --and cuentaContable=''


	drop table #causacionNomina
	drop table #causacionContableTemp
	drop table #causacionContableFinal
end


-- Causacion prestaciones sociales

 if @tipo= 'PS'
begin

insert #causacionNomina
select a.año, a.mes, a.noPeriodo,a.fecha, c.codigo identificacion, c.descripcion empleado,   a.concepto, b.descripcion nombreConcepto,
sum(valorTotal) valorTotal, ccosto , a.empresa,a.entidadEps, a.entidadPension, a.signo,0, a.tercero, null,null
from vSeleccionaLiquidacionDefinitivaCont a join
nConcepto b on a.concepto=b.codigo and a.empresa=b.empresa
join cTercero c on c.id = a.tercero and c.empresa = b.empresa
where a.año=@año and a.noPeriodo=@periodo and a.empresa=@empresa and 
 a.numero like '%'+@numero+'%' and
a.tipo
in (select distinct tipoTransaccion from cParametroContaNomi a join cClaseParametroContaNomi b 
on a.clase=b.codigo  where a.empresa=@empresa and b.tipo=@tipo  )
and a.anulado=0
group by a.año, a.mes, a.noPeriodo,a.fecha, a.tercero, c.codigo, c.descripcion ,   a.concepto, b.descripcion,ccosto,a.empresa, a.entidad,a.signo,a.entidadEps, a.entidadPension


select @fi=fechaInicial, @ff=fechaFinal from nPeriodoDetalle where año=@año and noPeriodo=@periodo 
and empresa=@empresa


if exists (
select *from vSeleccionaLiquidacionDefinitivaCont a join
nConcepto b on a.concepto=b.codigo and a.empresa=b.empresa
join cTercero c on c.id = a.tercero and c.empresa = b.empresa
where a.año=@año and a.noPeriodo=@periodo and a.empresa=@empresa and 
a.numero like '%'+@numero+'%' and
a.tipo
in (select distinct tipoTransaccion from cParametroContaNomi a join cClaseParametroContaNomi b 
on a.clase=b.codigo  where a.empresa=@empresa and b.tipo=@tipo  )
and a.anulado=0

)

begin



insert #causacionNomina
select   a.año, a.mes,0, a.fecha, e.codigo identificacion,e.descripcion empleado,  'L'+d.codigo concepto, d.descripcion nombreConcepto,
sum(c.valorTotal) valorTotal, k.ccosto,a.empresa,'', '', d.naturaleza,h.desarrollo,c.tercero, i.mCcostoSigo, i.aCcostoSigo 
 from aTransaccion a 
join aTransaccionNovedad b on a.numero=b.numero and a.tipo=b.tipo and a.empresa=b.empresa 
join aTransaccionTercero c on b.numero=c.numero and b.tipo=c.tipo and b.registro=c.registroNovedad and b.empresa=c.empresa 
join aNovedad d on b.novedad = d.codigo and b.empresa=d.empresa
join cTercero e on c.tercero=e.id and e.empresa=c.empresa
join nContratos g on g.tercero=c.tercero and g.empresa=c.empresa and g.id= (select max(id) from nContratos zz where c.tercero=zz.tercero and c.empresa=zz.empresa) --and g.activo=1
 left join aLotes h on h.codigo=c.lote and h.empresa=c.empresa
 left join aloteCcostoSigo i on i.lote = c.lote and i.empresa=c.empresa
  join  (select distinct tercero, ccosto, empresa from vSeleccionaLiquidacionDefinitivaCont where año=@año and noPeriodo=@periodo and empresa=@empresa
 and anulado=0 and numero like '%'+@numero+'%'
  and tipo in (select distinct tipoTransaccion from cParametroContaNomi a join cClaseParametroContaNomi b 
on a.clase=b.codigo  where a.empresa=@empresa and b.tipo=@tipo  )) k  on k.tercero=c.tercero and k.empresa=a.empresa 
and a.anulado=0
join nConcepto j on d.concepto=j.codigo and d.empresa=j.empresa
where convert(date, a.fecha) between @fi and @ff and a.empresa=@empresa
and d.concepto in (select distinct concepto from #causacionNomina)
and g.ccosto in (select distinct ccosto from #causacionNomina)
and a.anulado=0  and c.ejecutado=0
group by a.año, a.mes,a.fecha,e.codigo, e.descripcion,d.codigo, d.descripcion,e.descripcion, k.ccosto, c.tercero,b.novedad, d.descripcion,d.uMedida, a.mes,a.empresa, d.naturaleza,h.desarrollo,i.mCcostoSigo, i.aCcostoSigo ,j.signo
order by b.novedad

end


insert #causacionContableTemp
select   
g.tipoDocumento tipo,
g.comprobante,
0,
@fecha,
0,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.codigo else 
	    (case when a.entidad ='EP' then  r.codigo else ''
		end
		)
	  end
	  )
else (case when len(ltrim( rtrim(a.cuentaCredito)))>0  and len(ltrim( rtrim(a.terceroCredito)))>0 then a.terceroCredito
 else (
 case when len(ltrim( rtrim(a.cuentaGasto)))>0  and len(ltrim( rtrim(a.tercero)))>0 then a.tercero else m.identificacion end)
		end ) end  Identificacion,
case 
when a.manejaEntidad=1 
then (
      case when a.entidad ='EEPS' then  q.cuenta else 
	    (case when a.entidad ='EP' then  r.cuenta else ''
		end
		)
	  end
	  )
else (case when m.desarrollo=1  then a.cuentaActivo else 
(case when len(rtrim(ltrim(a.cuentaGasto)))=0 then a.cuentaCredito    else a.cuentaGasto  end )
end ) end CuentaContable,
m.fecha,
isnull(m.mccostoSiigo, isnull(j.codigo,w.codigo)) idCcostoSigo,
isnull(m.accostoSiigo,  isnull(k.codigo,y.codigo)) idSubCentroCostoSigo,
@nota,
case when m.naturaleza= 1 then 'D' else 'C' end Naturaleza,
round(m.valorTotal,0),
a.clase,
g.cuentaPuente,
m.tercero,
a.concepto,
a.cCosto,
A.departamento
from  cParametroContaNomi  a 
join cCentrosCosto b on a.cCosto=b.codigo and a.empresa=b.empresa 
join cCentrosCosto c on a.cCostoMayor = c.codigo and a.empresa=c.empresa
left join nConcepto  d on  d.codigo=a.concepto and d.empresa =a.empresa
left join aNovedad dd on 'L'+dd.codigo=a.concepto and dd.empresa=a.empresa
left join cPuc e on a.cuentaGasto =e.codigo and a.empresa=e.empresa
left join aNovedad f on f.codigo=a.concepto and f.empresa=a.empresa
 join cClaseParametroContaNomi g on a.clase=g.codigo and a.empresa=g.empresa
left join cpuc i on i.codigo =  a.cuentaGasto and i.empresa=a.empresa
left join cCentrosCostoSigo  j on a.cCostoMayorSigo = j.codigo and a.empresa=j.empresa
left join cCentrosCostoSigo k on a.cCostoSigo = k.codigo and a.empresa=k.empresa  and a.cCostoMayorSigo = k.mayor
left join cPuc l on l.codigo=a.cuentaCredito and l.empresa=a.empresa
join #causacionNomina m on m.ccosto = a.cCosto and m.empresa=a.empresa
and m.concepto = a.concepto and a.empresa=@empresa
left join cTercero  n on m.empleado = n.id	and n.empresa=a.empresa
left join nEntidadEps q on q.codigo = m.entidadEPS and q.empresa=a.empresa
left join nEntidadFondoPension r on r.codigo= m.entidadPension and r.empresa=a.empresa
left join cPuc s on q.cuenta = s.codigo and q.empresa=s.empresa
left join cPuc t on r.cuenta = t.codigo and t.empresa =r.empresa
left join cpuc u on  a.cuentaActivo = u.codigo and a.empresa= u.empresa
left join nConcepto v on dd.concepto=v.codigo and dd.empresa=v.empresa
left join cCentrosCostoSigo  w on a.cCostoMayorCredito = w.codigo and a.empresa=w.empresa
left join cCentrosCostoSigo y on a.cCostoCredito = y.codigo and a.empresa=y.empresa  and a.cCostoMayorCredito = y.mayor
where g.tipo =@tipo and m.valorTotal>0 and a.tipo='PE'
and a.empresa=@empresa


--select * from #causacionContableTemp

select @porTercero =portercero, @cuentaCruce =cuentaCruce from cClaseParametroContaNomi where tipo=@tipo
and empresa=@empresa


if @porTercero = 1 and @cuentaCruce<>''
begin
DECLARE CurContI CURSOR FOR 
select distinct #causacionContableTemp.tercero
 from #causacionContableTemp join nFuncionario 
 on #causacionContableTemp.tercero= nFuncionario.tercero and empresa=@empresa
 where valorTotal>0

  OPEN CurContI

FETCH NEXT FROM CurContI 
INTO  @IdentificacionCursor

WHILE @@FETCH_STATUS = 0
BEGIN



 set @vDebito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableTemp where Naturaleza='D' and tercero=@IdentificacionCursor),0)
 set @vCredito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableTemp where Naturaleza='C'and tercero = @IdentificacionCursor),0)
 insert #causacionContableTemp
 select distinct tipo,	comprobante	,Nocomprobante,@fecha,	0,nFuncionario.codigo,@cuentaCruce,	@fecha,	@MccostoCruce, @ccostoCruce	,	'CRUCE DE NOMINA'	,
 case when @vDebito>@vCredito then 'C' else 'D' end ,
 case when @vDebito-@vCredito>=0  then @vDebito-@vCredito else -(@vDebito-@vCredito) end  total,
 clase, 
 cuentaPuenta,
 #causacionContableTemp.tercero,
 '',
 '',
 ''
 from #causacionContableTemp join nFuncionario 
 on #causacionContableTemp.tercero = nFuncionario.tercero and nFuncionario.empresa=@empresa
 where #causacionContableTemp.tercero = @IdentificacionCursor
 



FETCH NEXT FROM CurContI INTO  @IdentificacionCursor

end


CLOSE CurContI
    DEALLOCATE CurContI
end
  

DECLARE CurCont CURSOR FOR 
select tipo,	comprobante	,Nocomprobante	,	registro,	Identificacion,	CuentaContable,	fecha,	mayorCcostoSigo,	ccostoSig,	nota	,Naturaleza	, valorTotal total, clase, cuentaPuenta
 from #causacionContableTemp
 where valorTotal>0
  order by Identificacion,valorTotal


OPEN CurCont

FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente


set @maximo =(
select count(*) from(
select tipo,	comprobante	,Nocomprobante	,	registro,	Identificacion,	CuentaContable,	fecha,	mayorCcostoSigo,	ccostoSig,	nota	,Naturaleza	, valorTotal, clase, cuentaPuenta
 from #causacionContableTemp
 where valorTotal>0
   ) a
   )

WHILE @@FETCH_STATUS = 0
BEGIN

set @contador = @contador+1
set @contadorGeneral = @contadorGeneral +1

 if exists ( select * from cpuc where codigo = @CurcuentaPuente and tercero=1 and empresa=@empresa )
 begin
	set @tercerocruce = isnull((select rtrim(ltrim(nit))  from gEmpresa where id=@empresa),'')
 end

if @consecutivocruce = @contador
 begin 
 set @valorDebito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinal where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(valorTotal,0)) from #causacionContableFinal where Naturaleza='C'and Nocomprobante = @noComprobante),0)
 
 insert #causacionContableFinal
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinal
 where  Nocomprobante = @noComprobante

 insert #causacionContableFinal
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante+1	,	1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'SALDO DOCUMENTO ANTERIOR'	,
 case when @valorDebito>@valorCredito then 'D' else 'C' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinal
 where  Nocomprobante = @noComprobante
	set @noComprobante = @noComprobante +1 
	set @contador=2
 end 
	insert #causacionContableFinal 
	select  @Curtipo,	@Curcomprobante,@noComprobante	,	@contador,	
	@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
	@CurccostoSig,	@Curnota	,@CurNaturaleza	, round( @CurvalorTotal,0)


 if @maximo = @contadorGeneral and @contador<> @consecutivocruce
 begin 

 set @valorDebito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='D' and Nocomprobante = @noComprobante),0)
 set @valorCredito = isnull((select distinct sum(isnull(round(valorTotal,0),0)) from #causacionContableFinal where Naturaleza='C'and Nocomprobante = @noComprobante),0)

 insert #causacionContableFinal
 select distinct @Curtipo,	@Curcomprobante	,@noComprobante	,	@contador+1,	@tercerocruce,	@CurcuentaPuente,	@fecha,	'',	'',	'CRUCE DE DOCUMENTO'	,
 case when @valorDebito>@valorCredito then 'C' else 'D' end ,
 case when @valorDebito-@valorCredito>=0  then @valorDebito-@valorCredito else -(@valorDebito-@valorCredito) end  total
 from #causacionContableFinal
 where  Nocomprobante = @noComprobante
 end 



FETCH NEXT FROM CurCont 
INTO  @Curtipo,	@Curcomprobante,@CurNocomprobante	,	@Curregistro,	
@CurIdentificacion,	@CurCuentaContable,	@Curfecha,	@CurmayorCcostoSigo,
@CurccostoSig,	@Curnota	,@CurNaturaleza	, @CurvalorTotal, @Curclase, @curCuentaPuente


end 
CLOSE CurCont
    DEALLOCATE CurCont

  

	--select * from #causacionContableFinal
	--where cuentacontable=''

	select isnull(convert(char(1),tipo),'')+
	isnull(convert(char(3),comprobante),'')+
	RIGHT(replicate('0',11) + cast((isnull(Nocomprobante,'')) as varchar),11)+
	RIGHT(replicate('0',5) +cast ((isnull(registro,'')) as varchar),5)+
	ISNULL(RIGHT(replicate('0',13) +cast (((isnull(Identificacion,''))) as varchar),13),'')+
	'000' +
	ISNULL(rtrim(ltrim(ISNULL(CuentaContable,''))) + REPLICATE('0', 10 -len(isnull(rtrim(ltrim(ISNULL(CuentaContable,''))),''))),'') +
	 REPLICATE('0', 13)+ --producto
	CONVERT(VARCHAR(8), convert(datetime,@fecha), 112)+
	RIGHT(replicate('0',4) + cast((isnull(Convert(varchar(4),ISNULL(mayorCcostoSigo,'')),'')) as varchar),4)+
	RIGHT(replicate('0',3) +cast((isnull(ccostoSig,'')) as varchar),3)+
	convert(char(50),isnull(nota,''))+
	isnull(convert(char(1),Naturaleza),'')+
	RIGHT(replicate('0',13) + cast((substring( convert(varchar(5000),ROUND(valorTotal,0)), 1,charindex('.',convert(varchar(5000),ROUND(valorTotal,0)))-1)) as varchar),13)
	+replicate('0',13)
	+replicate('0',171)
 from #causacionContableFinal
 where valorTotal>0 --and cuentaContable=''


	drop table #causacionNomina
	drop table #causacionContableTemp
	drop table #causacionContableFinal
end

--go
--exec [dbo].[spGuardaContabilizacion]
-- 2016,
-- 1,
-- 'CA',
--9,
-- 1280,
-- 'Prueba del sistema',
-- 260,
--'05/05/2016',
-- ''

