<?php

namespace Tests\Feature\Atlas\Dyna\Concerns;

trait AssertsJsonSafeToolResult
{
    /**
     * Regression guard for a real prod bug: a ->map() closure extracting a Carbon-cast
     * date via raw property access leaks a live Carbon object into a tool's return array.
     * Mocked-Bedrock tests never catch this — only the real AWS SDK's Converse
     * document-type validator does, rejecting the whole request with `[...][json] is not
     * a valid document type`. Assert every leaf value is JSON-safe (scalar/null/array) so
     * this bug class can't silently reappear in any Dyna tool.
     */
    private function assertNoNonScalarLeaves(mixed $value, string $path = 'result'): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->assertNoNonScalarLeaves($item, "{$path}.{$key}");
            }

            return;
        }

        $this->assertTrue(
            is_scalar($value) || is_null($value),
            "Expected a JSON-safe scalar at {$path}, got ".(is_object($value) ? get_class($value) : gettype($value))
        );
    }
}
