/* ============================================================================
   Migración Infos -> AppPalma: usuarios a la nueva estructura de seguridad
   ----------------------------------------------------------------------------
   QUÉ HACE
   --------
     Crea en AppPalma.dbo.Usuarios los 12 usuarios de Infos.dbo.sUsuarios y les
     asigna rol en AppPalma.dbo.UsuariosRoles según su perfil activo en
     Infos.dbo.sUsuarioPerfiles (empresa 1).
       - Usuario      = mismo login de Infos (así coincide con usuarioRegistro /
                        usuarioAnulado / usuario de las transacciones históricas).
       - Contrasena   = contraseña TEMPORAL aleatoria con hash Argon2id. Las
                        claves de Infos (varbinary cifrado por SQL Server) no son
                        compatibles con password_verify(): NO se migran.
       - ContrasenaSegura = 0 si @ForzarCambio = 1: el login exigirá cambiarla.
       - Usuarios inactivos en Infos quedan con FechaFinalizacion (bloqueados).
       - Correos con dominio mal escrito en Infos ("inverisonesarar") se
         corrigen a "inversionesarar".
     Es idempotente: si el login ya existe en Usuarios no lo toca.

   MAPEO DE PERFILES (Infos) -> ROLES (AppPalma)  *** REVISAR ANTES DE APLICAR ***
   -------------------------------------------------------------------------------
     sa Super Administrador -> @RolSa (3 Administrador)
     ad Administrativo      -> @RolAd (NULL = sin rol, pendiente de definir)
     op Operativos          -> @RolOp (NULL = sin rol, pendiente de definir)
     Un usuario sin rol puede iniciar sesión pero no ve módulos y no aparece
     en el listado de usuarios con rol. El script los lista al final.

   MODO PRUEBA
   -----------
     @Aplicar = 0 (por defecto): ejecuta dentro de una transacción, muestra el
     resultado y hace ROLLBACK. @Aplicar = 1: hace COMMIT.

   REQUISITOS
   ----------
     - Ejecutar conectado a AppPalma con un usuario que lea Infos.
     - Las contraseñas temporales en texto plano se entregan aparte (no están
       en este archivo ni en el repositorio).
     - Archivo UTF-8: ejecutar desde SSMS o con
       "sqlcmd -f 65001 -i 05_migrar_usuarios.sql".
   ============================================================================ */

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @Aplicar      BIT = 0;
DECLARE @ForzarCambio BIT = 1;
DECLARE @RolSa INT = 3;
DECLARE @RolAd INT = NULL;
DECLARE @RolOp INT = NULL;
DECLARE @Ahora DATETIME = GETDATE();

DECLARE @map TABLE (usuario VARCHAR(50) PRIMARY KEY, nombre VARCHAR(100), apellido VARCHAR(100), empresa VARCHAR(100), email VARCHAR(100), hash VARCHAR(100));
INSERT @map (usuario, nombre, apellido, empresa, email, hash) VALUES
        (N'admin', N'Administrador', N'del Sistema', N'Inversiones Arar', N'directortecnologia@inversionesarar.com', N'$argon2id$v=19$m=65536,t=4,p=1$elBieU5RRzFicWZoODZIeQ$mCeEgnbbl8tyX2hf9/FIhJLmWXIbXp2HPgW8tol9OcY'),
        (N'administrativo', N'Administrador', N'de Fincas', N'HECARSE', N'administrativo@hecarse.com', N'$argon2id$v=19$m=65536,t=4,p=1$a2lJUVI1eHMwTGR2VnFNTw$g9fgFfMv1E80psojtYb8nXyvdrsuXxiGm0qORfKXbNM'),
        (N'agronomo', N'Agrónomo', N'Fincas', N'HECARSE', N'agronomo@hecarse.com', N'$argon2id$v=19$m=65536,t=4,p=1$WDU5RVVoNXBWVndDY3NwWQ$wUNjXLFnQfjhMMKVnMTSLDO8lTTia6bStFmMlFimMIE'),
        (N'anoche', N'Liliana', N'Barragan', N'Inversiones Arar', N'mesaayuda01@inversionesarar.com', N'$argon2id$v=19$m=65536,t=4,p=1$RW1qeDVHclRXWXNoUDYwbw$u3Cu0x+DImi/iMhAYRrol+SURDEGmaYViTwtEc9jCwE'),
        (N'bodega', N'Bodega', N'', N'HECARSE', N'administrativo@hecarse.com', N'$argon2id$v=19$m=65536,t=4,p=1$VlpVVWZ0VlZxdFI3djNCcg$9eGa3SzB62hz6HVn8XZJddnsfkNVVIAwiQbCcmMdFhQ'),
        (N'controlinterno', N'Control Interno', N'Arar', N'Inversiones Arar', N'control01@inversionesarar.com', N'$argon2id$v=19$m=65536,t=4,p=1$djVSMHRwYnRBNXNCVE5WYQ$j3MGV9dzN2lxjXPTjomqDfOWpEwTZPLc7Y9AWj3BY3M'),
        (N'Director', N'Director', N'Administrativo', N'HECARSE', N'directorganaderia@hecarse.com', N'$argon2id$v=19$m=65536,t=4,p=1$M3NDNWZrcnhOSnRGYmhFMQ$Cy/i5myhacr3VZENfyDfE9qug+ynuQ3iLt/f3jMRds8'),
        (N'Gerente', N'Gerente', N'', N'HECARSE', N'administrativo01@hecarse.com', N'$argon2id$v=19$m=65536,t=4,p=1$TDVWbkR6V3lxVDE2eVNCdg$iMSOxiF117Lv29ubZQTEN6ci72P0Xvbe2KkL0hf+o0s'),
        (N'jarenas', N'Javier', N'Arenas', N'HECARSE', N'directorganaderia@hecarse.com', N'$argon2id$v=19$m=65536,t=4,p=1$cTQyRlR1b3BhV1ViWjVFSg$XzSfJnAeNtV5PlDfQEAq6zJHtVi50g6jW/Mc5uIc2UI'),
        (N'operativo', N'Eslendi', N'Centeno', N'HECARSE', N'Auxiliarhato@hecarse.com', N'$argon2id$v=19$m=65536,t=4,p=1$VnlYRUdXak1hUHdrZjQvVA$x5o0iZoNuShxlkQwoxioY2lsdwiF6d5r6CPNg49+PnI'),
        (N'operativo01', N'Leonela', N'Morales', N'HECARSE', N'adm_hato@hecarse.com', N'$argon2id$v=19$m=65536,t=4,p=1$M0hoVTdTVDA5YU52RXk2MQ$WHn43rEx7VmyOh0rHN1Gu/HhHiRV9jIP2VIfzoviEQo'),
        (N'operativo02', N'Brenda', N'Estor', N'HECARSE', N'Auxiliarcruceiro@hecarse.com', N'$argon2id$v=19$m=65536,t=4,p=1$Q3oxZWFKNmp5Nm14ZXc0eg$ARknm6y2PcEDbgnqXztWc3SgZX97EXNCI1EVbUXmoec');

