<?php

namespace MigrAlign\Scanning;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use ReflectionMethod;

final class BlueprintCompatibility
{
    private static ?bool $connectionFirst = null;

    public static function blueprintTakesConnectionFirst(): bool
    {
        if (self::$connectionFirst !== null) {
            return self::$connectionFirst;
        }

        $parameter = (new ReflectionMethod(Blueprint::class, '__construct'))->getParameters()[0] ?? null;
        self::$connectionFirst = $parameter?->getType()?->getName() === Connection::class;

        return self::$connectionFirst;
    }
}
