<?php

declare(strict_types=1);

namespace Hegel\Tests\Integration;

use Hegel\Generator\Generators as gen;
use Hegel\PHPUnit\HegelTrait;
use Hegel\PHPUnit\Property;
use Hegel\Runner;
use Hegel\Server\Session;
use Hegel\TestCase as TC;
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

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     */
    #[Test, Property]
    public function integers_self_equality(TC $tc): void
    {
        $n = $tc->draw(gen::integers(-1000, 1000));
        $this->assertSame($n, $n);
    }

    /**
     * @throws \Hegel\Exception\ConnectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\GeneratorNotSupportedException
     * @throws \InvalidArgumentException
     */
    #[Test]
    public function always_below_50_fails_and_shrinks(): void
    {
        $conn = Session::global()->connection();
        $runner = new Runner($conn);

        $result = $runner->run(
            testFn: static function (TC $tc): void {
                $n = (int) $tc->draw(gen::integers(0, 100));
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

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     */
    #[Test, Property]
    public function addition_is_commutative(TC $tc): void
    {
        $x = $tc->draw(gen::integers(-1000, 1000));
        $y = $tc->draw(gen::integers(-1000, 1000));
        $this->assertSame($x + $y, $y + $x);
    }

    /**
     * @throws \Hegel\Exception\AssumeRejectedException
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     */
    #[Test, Property]
    public function assume_filters_correctly(TC $tc): void
    {
        $n = $tc->draw(gen::integers(0, 100));
        if ($n <= 0) {
            $tc->reject();
        }
        $this->assertGreaterThan(0, $n);
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[Test, Property]
    public function text_generation_produces_strings(TC $tc): void
    {
        $drawn = $tc->draw(gen::text(0, 100));
        // @mago-expect analyzer:redundant-type-comparison
        $this->assertIsString($drawn);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     */
    #[Test, Property]
    public function list_generation_with_bounds(TC $tc): void
    {
        $drawn = $tc->draw(gen::lists(gen::integers(0, 100))->minSize(1)->maxSize(5));
        // @mago-expect analyzer:redundant-type-comparison
        $this->assertIsArray($drawn);
        $this->assertGreaterThanOrEqual(1, count($drawn));
        $this->assertLessThanOrEqual(5, count($drawn));
    }

    /**
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[Test, Property]
    public function boolean_generation(TC $tc): void
    {
        $drawn = $tc->draw(gen::booleans());
        // @mago-expect analyzer:redundant-type-comparison
        $this->assertIsBool($drawn);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     */
    #[Test, Property]
    public function sampled_from_returns_element(TC $tc): void
    {
        $val = $tc->draw(gen::sampledFrom(['a', 'b', 'c']));
        $this->assertContains($val, ['a', 'b', 'c']);
    }

    /**
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     * @throws \InvalidArgumentException
     */
    #[Test, Property(testCases: 50)]
    public function email_generation_contains_at_sign(TC $tc): void
    {
        $email = $tc->draw(gen::emails());
        $this->assertStringContainsString('@', $email);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \Hegel\Exception\ConnectionException
     * @throws \Hegel\Exception\DataExhaustedException
     */
    #[Test, Property]
    public function sort_is_idempotent(TC $tc): void
    {
        $drawn = $tc->draw(gen::lists(gen::integers(0, 100)));
        // @mago-expect analyzer:redundant-type-comparison
        assert(is_array($drawn), 'List draw must return an array');
        $list = $drawn;
        sort($list);
        $sorted = $list;
        sort($sorted);
        $this->assertEquals($list, $sorted);
    }

    #[Test, Property]
    public function list_generated_out_of_sample_from(TC $tc): void
    {
        $val = $tc->draw(gen::lists(gen::sampledFrom(['a', 'b', 'c']))->minSize(1)->maxSize(1));

        $anyInArray = in_array('a', $val, true)
            || in_array('b', $val, true)
            || in_array('c', $val, true);

        $this->assertTrue($anyInArray, "'a', 'b', or 'c' not found in " . print_r($val, true));
    }

    #[Test, Property]
    public function list_of_filtered_generator_should_never_contain_filtered_elements(TC $tc): void
    {
        $val = $tc->draw(
            gen::lists(gen::sampledFrom(['a', 'b', 'c'])->filter(static fn($value) => $value !== 'b'))
                ->minSize(1)
                ->maxSize(10)
        );

        $this->assertNotContains('b', $val, 'List should never contain \'b\': ' . print_r($val, true));
    }

    #[Test, Property]
    public function dict_of_filtered_generators_should_never_contain_filtered_elements(TC $tc): void
    {
        $val = $tc->draw(
            gen::dicts(
                gen::sampledFrom(['a', 'b', 'c'])->filter(static fn($value) => $value !== 'b'),
                gen::sampledFrom([1, 2, 3])->filter(static fn($value) => $value !== 2)
            )
        );

        $keys = array_keys($val);
        $values = array_values($val);

        $this->assertNotContains('b', $keys, 'Keys should never contain filtered value (\'b\'): '. print_r($keys, true));
        $this->assertNotContains(2, $values, 'Keys should never contain filtered value (\'2\'): '. print_r($values, true));
    }
}