IF EXISTS (SELECT 1 FROM Infos.dbo.sUsuarios s WHERE NOT EXISTS (SELECT 1 FROM @map m WHERE m.usuario = s.usuario))
BEGIN
    SELECT s.usuario, s.descripcion FROM Infos.dbo.sUsuarios s
    WHERE NOT EXISTS (SELECT 1 FROM @map m WHERE m.usuario = s.usuario);
    RAISERROR(N'Hay usuarios en Infos que no están en @map (se crearon después de generar el script). No se aplicó nada.', 16, 1);
    RETURN;
END;

DECLARE @nuevos TABLE (Id INT, Usuario VARCHAR(100));

BEGIN TRY

    BEGIN TRAN;

    INSERT INTO dbo.Usuarios (Nombre, Apellido, Usuario, Contrasena, Empresa, Email, IntentosFallidos, ContrasenaSegura, FechaInicio, FechaFinalizacion)
    OUTPUT inserted.Id, inserted.Usuario INTO @nuevos (Id, Usuario)
    SELECT m.nombre, m.apellido, s.usuario, m.hash, m.empresa, m.email, 0,
           CASE WHEN @ForzarCambio = 1 THEN 0 ELSE 1 END,
           @Ahora,
           CASE WHEN s.activo = 1 THEN NULL ELSE @Ahora END
    FROM Infos.dbo.sUsuarios s
    JOIN @map m ON m.usuario = s.usuario
    WHERE NOT EXISTS (SELECT 1 FROM dbo.Usuarios u WHERE u.Usuario = s.usuario);

    INSERT INTO dbo.UsuariosRoles (IdUsuario, IdRol, FechaInicio)
    SELECT n.Id, r.IdRol, @Ahora
    FROM @nuevos n
    JOIN Infos.dbo.sUsuarioPerfiles p ON p.usuario = n.Usuario AND p.empresa = 1 AND p.activo = 1
    CROSS APPLY (SELECT CASE p.perfil WHEN 'sa' THEN @RolSa WHEN 'ad' THEN @RolAd WHEN 'op' THEN @RolOp END AS IdRol) r
    WHERE r.IdRol IS NOT NULL;

    SELECT n.Id, n.Usuario, u.Nombre, u.Apellido, u.Email,
           p.perfil AS PerfilInfos, ro.Nombre AS RolAsignado,
           CASE WHEN u.FechaFinalizacion IS NULL THEN 'Activo' ELSE 'Bloqueado' END AS Estado,
           CASE WHEN (SELECT COUNT(*) FROM dbo.Usuarios x WHERE x.Email = u.Email) > 1 THEN 'Correo compartido' ELSE '' END AS Alerta
    FROM @nuevos n
    JOIN dbo.Usuarios u ON u.Id = n.Id
    LEFT JOIN Infos.dbo.sUsuarioPerfiles p ON p.usuario = n.Usuario AND p.empresa = 1 AND p.activo = 1
    LEFT JOIN dbo.UsuariosRoles ur ON ur.IdUsuario = n.Id AND ur.FechaFinalizacion IS NULL
    LEFT JOIN dbo.Roles ro ON ro.Id = ur.IdRol
    ORDER BY n.Id;

    IF @Aplicar = 1
    BEGIN
        COMMIT;
        PRINT N'APLICADO: cambios confirmados.';
    END
    ELSE
    BEGIN
        ROLLBACK;
        PRINT N'MODO PRUEBA: se revirtió todo. Cambie @Aplicar = 1 para confirmar.';
    END

END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK;
    THROW;
END CATCH;
