<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * "A day" at the property runs from midnight to midnight in config('payments.timezone'), whatever the
 * app timezone is (same rule as the daily cash summary). Timestamps are stored in the app timezone.
 */
final class PropertyTime
{
    public static function timezone(): string
    {
        return (string) config('payments.timezone', config('app.timezone'));
    }

    public static function today(): string
    {
        return CarbonImmutable::now(self::timezone())->toDateString();
    }

    /**
     * Start of $from and the start of the day after $to, in the app timezone, for "where >= start and < end".
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(string $from, string $to): array
    {
        $app = (string) config('app.timezone');

        return [
            CarbonImmutable::parse($from, self::timezone())->startOfDay()->setTimezone($app),
            CarbonImmutable::parse($to, self::timezone())->startOfDay()->addDay()->setTimezone($app),
        ];
    }

    /** The property's calendar date (Y-m-d) of a stored timestamp. */
    public static function dateOf(CarbonInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::timezone())->toDateString();
    }

    /** A stored timestamp shown in property time. */
    public static function local(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::timezone());
    }
}
