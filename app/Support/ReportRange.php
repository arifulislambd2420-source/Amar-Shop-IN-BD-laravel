<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The date range of a dashboard report: Today, Yesterday, Last 7 days, Last 30 days, This month, or a
 * custom from–to. Days are the shop's days (Bangladesh time, see ShopTime); start()/end() are UTC
 * times to compare with the database's created_at — start inclusive, end exclusive.
 */
class ReportRange
{
    public const TODAY = 'today';

    public const YESTERDAY = 'yesterday';

    public const LAST_7 = 'last_7';

    public const LAST_30 = 'last_30';

    public const THIS_MONTH = 'this_month';

    public const CUSTOM = 'custom';

    public const DEFAULT = self::TODAY;

    /** A custom range is capped, so a typo can never make the dashboard scan years of orders. */
    public const MAX_DAYS = 366;

    /** @return array<string, string> key => label (Bangla source text, translated by the caller) */
    public static function options(): array
    {
        return [
            self::TODAY => 'আজ',
            self::YESTERDAY => 'গতকাল',
            self::LAST_7 => 'গত ৭ দিন',
            self::LAST_30 => 'গত ৩০ দিন',
            self::THIS_MONTH => 'এই মাস',
            self::CUSTOM => 'কাস্টম তারিখ',
        ];
    }

    public function __construct(
        public readonly string $key,
        public readonly Carbon $from, // first day, shop time, 00:00
        public readonly Carbon $until, // last day, shop time, 00:00
    ) {
    }

    /** @param  array<string, mixed>|null  $filters  the dashboard's filter form: range, from, until */
    public static function fromFilters(?array $filters): self
    {
        $key = (string) ($filters['range'] ?? self::DEFAULT);
        $key = array_key_exists($key, self::options()) ? $key : self::DEFAULT;
        $now = Carbon::now(ShopTime::zone())->startOfDay();

        [$from, $until] = match ($key) {
            self::YESTERDAY => [$now->copy()->subDay(), $now->copy()->subDay()],
            self::LAST_7 => [$now->copy()->subDays(6), $now],
            self::LAST_30 => [$now->copy()->subDays(29), $now],
            self::THIS_MONTH => [$now->copy()->startOfMonth(), $now],
            self::CUSTOM => self::custom($filters, $now),
            default => [$now, $now],
        };

        return new self($key, $from, $until);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function custom(?array $filters, Carbon $today): array
    {
        $parse = function ($value) use ($today): ?Carbon {
            try {
                return filled($value) ? Carbon::parse((string) $value, ShopTime::zone())->startOfDay() : null;
            } catch (\Throwable) {
                return null;
            }
        };

        $from = $parse($filters['from'] ?? null) ?? $today->copy();
        $until = $parse($filters['until'] ?? null) ?? $from->copy();

        if ($from->greaterThan($until)) {
            [$from, $until] = [$until, $from];
        }

        if ($from->diffInDays($until) >= self::MAX_DAYS) {
            $until = $from->copy()->addDays(self::MAX_DAYS - 1);
        }

        return [$from, $until];
    }

    /** First instant of the range, in UTC. */
    public function start(): Carbon
    {
        return $this->from->copy()->utc();
    }

    /** First instant after the range, in UTC. */
    public function end(): Carbon
    {
        return $this->until->copy()->addDay()->utc();
    }

    /** Number of shop days in the range. */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->until) + 1;
    }

    /** Shop-day dates (Y-m-d) from first to last. */
    public function dates(): array
    {
        return array_map(fn (int $i) => $this->from->copy()->addDays($i)->toDateString(), range(0, $this->days() - 1));
    }

    public function label(): string
    {
        $fmt = fn (Carbon $d) => $d->format('d M Y');

        return $this->from->equalTo($this->until) ? $fmt($this->from) : $fmt($this->from).' – '.$fmt($this->until);
    }
}
