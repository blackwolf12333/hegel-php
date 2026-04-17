<?php

declare(strict_types=1);

namespace Hegel\Tests\Unit\Protocol\Command;

use Hegel\Protocol\Command\CollectionMoreCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionMoreCommandTest extends TestCase
{
    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function to_array_contains_command_key(): void
    {
        $command = new CollectionMoreCommand(collectionId: 42);

        /** @var array{command: string, collection_id: int|string} $result */
        $result = $command->toArray();

        $this->assertArrayHasKey('command', $result);
        $this->assertSame('collection_more', $result['command']);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function to_array_contains_collection_id(): void
    {
        $command = new CollectionMoreCommand(collectionId: 7);

        /** @var array{command: string, collection_id: int|string} $result */
        $result = $command->toArray();

        $this->assertSame(7, $result['collection_id']);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[Test]
    public function to_array_contains_string_collection_id(): void
    {
        $command = new CollectionMoreCommand(collectionId: 'abc');

        /** @var array{command: string, collection_id: int|string} $result */
        $result = $command->toArray();

        $this->assertSame('abc', $result['collection_id']);
    }
}
