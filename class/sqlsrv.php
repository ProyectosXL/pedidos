<?php

if (class_exists('Sqlsrv')) {
    return;
}

class Sqlsrv
{
    /**
     * Ejecuta una consulta y detiene la ejecución si falla (equivalente a odbc_exec + die).
     * @param resource $cid
     * @param string $sql
     * @param array $params
     * @return resource
     */
    public static function ejecutar($cid, $sql, array $params = [])
    {
        $stmt = empty($params)
            ? sqlsrv_query($cid, $sql)
            : sqlsrv_query($cid, $sql, $params);

        if ($stmt === false) {
            die('Error en sqlsrv_query: ' . print_r(sqlsrv_errors(), true));
        }

        return $stmt;
    }

    /**
     * @param resource $stmt
     * @return array|false
     */
    public static function fetch($stmt)
    {
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }

    /**
     * Equivalente a odbc_num_rows() > 0 antes de consumir filas.
     * @param resource $stmt
     */
    public static function tieneFilas($stmt)
    {
        return $stmt !== false && sqlsrv_has_rows($stmt);
    }
}
