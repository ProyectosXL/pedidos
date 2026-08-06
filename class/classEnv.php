<?php


class DotEnv
{
    /**
     * The directory where the .env file can be located.
     *
     * @var string
     */
    protected $path;

    /**
     * Ruta del archivo .env, siempre en la carpeta raíz un nivel por encima
     * del repositorio (fuera del repo a propósito, para que no quede
     * versionado). classEnv.php vive en <repo>/class/, por lo que
     * __DIR__ . '/../../.env' resuelve a <repo>/../.env.
     *
     * @return string Ruta absoluta si el archivo existe; si no, la ruta
     *                 construida (sin resolver), útil como diagnóstico.
     */
    public static function resolveEnvPath()
    {
        $path = __DIR__ . '/../../.env';
        $real = realpath($path);

        return $real !== false ? $real : $path;
    }

    public function __construct(?string $path = null)
    {
        if ($path === null) {
            $path = self::resolveEnvPath();
        }

        if(!file_exists($path)) {
            throw new \InvalidArgumentException(sprintf(
                'No se encontró el archivo de configuración de entorno (.env) en "%s". Debe estar en la carpeta raíz, un nivel por encima del repositorio.',
                $path
            ));
        }
        $this->path = $path;
    }

    private function load() :void
    {
        if (!is_readable($this->path)) {
            throw new \RuntimeException(sprintf(
                'El archivo de configuración de entorno (.env) en "%s" existe pero no es legible. Revisar los permisos del archivo.',
                $this->path
            ));
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {

            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    /**
     * Lee una variable de entorno de forma confiable.
     * getenv() puede devolver false de forma intermitente (no es thread-safe),
     * por eso priorizamos $_ENV/$_SERVER que load() ya pobló en esta request.
     *
     * @return string|null
     */
    private static function env(string $name)
    {
        if (array_key_exists($name, $_ENV) && $_ENV[$name] !== false) {
            return $_ENV[$name];
        }
        if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== false) {
            return $_SERVER[$name];
        }
        $val = getenv($name);
        return $val === false ? null : $val;
    }

    public function listVars(){
        (new DotEnv(self::resolveEnvPath()))->load();

        $vars = array(

            'HOST_CENTRAL' => self::env('HOST_CENTRAL'),
            'HOST_LOCALES' => self::env('HOST_LOCALES'),
            'DATABASE_CENTRAL' => self::env('DATABASE_CENTRAL'),
            'DATABASE_LOCALES' => self::env('DATABASE_LOCALES'),
            'DATABASE_UY' => self::env('DATABASE_UY'),
            'DATABASE_SUC_UY' => self::env('DATABASE_SUC_UY'),
            'USER' => self::env('USER'),
            'PASS' => self::env('PASS'),
            'PASS_LOCALES' => self::env('PASS_LOCALES'),
            'CHARACTER' => self::env('CHARACTER') ?: 'UTF-8',
            'ENV' => self::env('ENV'),
            'HOST_EMAIL' => self::env('HOST_EMAIL'),
            'USER_EMAIL' => self::env('USER_EMAIL'),
            'PASS_EMAIL' => self::env('PASS_EMAIL'),
            

        );

        return $vars;


    }

}