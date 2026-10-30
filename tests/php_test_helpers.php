<?php
declare(strict_types=1);

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function checkSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "$message\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true)
        );
    }
}

function expectException(callable $action, string $exceptionClass, string $message): Throwable
{
    try {
        $action();
    } catch (Throwable $error) {
        check($error instanceof $exceptionClass, "$message: unexpected " . $error::class);
        return $error;
    }

    throw new RuntimeException("$message: no exception was thrown");
}
