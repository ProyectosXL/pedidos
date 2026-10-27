/*
    SJ_STOCK_DISPONIBLE_VALIDACION_LOTE
    Base: LAKER_SA (XL-TANGO). Usado por locales/controller/cargarPedidoNuevo.php
    (validación de stock al enviar el pedido de una franquicia).

    PROBLEMA (08/09/2026): el SP joineaba directo contra el linked server
    [XL-SALES\SQLEXPRESS].UbicacionesStockMvc.DBO.SJ_SOTCK_ECOMMERCE_SUMADO.
    Los dos INSERT INTO #STOCK_UBIC se recompilaban en casi cada ejecución
    (85 ejecuciones del SP contra 1-2 planes cacheados de esas sentencias) y el
    optimizador alternaba entre:
      - plan bueno: 1 scan remoto + hash join  -> ~1,4 s
      - plan malo:  1 seek remoto POR ARTICULO -> ~2,4 s/artículo
    Medición real en sys.dm_exec_procedure_stats: 80 ejecuciones,
    min 1.356 ms / promedio 128.532 ms / max 553.635 ms (9 min).
    Con el plan malo el PHP moría en set_time_limit(180) y nginx devolvía 504.

    FIX: materializar el remoto en #UBIC_REMOTO con UNA sola pasada (953 filas,
    ~1,4 s) y después joinear local. El acceso remoto pasa a ser siempre 1 scan,
    sin importar qué plan elija el optimizador para el resto.

    Verificado sobre carritos reales (FRDEVO/GENERAL 136 arts, FRSEOA/ACCESORIOS,
    FRPOUT/GENERAL 115 arts): resultados idénticos al SP anterior, ~1,7 s.
*/

