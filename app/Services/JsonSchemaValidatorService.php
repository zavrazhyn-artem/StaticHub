<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\JsonSchemaValidationException;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use RuntimeException;

/**
 * Thin wrapper around opis/json-schema that loads schemas from
 * resources/schemas/{name}.json and throws a typed exception on failure.
 */
final class JsonSchemaValidatorService
{
    private readonly Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
        // Allow the validator to resolve $ref URIs relative to the schema directory.
        $this->validator->resolver()->registerPrefix(
            'https://schema.local/',
            resource_path('schemas'),
        );
    }

    /**
     * Validates $data against the named schema.
     *
     * @param  array<string,mixed>|object $data       Already-decoded payload.
     * @param  string                     $schemaName Filename without extension (e.g. 'bnet_equipment').
     *
     * @throws JsonSchemaValidationException When validation fails.
     * @throws RuntimeException              When the schema file cannot be found.
     */
    public function validate(array|object $data, string $schemaName): bool
    {
        $schemaPath = resource_path("schemas/{$schemaName}.json");

        if (!file_exists($schemaPath)) {
            throw new RuntimeException(
                "JSON schema file not found: {$schemaPath}"
            );
        }

        $schemaContent = file_get_contents($schemaPath);

        if ($schemaContent === false) {
            throw new RuntimeException(
                "Failed to read JSON schema file: {$schemaPath}"
            );
        }

        $schema = json_decode($schemaContent);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Invalid JSON in schema file '{$schemaName}': " . json_last_error_msg()
            );
        }

        // opis/json-schema requires an object, not an array.
        $payload = is_array($data) ? (object) $this->deepCastToObject($data) : $data;

        $result = $this->validator->validate($payload, $schema);

        if (!$result->isValid()) {
            throw JsonSchemaValidationException::fromValidationError(
                $schemaName,
                $result->error(),   // @phpstan-ignore-line — only null when isValid()
            );
        }

        return true;
    }

    /**
     * Recursively converts an associative array to a stdClass tree so that
     * opis/json-schema treats objects as objects rather than arrays.
     *
     * @param  array<string,mixed> $data
     */
    private function deepCastToObject(array $data): \stdClass
    {
        $obj = new \stdClass();

        foreach ($data as $key => $value) {
            $obj->{$key} = is_array($value) && $this->isAssoc($value)
                ? $this->deepCastToObject($value)
                : (is_array($value) ? array_map(
                    fn($item) => is_array($item) && $this->isAssoc($item)
                        ? $this->deepCastToObject($item)
                        : $item,
                    $value,
                ) : $value);
        }

        return $obj;
    }

    /** @param array<mixed> $array */
    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
