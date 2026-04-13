> [!IMPORTANT]
> We're excited you're checking out Hegel! Hegel is in beta, and we'd love for you to try it and [report any feedback](https://github.com/blackwolf12333/hegel-php/issues/new).
>
> As part of our beta, we may make breaking changes if it makes Hegel a better property-based testing library. If that instability bothers you, please check back in a few months for a stable release!
>
> See https://hegel.dev/compatibility for more details.

# Hegel for PHP

* [Hegel website](https://hegel.dev)

`hegel-php` is a property-based testing library for PHP. `hegel-php` is based on [hegel-go](https://github.com/hegeldev/hegel-go), using the [Hegel](https://hegel.dev/) protocol.

## Installation

To install: `composer require --dev blackwolf12333/hegel-php`.

Hegel requires PHP 8.4+ and PHPUnit 13+.

Hegel will use [uv](https://docs.astral.sh/uv/) to install the required [hegel-core](https://github.com/hegeldev/hegel-core) server component.
If `uv` is already on your path, it will use that, otherwise it will download a private copy of it to ~/.cache/hegel and not put it on your path.
See https://hegel.dev/reference/installation for details.

## Quickstart

Here's a quick example of how to write a Hegel test:

```php
<?php

use Hegel\Generator\Generators as gen;
use Hegel\PHPUnit\HegelTrait;
use Hegel\PHPUnit\Property;
use Hegel\TestCase as TC;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

function mySort(array $list): array
{
    $result = $list;
    sort($result);
    $result = array_unique($result);
    return array_values($result);
}

final class MySortTest extends TestCase
{
    use HegelTrait;

    #[Test, Property]
    public function matches_builtin(TC $tc): void
    {
        $list1 = (array) $tc->draw(gen::lists(gen::integers()));
        $list2 = mySort($list1);
        sort($list1);
        $this->assertEquals($list1, $list2);
    }
}
```

This test will fail when run with `./vendor/bin/phpunit`! Hegel will produce a minimal failing test case for us:

```
1) Hegel\Tests\Integration\MySortTest::matches_builtin
Failed asserting that two arrays are equal.
--- Expected
+++ Actual
@@ @@
 Array (
     0 => 0
-    1 => 0
 )
```

Hegel reports the minimal example showing that our sort is incorrectly dropping duplicates. If we remove the `array_unique()` call from `mySort()`, this test will then pass (because it's just comparing the standard sort against itself).
