<?php

namespace App\Domains\TemporalRouting\Services;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

class TemporalRuleStatusService
{
    /** @return array<string, mixed> */
    public function rule(SwitchAccount $account, SwitchTemporalRule $rule, ?CarbonImmutable $at = null): array
    {
        $timezone = $this->timezone($account);
        $evaluatedAt = ($at ?? CarbonImmutable::now('UTC'))->setTimezone($timezone);
        $override = $rule->enabled === true ? 'forced_active' : ($rule->enabled === false ? 'forced_inactive' : 'scheduled');
        $active = $rule->enabled ?? $this->matchesSchedule($rule, $evaluatedAt);

        return [
            'state' => $active ? 'active' : 'inactive',
            'is_active' => $active,
            'override' => $override,
            'timezone' => $timezone,
            'evaluated_at' => $evaluatedAt->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function ruleSet(SwitchAccount $account, SwitchTemporalRuleSet $set, ?CarbonImmutable $at = null): array
    {
        $set->loadMissing('rules.rule');
        $statuses = $set->rules
            ->map(function ($membership) use ($account, $at): ?array {
                if ($membership->rule === null) {
                    return null;
                }

                $status = $this->rule($account, $membership->rule, $at);
                $membership->rule->setAttribute('effective_status', $status);

                return $status;
            })
            ->filter()
            ->values();
        $overrides = $statuses->pluck('override')->unique()->values();

        return [
            'state' => $statuses->contains(fn (array $status): bool => $status['is_active']) ? 'active' : 'inactive',
            'is_active' => $statuses->contains(fn (array $status): bool => $status['is_active']),
            'override' => $overrides->count() === 1 ? $overrides->first() : ($overrides->isEmpty() ? 'empty' : 'mixed'),
            'timezone' => $this->timezone($account),
            'evaluated_at' => ($at ?? CarbonImmutable::now('UTC'))->setTimezone($this->timezone($account))->toIso8601String(),
            'rule_count' => $set->rules->count(),
            'resolved_rule_count' => $statuses->count(),
            'active_rule_count' => $statuses->filter(fn (array $status): bool => $status['is_active'])->count(),
        ];
    }

    private function matchesSchedule(SwitchTemporalRule $rule, CarbonImmutable $at): bool
    {
        $start = $rule->start_date === null
            ? CarbonImmutable::create(2011, 1, 1, 0, 0, 0, $at->timezone)
            : CarbonImmutable::parse($rule->start_date->format('Y-m-d'), $at->timezone)->startOfDay();

        if ($at->startOfDay()->lessThan($start)) {
            return false;
        }

        $seconds = ($at->hour * 3600) + ($at->minute * 60) + $at->second;
        $windowStart = $rule->time_window_start ?? 0;
        $windowStop = $rule->time_window_stop ?? 86400;

        if ($seconds < $windowStart || $seconds > $windowStop) {
            return false;
        }

        $interval = max(1, $rule->interval);

        return match ($rule->cycle) {
            'date' => $at->isSameDay($start),
            'daily' => (int) $start->diffInDays($at->startOfDay()) % $interval === 0,
            'weekly' => (int) $start->startOfWeek()->diffInWeeks($at->startOfWeek()) % $interval === 0
                && in_array(strtolower($at->englishDayOfWeek), $rule->weekdays ?? [], true),
            'monthly' => (int) $start->startOfMonth()->diffInMonths($at->startOfMonth()) % $interval === 0
                && $this->matchesDay($rule, $at),
            'yearly' => ($at->year - $start->year) % $interval === 0
                && $at->month === ($rule->month ?? $start->month)
                && $this->matchesDay($rule, $at),
            default => false,
        };
    }

    private function matchesDay(SwitchTemporalRule $rule, CarbonImmutable $at): bool
    {
        if (in_array($at->day, $rule->days ?? [], true)) {
            return true;
        }

        $weekdays = $rule->weekdays ?? [];
        if (! in_array(strtolower($at->englishDayOfWeek), $weekdays, true)) {
            return false;
        }

        return match ($rule->ordinal ?? 'first') {
            'every' => true,
            'first' => $at->weekNumberInMonth === 1,
            'second' => $at->weekNumberInMonth === 2,
            'third' => $at->weekNumberInMonth === 3,
            'fourth' => $at->weekNumberInMonth === 4,
            'fifth' => $at->weekNumberInMonth === 5,
            'last' => $at->addWeek()->month !== $at->month,
            default => false,
        };
    }

    private function timezone(SwitchAccount $account): string
    {
        $timezone = $account->timezone ?: (string) config('app.timezone', 'UTC');

        try {
            new DateTimeZone($timezone);

            return $timezone;
        } catch (Throwable) {
            return 'UTC';
        }
    }
}
