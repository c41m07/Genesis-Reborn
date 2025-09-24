<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use RuntimeException;

class ViewRenderer
{
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function render(string $template, array $parameters = []): string
    {
        $path = rtrim($this->basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $template;
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Template "%s" introuvable.', $template));
        }

        extract($parameters, EXTR_SKIP);
        ob_start();
        include $path;

        return (string) ob_get_clean();
    }
}
