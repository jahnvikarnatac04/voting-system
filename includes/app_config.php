<?php
/**
 * includes/app_config.php
 * ---------------------------------------------------------------
 * Shared configuration loader for credentials and service settings.
 *
 * Precedence:
 *   1. includes/config.php   (untracked, gitignored — the real values)
 *   2. environment variable of the same name
 *   3. the supplied default
 *
 * WHY THIS EXISTS
 *   Credentials must never live in tracked source. `includes/config.php`
 *   is listed in .gitignore, so real values stay on the machine and out
 *   of the repository. `includes/config.example.php` is the tracked
 *   placeholder that documents every key.
 *
 *   This loader is intentionally dependency-free (no Composer, no
 *   session, no headers) so it is safe to require from any entry point,
 *   including CLI scripts.
 *
 * See: docs/process/policies/security-and-secrets.md
 * ---------------------------------------------------------------
 */

if (!function_exists('app_config')) {
    /**
     * Read a configuration value.
     *
     * @param string $key     Config key (also the environment variable name).
     * @param mixed  $default Returned when the key is absent or empty.
     * @return mixed
     */
    function app_config($key, $default = null)
    {
        static $fileConfig = null;

        if ($fileConfig === null) {
            $fileConfig = [];
            $path = __DIR__ . '/config.php';
            if (is_file($path)) {
                $loaded = require $path;
                if (is_array($loaded)) {
                    $fileConfig = $loaded;
                }
            }
        }

        // 1. config.php
        if (array_key_exists($key, $fileConfig)) {
            $value = $fileConfig[$key];
            // Treat empty string as "not configured" so env/default can win.
            if ($value !== '' && $value !== null) {
                return $value;
            }
        }

        // 2. environment
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }

        // 3. default
        return $default;
    }
}

if (!function_exists('app_config_bool')) {
    /**
     * Read a boolean configuration value.
     *
     * @param string $key
     * @param bool   $default
     * @return bool
     */
    function app_config_bool($key, $default = false)
    {
        $value = app_config($key, $default ? '1' : '0');
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}
