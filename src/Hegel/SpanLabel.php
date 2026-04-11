<?php

declare(strict_types=1);

namespace Hegel;

enum SpanLabel: int
{
    case List_ = 1;
    case ListElement = 2;
    case Set = 3;
    case SetElement = 4;
    case Map = 5;
    case MapEntry = 6;
    case Tuple = 7;
    case OneOf = 8;
    case Optional = 9;
    case FixedDict = 10;
    case FlatMap = 11;
    case Filter = 12;
    case Mapped = 13;
    case SampledFrom = 14;
    case EnumVariant = 15;
}