ALTER PROCEDURE dbo.SJ_STOCK_DISPONIBLE_VALIDACION_LOTE
(
    @CODIGOS_CSV NVARCHAR(MAX),
    @SUC         INT,
    @COD_CLIENT  VARCHAR(20),
    @TIPO        INT
)
AS
BEGIN

    SET NOCOUNT ON;

    IF @CODIGOS_CSV IS NULL OR LTRIM(RTRIM(@CODIGOS_CSV)) = ''
    BEGIN

        SELECT CAST(NULL AS VARCHAR(30)) AS COD_ARTICU, CAST(0 AS INT) AS CANT_STOCK
        WHERE 1 = 0;

        RETURN;

    END;

    DECLARE @ARTS TABLE (
        COD_ARTICU VARCHAR(30) COLLATE Latin1_General_BIN NOT NULL PRIMARY KEY
    );

    INSERT INTO @ARTS (COD_ARTICU)
    SELECT DISTINCT LEFT(LTRIM(RTRIM(value)), 30)
    FROM STRING_SPLIT(@CODIGOS_CSV, ',')
    WHERE LTRIM(RTRIM(value)) <> '';

    -- Stock de ubicaciones (linked server XL-SALES): se materializa UNA sola vez.
    -- Ver comentario de cabecera: joinear directo contra el linked server derivaba
    -- en planes de seek remoto por fila (~2,4 s/artículo => 504 en nginx).
    IF OBJECT_ID('tempdb..#UBIC_REMOTO') IS NOT NULL DROP TABLE #UBIC_REMOTO;

    SELECT
        CAST(COD_ARTICULO AS VARCHAR(50)) COLLATE Latin1_General_BIN AS COD_ARTICULO,
        CAST(ISNULL(STOCK_UBIC, 0) AS FLOAT) AS STOCK_UBIC
    INTO #UBIC_REMOTO
    FROM [XL-SALES\SQLEXPRESS].UbicacionesStockMvc.DBO.SJ_SOTCK_ECOMMERCE_SUMADO;

    CREATE CLUSTERED INDEX IX_UBIC_REMOTO ON #UBIC_REMOTO (COD_ARTICULO);

    IF OBJECT_ID('tempdb..#MAP') IS NOT NULL DROP TABLE #MAP;

    SELECT
        A.COD_ARTICU AS COD_PEDIDO,
        CASE
            WHEN @TIPO = 2 AND K.COD_ARTICU IS NOT NULL
                THEN K.COD_ARTICU COLLATE Latin1_General_BIN
            ELSE A.COD_ARTICU
        END AS COD_COMP,
        CAST(ISNULL(K.MULTIPLO, 1) AS INT) AS MULTIPLO,
        CASE WHEN @TIPO = 2 AND K.COD_ARTICU IS NOT NULL THEN 1 ELSE 0 END AS ES_KIT
    INTO #MAP
    FROM @ARTS A
    OUTER APPLY (
        SELECT TOP 1
            COD_ARTICU COLLATE Latin1_General_BIN AS COD_ARTICU,
            ISNULL(MULTIPLO, 1) AS MULTIPLO
        FROM RO_V_KITS_PUBLICADOS
        WHERE COD_KIT = A.COD_ARTICU COLLATE Modern_Spanish_CI_AI
    ) K;

    -- Una sola creación de #STOCK_UBIC (SQL Server no permite SELECT INTO en ambas ramas IF/ELSE)

    IF OBJECT_ID('tempdb..#STOCK_UBIC') IS NOT NULL DROP TABLE #STOCK_UBIC;

    CREATE TABLE #STOCK_UBIC (
        COD_PEDIDO VARCHAR(30) COLLATE Latin1_General_BIN NOT NULL,
        STOCK_UBIC FLOAT NOT NULL
    );

    IF @TIPO = 1
    BEGIN

        INSERT INTO #STOCK_UBIC (COD_PEDIDO, STOCK_UBIC)
        SELECT
            A.COD_ARTICU,
            CAST(ISNULL(U.STOCK_UBIC, 0) AS FLOAT)
        FROM @ARTS A
        LEFT JOIN #UBIC_REMOTO U
            ON U.COD_ARTICULO = A.COD_ARTICU;

    END

    ELSE
    BEGIN

        IF OBJECT_ID('tempdb..#UBIC_KEYS') IS NOT NULL DROP TABLE #UBIC_KEYS;

        SELECT DISTINCT X.COD_ARTICULO
        INTO #UBIC_KEYS
        FROM (
            SELECT COD_ARTICU COLLATE Latin1_General_BIN AS COD_ARTICULO FROM @ARTS
            UNION
            SELECT K.COD_ARTICU COLLATE Latin1_General_BIN
            FROM RO_V_KITS_PUBLICADOS K
            INNER JOIN @ARTS A
                ON K.COD_KIT = A.COD_ARTICU COLLATE Modern_Spanish_CI_AI
        ) X;

        INSERT INTO #STOCK_UBIC (COD_PEDIDO, STOCK_UBIC)
        SELECT
            CAST(
                CASE
                    WHEN B.COD_KIT IS NOT NULL
                        THEN B.COD_KIT COLLATE Latin1_General_BIN
                    ELSE U.COD_ARTICULO COLLATE Latin1_General_BIN
                END AS VARCHAR(30)
            ),
            CAST(ISNULL(MAX(
                CASE
                    WHEN B.COD_KIT IS NOT NULL
                        THEN CAST(U.STOCK_UBIC AS FLOAT) / NULLIF(B.MULTIPLO, 0)
                    ELSE CAST(U.STOCK_UBIC AS FLOAT)
                END

            ), 0) AS FLOAT)
        FROM #UBIC_KEYS K
        INNER JOIN #UBIC_REMOTO U
            ON U.COD_ARTICULO = K.COD_ARTICULO
        LEFT JOIN RO_V_KITS_PUBLICADOS B
            ON U.COD_ARTICULO = B.COD_ARTICU COLLATE Modern_Spanish_CI_AI
        WHERE (
            CASE
                WHEN B.COD_KIT IS NOT NULL
                    THEN B.COD_KIT COLLATE Modern_Spanish_CI_AI
                ELSE U.COD_ARTICULO COLLATE Modern_Spanish_CI_AI
            END

        ) IN (SELECT COD_ARTICU COLLATE Modern_Spanish_CI_AI FROM @ARTS)
        GROUP BY
            CAST(
                CASE
                    WHEN B.COD_KIT IS NOT NULL
                        THEN B.COD_KIT COLLATE Latin1_General_BIN
                    ELSE U.COD_ARTICULO COLLATE Latin1_General_BIN
                END AS VARCHAR(30)
            );

    END;

    IF OBJECT_ID('tempdb..#UBIC') IS NOT NULL DROP TABLE #UBIC;

    SELECT
        M.COD_PEDIDO,
        CAST(ISNULL(U.STOCK_UBIC, 0) AS FLOAT) AS STOCK_UBIC
    INTO #UBIC
    FROM #MAP M
    LEFT JOIN #STOCK_UBIC U ON U.COD_PEDIDO = M.COD_PEDIDO;

    IF OBJECT_ID('tempdb..#COMPS') IS NOT NULL DROP TABLE #COMPS;

    SELECT DISTINCT COD_COMP INTO #COMPS FROM #MAP;

    IF OBJECT_ID('tempdb..#PEND') IS NOT NULL DROP TABLE #PEND;

    SELECT COD_COMP, SUM(CANT_PEND) AS CANT_PEND
    INTO #PEND
    FROM (
        SELECT
            C.COD_COMP,
            CAST(SUM(S.CANT_COMP) AS FLOAT) AS CANT_PEND
        FROM #COMPS C
        INNER JOIN STA19 S
            ON S.COD_ARTICU = C.COD_COMP COLLATE Latin1_General_BIN
        WHERE S.COD_DEPOSI = '01'
          AND S.CANT_COMP > 0
        GROUP BY C.COD_COMP
        UNION ALL
        SELECT
            C.COD_COMP,
            CAST(ISNULL(SUM(B.CANTIDAD), 0) AS FLOAT) AS CANT_PEND
        FROM #COMPS C
        INNER JOIN STA20 B
            ON B.COD_ARTICU = C.COD_COMP COLLATE Latin1_General_BIN
        INNER JOIN STA14 A
            ON A.NCOMP_IN_S = B.NCOMP_IN_S
           AND A.TCOMP_IN_S = B.TCOMP_IN_S
        WHERE A.FECHA_MOV >= GETDATE() - 2
          AND A.N_COMP LIKE 'R%'
          AND A.TCOMP_IN_S = 'RP'
          AND B.COD_ARTICU NOT LIKE CASE @TIPO WHEN 1 THEN '[XO][PH]%' ELSE 'XP%' END
        GROUP BY C.COD_COMP
        UNION ALL
        SELECT
            C.COD_COMP,
            CAST(SUM(E.CANT) AS FLOAT) AS CANT_PEND
        FROM #COMPS C
        INNER JOIN SJ_EXCLUIDOS E
            ON E.COD_ARTICU = C.COD_COMP COLLATE Latin1_General_BIN
        GROUP BY C.COD_COMP
        HAVING SUM(E.CANT) > 0
    ) X
    GROUP BY COD_COMP;

    IF OBJECT_ID('tempdb..#FISICO') IS NOT NULL DROP TABLE #FISICO;

    SELECT
        C.COD_COMP,
        CAST(SUM(S.CANT_STOCK) AS FLOAT) AS CANT_STOCK
    INTO #FISICO
    FROM #COMPS C
    INNER JOIN STA19 S
        ON S.COD_ARTICU = C.COD_COMP COLLATE Latin1_General_BIN
    WHERE S.COD_DEPOSI = '01'
      AND S.CANT_STOCK > 0
    GROUP BY C.COD_COMP;

    IF OBJECT_ID('tempdb..#TRANSF') IS NOT NULL DROP TABLE #TRANSF;

    SELECT
        C.COD_COMP,
        CAST(ISNULL(SUM(T.SumaDeCantidad), 0) AS FLOAT) AS CANT_TRANSF
    INTO #TRANSF
    FROM #COMPS C
    LEFT JOIN Tr_X_Art T
        ON T.codigo = C.COD_COMP COLLATE Latin1_General_BIN
       AND T.Depo_Origen_Nombre LIKE '01-Central'
    GROUP BY C.COD_COMP;

    SELECT
        M.COD_PEDIDO AS COD_ARTICU,
        CAST(
            CASE WHEN v.FINAL_STOCK < 0 THEN 0 ELSE v.FINAL_STOCK END
        AS INT) AS CANT_STOCK
    FROM #MAP M
    LEFT JOIN #FISICO F ON F.COD_COMP = M.COD_COMP
    LEFT JOIN #PEND   P ON P.COD_COMP = M.COD_COMP
    LEFT JOIN #TRANSF T ON T.COD_COMP = M.COD_COMP
    LEFT JOIN #UBIC   U ON U.COD_PEDIDO = M.COD_PEDIDO
    CROSS APPLY (
        SELECT
            CASE
                WHEN ISNULL(F.CANT_STOCK, 0) - ISNULL(P.CANT_PEND, 0) < 0 THEN 0
                ELSE ISNULL(F.CANT_STOCK, 0) - ISNULL(P.CANT_PEND, 0)
            END AS S1
    ) s1
    CROSS APPLY (
        SELECT
            CASE
                WHEN @SUC = 200 THEN CASE WHEN s1.S1 - 5 < 0 THEN 0 ELSE s1.S1 - 5 END
                ELSE s1.S1
            END AS S2
    ) s2
    CROSS APPLY (
        SELECT
            CASE
                WHEN s2.S2 - ISNULL(T.CANT_TRANSF, 0) < 0 THEN 0
                ELSE s2.S2 - ISNULL(T.CANT_TRANSF, 0)
            END AS S3
    ) s3
    CROSS APPLY (
        SELECT
            CASE
                WHEN @TIPO = 2 AND M.ES_KIT = 1 THEN FLOOR(s3.S3 / NULLIF(M.MULTIPLO, 0))
                ELSE s3.S3
            END AS S4
    ) s4
    CROSS APPLY (
        SELECT
            CASE
                WHEN s4.S4 - ISNULL(U.STOCK_UBIC, 0) < 0 THEN 0
                ELSE s4.S4 - ISNULL(U.STOCK_UBIC, 0)
            END AS FINAL_STOCK
    ) v;

END
