<?php

namespace App\Services\Setup;

use RuntimeException;

/**
 * Minimal, dependency-free writer for the application's `.env` file.
 *
 * It updates existing keys in place (preserving comments and ordering) and
 * appends missing ones, quoting values only when the dotenv grammar requires
 * it. This is setup infrastructure, deliberately isolated from the module
 * Service–Repository layers.
 */
class EnvironmentWriter
{
    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? app()->environmentFilePath();
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function isWritable(): bool
    {
        return $this->exists()
            ? is_writable($this->path)
            : is_writable(dirname($this->path));
    }

    /**
     * Ensure the environment file exists, seeding it from `.env.example`.
     */
    public function ensureExists(): bool
    {
        if ($this->exists()) {
            return true;
        }

        $example = $this->path.'.example';

        return is_file($example) && copy($example, $this->path);
    }

    /**
     * Read every key/value pair from the environment file.
     *
     * @return array<string, string>
     */
    public function read(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $contents = (string) file_get_contents($this->path);
        $values = [];

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            if (! preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/i', $line, $matches)) {
                continue;
            }

            $values[strtoupper($matches[1])] = $this->unquote(trim($matches[2]));
        }

        return $values;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->read()[strtoupper($key)] ?? $default;
    }

    /**
     * Set one or more environment values.
     *
     * @param  array<string, string|int|float|bool|null>  $values
     */
    public function set(array $values): void
    {
        if ($values === []) {
            return;
        }

        $contents = $this->exists() ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $key = strtoupper((string) $key);
            $line = $key.'='.$this->format($value);
            $pattern = '/^\s*'.preg_quote($key, '/').'\s*=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = (string) preg_replace($pattern, $line, $contents, 1);

                continue;
            }

            $contents = rtrim($contents, "\r\n");
            $contents = ($contents === '' ? '' : $contents.PHP_EOL).$line.PHP_EOL;
        }

        if (! str_ends_with($contents, "\n")) {
            $contents .= PHP_EOL;
        }

        if (file_put_contents($this->path, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write to the environment file [{$this->path}].");
        }
    }

    protected function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }

    protected function format(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#=$"\'\\\\]/', $value) === 1) {
            // Single quotes keep `$` literal (no interpolation); fall back to
            // double quotes only when the value contains a single quote.
            if (! str_contains($value, "'")) {
                return "'".$value."'";
            }

            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
