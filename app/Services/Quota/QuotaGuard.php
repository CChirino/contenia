<?php

namespace App\Services\Quota;

use App\Enums\Network;
use App\Enums\QuotaAction;
use App\Exceptions\QuotaExceededException;
use App\Models\Brand;
use App\Models\BrandUsage;
use Carbon\CarbonImmutable;

/**
 * Límites de uso por marca y mes de contenido (spec §10).
 * `null` = ilimitado (plan Interno).
 */
class QuotaGuard
{
    public function limit(Brand $brand, QuotaAction $action, int $year, int $month): ?int
    {
        $planLimit = $brand->plan?->{$action->planColumn()};

        if ($planLimit === null) {
            return null; // ilimitado
        }

        $extra = (int) ($this->usage($brand, $year, $month)?->{$action->extraColumn()} ?? 0);

        return $planLimit + $extra;
    }

    public function used(Brand $brand, QuotaAction $action, int $year, int $month): int
    {
        return (int) ($this->usage($brand, $year, $month)?->{$action->value} ?? 0);
    }

    public function remaining(Brand $brand, QuotaAction $action, int $year, int $month): ?int
    {
        $limit = $this->limit($brand, $action, $year, $month);

        return $limit === null ? null : max(0, $limit - $this->used($brand, $action, $year, $month));
    }

    /**
     * Lanza QuotaExceededException (422) si no queda cupo para $amount.
     */
    public function check(Brand $brand, QuotaAction $action, int $year, int $month, int $amount = 1): void
    {
        $remaining = $this->remaining($brand, $action, $year, $month);

        if ($remaining !== null && $remaining < $amount) {
            $period = CarbonImmutable::create($year, $month, 1)
                ->translatedFormat('F \d\e Y');

            throw QuotaExceededException::for(
                $action->label(),
                (int) $this->limit($brand, $action, $year, $month),
                $brand->plan?->name ?? 'sin plan',
                $period,
            );
        }
    }

    /**
     * Incremento atómico del contador; llamar dentro de la transacción
     * que persiste el resultado para que un fallo no consuma cupo.
     */
    public function record(Brand $brand, QuotaAction $action, int $year, int $month, int $amount = 1): void
    {
        if ($amount <= 0) {
            return;
        }

        $usage = $brand->usageFor($year, $month);

        BrandUsage::withoutGlobalScopes()
            ->whereKey($usage->id)
            ->increment($action->value, $amount, [
                'updated_at' => now(),
            ]);
    }

    /**
     * Resumen para la barra de uso de la grilla.
     *
     * @return array{unlimited: bool, plan: string, actions: array<int, array{action: string, used: int, limit: ?int, extra: int}>}
     */
    public function summary(Brand $brand, int $year, int $month): array
    {
        $plan = $brand->plan;
        $usage = $this->usage($brand, $year, $month);

        $actions = [];
        foreach (QuotaAction::cases() as $action) {
            $actions[] = [
                'action' => $action->value,
                'used' => $this->used($brand, $action, $year, $month),
                'limit' => $this->limit($brand, $action, $year, $month),
                'extra' => (int) ($usage?->{$action->extraColumn()} ?? 0),
            ];
        }

        return [
            'unlimited' => collect($actions)->every(fn ($a) => $a['limit'] === null),
            'plan' => $plan?->name ?? 'Sin plan',
            'actions' => $actions,
        ];
    }

    /**
     * ¿El plan de la marca incluye esta red?
     */
    public function allowsNetwork(Brand $brand, Network $network): bool
    {
        $allowed = $brand->plan?->networks;

        return $allowed === null || in_array($network->value, $allowed, true);
    }

    /**
     * Fila de uso del periodo, sin scope de marca (el guard recibe la marca
     * explícita; el scope ya la filtraría igualmente si está bindeada).
     */
    private function usage(Brand $brand, int $year, int $month): ?BrandUsage
    {
        return $brand->usage()
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }
}
