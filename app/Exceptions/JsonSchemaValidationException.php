<?php

declare(strict_types=1);

namespace App\Exceptions;

use Opis\JsonSchema\Errors\ValidationError;
use RuntimeException;

class JsonSchemaValidationException extends RuntimeException
{
    /** @var array<int,string> */
    private readonly array $errorMessages;

    /**
     * @param  array<int,string> $errorMessages Human-readable list of schema violations.
     */
    public function __construct(
        string $schemaName,
        array $errorMessages,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $this->errorMessages = $errorMessages;

        $summary = implode('; ', $errorMessages);

        parent::__construct(
            message:  "JSON schema validation failed for schema '{$schemaName}': {$summary}",
            code:     $code,
            previous: $previous,
        );
    }

    /**
     * Build an instance directly from an opis ValidationError tree.
     */
    public static function fromValidationError(
        string $schemaName,
        ValidationError $error,
    ): self {
        return new self($schemaName, self::flattenErrors($error));
    }

    /** @return array<int,string> */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    /**
     * Recursively flattens an opis ValidationError tree into plain strings.
     *
     * @return array<int,string>
     */
    private static function flattenErrors(ValidationError $error, string $path = ''): array
    {
        $messages = [];

        $keyword  = $error->keyword();
        $pointer  = $path !== '' ? $path : '/';
        $messages[] = "[{$pointer}] {$keyword}: " . json_encode($error->args(), JSON_UNESCAPED_UNICODE);

        foreach ($error->subErrors() as $sub) {
            $subPath = $path . '/' . implode('/', $sub->data()->fullPath());
            $messages = array_merge($messages, self::flattenErrors($sub, $subPath));
        }

        return $messages;
    }
}
