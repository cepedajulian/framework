<?php

declare(strict_types=1);

namespace Dasintranet\Framework;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class View extends App
{
    public function render(string $viewFile, array $data = []): string
    {
        return self::capture([$this->resolve($viewFile)], $data);
    }

    public function extend(string $viewFile, array $data = []): string
    {
        return self::capture([
            $this->resolve('layouts.appHead'),
            $this->resolve($viewFile),
            $this->resolve('layouts.appFooter'),
        ], $data);
    }

    private function resolve(string $name): string
    {
        // Dot notation ("users.profile") and nested paths are supported.
        // Traversal and absolute paths are intentionally rejected.
        if (!preg_match('~^[a-zA-Z0-9_-]+(?:[./][a-zA-Z0-9_-]+)*$~D', $name)) {
            throw new InvalidArgumentException('Invalid view name.');
        }

        $name = str_replace('.', '/', $name);
        $base = realpath(self::getViews());
        if ($base === false) {
            throw new RuntimeException('Views directory does not exist.');
        }

        $path = realpath($base . '/' . $name . '.php');
        if ($path === false || !is_file($path) || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('View not found: ' . $name);
        }

        return $path;
    }

    /** @param list<string> $paths */
    private static function capture(array $paths, array $data): string
    {
        return (static function (array $paths, array $data): string {
            extract($data, EXTR_SKIP);
            $outputLevel = ob_get_level();
            ob_start();

            try {
                foreach ($paths as $path) {
                    require $path;
                }

                return (string) ob_get_clean();
            } catch (Throwable $exception) {
                while (ob_get_level() > $outputLevel) {
                    ob_end_clean();
                }
                throw $exception;
            }
        })($paths, $data);
    }
}
