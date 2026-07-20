<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Regras de precificação SecOps.
 * - 1 TB = 1024 GB (10 TB = 10.240 GB)
 * - Preço USD/TB/ano, contrato multi-anual multiplica pelo nº de anos.
 * - Aplica-se o desconto TOTAL sobre o valor bruto.
 * - Custo mensal (BRL) = (valor líquido em USD * câmbio) / (12 * anos).
 */
final class PricingService
{
    public const GB_PER_TB = 1024;

    public function tbToGb(float $tb): float
    {
        return round($tb * self::GB_PER_TB, 2);
    }

    /**
     * @return array{
     *   gb_per_year:float,
     *   gross_usd:float,
     *   discount_amount_usd:float,
     *   net_usd:float,
     *   monthly_brl:float
     * }
     */
    public function computeItem(
        float $unitPriceUsdPerTbYear,
        float $tbPerYear,
        int $contractYears,
        float $totalDiscountPct,
        float $dollarRate
    ): array {
        $gross      = $unitPriceUsdPerTbYear * $tbPerYear * $contractYears;
        $discount   = $gross * ($totalDiscountPct / 100);
        $net        = $gross - $discount;
        $totalMonths = max(1, 12 * $contractYears);
        $monthlyBrl = ($net * $dollarRate) / $totalMonths;

        return [
            'gb_per_year'         => $this->tbToGb($tbPerYear),
            'gross_usd'           => round($gross, 2),
            'discount_amount_usd' => round($discount, 2),
            'net_usd'             => round($net, 2),
            'monthly_brl'         => round($monthlyBrl, 2),
        ];
    }
}
