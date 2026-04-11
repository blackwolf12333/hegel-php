<?php

declare(strict_types=1);

namespace Hegel\Generator;

/**
 * A generator that has a serializable schema for server-side generation.
 *
 * Schema generators produce schemas sent to the hegel-core server.
 * Composed generators (map/filter/flatMap) operate at the PHP level
 * and do not implement this interface.
 */
interface SchemaGenerator extends Generator
{
    /** @return array<string, mixed> */
    public function schema(): array;
}
