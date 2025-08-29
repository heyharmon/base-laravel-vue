<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Response;
use App\Models\Chat;
use Carbon\Carbon;
use App\Exceptions\UsageLimitExceeded;

class TeamUsageService
{
    public function currentMonthCost(Team $team): float
    {
        [$start, $end] = $this->monthRange();
        $responseCost = Response::whereHas('prompt', fn($q) => $q->where('team_id', $team->id))
            ->whereBetween('created_at', [$start, $end])
            ->sum('cost');
        $chatCost = Chat::whereHas('conversation', fn($q) => $q->where('team_id', $team->id))
            ->whereBetween('created_at', [$start, $end])
            ->sum('cost');
        return (float) ($responseCost + $chatCost);
    }

    public function currentMonthPrice(Team $team): float
    {
        [$start, $end] = $this->monthRange();
        $responsePrice = Response::whereHas('prompt', fn($q) => $q->where('team_id', $team->id))
            ->whereBetween('created_at', [$start, $end])
            ->sum('price');
        $chatPrice = Chat::whereHas('conversation', fn($q) => $q->where('team_id', $team->id))
            ->whereBetween('created_at', [$start, $end])
            ->sum('price');
        return (float) ($responsePrice + $chatPrice);
    }

    public function exceedsLimit(Team $team, float $additionalCost = 0): bool
    {
        if (is_null($team->token_limit_cost)) {
            return false;
        }
        return ($this->currentMonthCost($team) + $additionalCost) > $team->token_limit_cost;
    }

    public function ensureWithinLimit(Team $team, float $additionalCost = 0): void
    {
        if ($this->exceedsLimit($team, $additionalCost)) {
            throw new UsageLimitExceeded('Team has exceeded its monthly token limit.');
        }
    }

    public function usageForPeriod(Team $team, Carbon $start, Carbon $end): array
    {
        $responsesQuery = Response::whereHas('prompt', fn($q) => $q->where('team_id', $team->id))
            ->whereBetween('created_at', [$start, $end]);
        $chatsQuery = Chat::whereHas('conversation', fn($q) => $q->where('team_id', $team->id))
            ->whereBetween('created_at', [$start, $end]);

        $responseTokens = (int) $responsesQuery->sum('usage->total_tokens');
        $responseCost = (float) $responsesQuery->sum('cost');
        $responsePrice = (float) $responsesQuery->sum('price');
        $responsesCount = (int) $responsesQuery->count();

        $chatTokens = (int) $chatsQuery->sum('usage->total_tokens');
        $chatCost = (float) $chatsQuery->sum('cost');
        $chatPrice = (float) $chatsQuery->sum('price');
        $chatsCount = (int) $chatsQuery->count();

        return [
            'responses' => [
                'count' => $responsesCount,
                'tokens' => $responseTokens,
                'cost' => $responseCost,
                'price' => $responsePrice,
            ],
            'chats' => [
                'count' => $chatsCount,
                'tokens' => $chatTokens,
                'cost' => $chatCost,
                'price' => $chatPrice,
            ],
            'total' => [
                'tokens' => $responseTokens + $chatTokens,
                'cost' => $responseCost + $chatCost,
                'price' => $responsePrice + $chatPrice,
            ],
        ];
    }

    protected function monthRange(): array
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();
        return [$start, $end];
    }
}
