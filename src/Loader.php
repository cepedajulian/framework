<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use InvalidArgumentException;

/**
 * Initializes an application that uses the DasIntranet framework.
 *
 * Controllers are autoloaded through Composer (PSR-4), not required manually.
 */
final class Loader
{
    /**
     * Register the application's views, helper functions and route definitions.
     *
     * Calling boot() separately allows tests and command-line scripts to register
     * the application without dispatching an HTTP request.
     */
    public static function boot(string $appDirectory): void
    {
        $directory = realpath($appDirectory);

        if ($directory === false || !is_dir($directory)) {
            throw new InvalidArgumentException('Application directory not found: ' . $appDirectory);
        }

        App::setViews($directory . '/views');

        $functionsFile = $directory . '/controllers/functions.php';
        if (is_file($functionsFile)) {
            require_once $functionsFile;
        }

        // glob() returns paths in alphabetical order by default. Individual
        // controllers remain lazily loaded by Composer's PSR-4 autoloader.
        foreach (glob($directory . '/routes/*.php') ?: [] as $routeFile) {
            require $routeFile;
        }
    }

    /** Initialize the application and dispatch the current HTTP request. */
    public static function run(string $appDirectory): void
    {
        self::boot($appDirectory);
        App::run();
    }
}
