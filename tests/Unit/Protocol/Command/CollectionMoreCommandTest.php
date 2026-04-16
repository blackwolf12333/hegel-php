<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Protocol\Command;

use Hegel\Protocol\Command\CollectionMoreCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionMoreCommandTest extends TestCase
{
    #[Test]
    public function to_array_contains_command_key(): void
    {
        $command = new CollectionMoreCommand(collectionId: 42);

        /** @var mixed $result */
        $result = $command->toArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('command', $result);
        $this->assertSame('collection_more', $result['command']);
    }

    #[Test]
    public function to_array_contains_collection_id(): void
    {
        $command = new CollectionMoreCommand(collectionId: 7);

        /** @var mixed $result */
        $result = $command->toArray();

        $this->assertIsArray($result);
        $this->assertSame(7, $result['collection_id']);
    }

    #[Test]
    public function to_array_contains_string_collection_id(): void
    {
        $command = new CollectionMoreCommand(collectionId: 'abc');

        /** @var mixed $result */
        $result = $command->toArray();

        $this->assertIsArray($result);
        $this->assertSame('abc', $result['collection_id']);
    }
}
