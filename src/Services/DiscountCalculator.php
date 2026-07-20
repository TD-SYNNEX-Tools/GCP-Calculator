<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Aplica as regras oficiais de desconto SecOps.
 *
 * Cenários (TOTAL = TD + REVENDA):
 *   Standard      + DR  => 27% (10% TD + 17% Revenda)
 *   Standard      s/ DR => 17% ( 7% TD + 10% Revenda)
 *   Non-Standard  + DR  => 22% ( 7% TD + 15% Revenda)
 *   Non-Standard  s/ DR => 12% ( 7% TD +  5% Revenda)
 */
final readonly class DiscountBreakdown
{
    public function __construct(
        public float $totalPct,
        public float $tdPct,
        public float $resellerPct,
    ) {}

    public function toArray(): array
    {
        return [
            'total_pct'    => $this->totalPct,
            'td_pct'       => $this->tdPct,
            'reseller_pct' => $this->resellerPct,
        ];
    }
}

final class DiscountCalculator
{
    public function calculate(PricingType $type, bool $dealRegistration): DiscountBreakdown
    {
        [$total, $td, $reseller] = match (true) {
            $type === PricingType::STANDARD     && $dealRegistration => [27.0, 10.0, 17.0],
            $type === PricingType::STANDARD     && !$dealRegistration => [17.0,  7.0, 10.0],
            $type === PricingType::NON_STANDARD && $dealRegistration => [22.0,  7.0, 15.0],
            $type === PricingType::NON_STANDARD && !$dealRegistration => [12.0,  7.0,  5.0],
        };

        return new DiscountBreakdown($total, $td, $reseller);
    }
}
