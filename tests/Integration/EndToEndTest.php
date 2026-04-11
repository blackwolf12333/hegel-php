<?php

declare(strict_types=1);

namespace Hegel\Tests\Integration;

use Hegel\Generator\Generators as gen;
use Hegel\PHPUnit\HegelTrait;
use Hegel\PHPUnit\Property;
use Hegel\Runner;
use Hegel\Server\Session;
use Hegel\TestCase as HegelTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EndToEndTest extends TestCase
{
    use HegelTrait;

    #[\Override]
    protected function tearDown(): void
    {
        // Don't reset session between tests - reuse connection
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        Session::reset();
    }

    #[Test]
    public function integers_self_equality(): void
    {
        $this->check(function (HegelTestCase $tc): void {
            $n = $tc->draw(gen::integers(-1000, 1000));
            $this->assertSame($n, $n);
        });
    }

    #[Test]
    public function always_below_50_fails_and_shrinks(): void
    {
        $conn = Session::global()->connection();
        $runner = new Runner($conn);

        $result = $runner->run(
            testFn: function (HegelTestCase $tc): void {
                $n = $tc->draw(gen::integers(0, 100));
                if ($n >= 50) {
                    throw new \RuntimeException("Value {$n} is >= 50");
                }
            },
            testCases: 200,
        );

        $this->assertFalse($result->passed);
        $this->assertNotEmpty($result->finalErrors);
        // The shrunk value should be exactly 50 (minimal counterexample)
        $this->assertStringContainsString('50', $result->finalErrors[0]->getMessage());
    }

    #[Test]
    public function addition_is_commutative(): void
    {
        $this->check(function (HegelTestCase $tc): void {
            $x = $tc->draw(gen::integers(-1000, 1000));
            $y = $tc->draw(gen::integers(-1000, 1000));
            $this->assertSame($x + $y, $y + $x);
        });
    }

    #[Test]
    public function assume_filters_correctly(): void
    {
        $this->check(function (HegelTestCase $tc): void {
            $n = $tc->draw(gen::integers(0, 100));
            if ($n <= 0) {
                $tc->reject();
            }
            $this->assertGreaterThan(0, $n);
        });
    }

    #[Test]
    public function text_generation_produces_strings(): void
    {
        $this->check(function (HegelTestCase $tc): void {
            $text = $tc->draw(gen::text(0, 100));
            $this->assertIsString($text);
        });
    }

    #[Test]
    public function list_generation_with_bounds(): void
    {
        $this->check(function (HegelTestCase $tc): void {
            $list = $tc->draw(gen::lists(gen::integers(0, 100))->minSize(1)->maxSize(5));
            $this->assertIsArray($list);
            $this->assertGreaterThanOrEqual(1, count($list));
            $this->assertLessThanOrEqual(5, count($list));
        });
    }

    #[Test]
    public function boolean_generation(): void
    {
        $this->check(function (HegelTestCase $tc): void {
            $b = $tc->draw(gen::booleans());
            $this->assertIsBool($b);
        });
    }

    #[Test]
    public function sampled_from_returns_element(): void
    {
        $this->check(function (HegelTestCase $tc): void {
            $val = $tc->draw(gen::sampledFrom(['a', 'b', 'c']));
            $this->assertContains($val, ['a', 'b', 'c']);
        });
    }

    #[Test, Property(testCases: 50)]
    public function email_generation_contains_at_sign(): void
    {
        $this->check(function (HegelTestCase $tc): void {
            $email = $tc->draw(gen::emails());
            $this->assertStringContainsString('@', $email);
        });
    }

    #[Test]
    public function sort_is_idempotent(): void
    {
        $this->check(function (HegelTestCase $tc): void {
            $list = $tc->draw(gen::lists(gen::integers(0, 100)));
            sort($list);
            $sorted = $list;
            sort($sorted);
            $this->assertEquals($list, $sorted);
        });
    }
}
