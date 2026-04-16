<?php

declare(strict_types=1);

namespace Hegel\Tests\Integration;

use Hegel\Generator\Generators as gen;
use Hegel\PHPUnit\HegelTrait;
use Hegel\PHPUnit\Property;
use Hegel\TestCase as TC;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

function my_sort(array $list): array
{
    $result = $list;
    sort($result);
//    $result = array_unique($result);
    return array_values($result);
}

/**
 * This test covers the example that is shown in the README.md file. If we change this test
 * that change should be reflected in the README.md file.
 */
final class MySortTest extends TestCase
{
    use HegelTrait;

    #[Test, Property]
    public function matches_builtin(TC $tc): void
    {
        /** @var mixed $drawn */
        $drawn = $tc->draw(gen::lists(gen::integers()));
        assert(is_array($drawn), 'List draw must return an array');
        /** @var list<int> $list1 */
        $list1 = $drawn;
        $list2 = my_sort($list1);
        sort($list1);
        $this->assertEquals($list1, $list2);
    }
}
