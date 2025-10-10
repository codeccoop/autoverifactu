<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Models\Model;

/**
 * Invoice lines as breakdown details
 */
class BreakdownDetails extends Model
{
    /**
     * @var string Key of the tax type from the TaxType class.
     */
    public $taxType;

    /**
     * @var string Key of the regime type from RegimeType class.
     */
    public $regimeType;

    /**
     * @var string Key of the operation type from the OperationType class.
     */
    public $operationType;

    /**
     * @var string Tax rate percentage formated with thow decimal precission.
     */
    public $taxRate;

    /**
     * @var string Base amount formated with two decimal precission.
     */
    public $baseAmount;

    /**
     * @var string Tax amount formated with two decimal precission.
     */
    public $taxAmount;

    final public function validateTaxAmount($context)
    {
        if (!isset($this->taxRate) || !isset($this->baseAmount) || !isset($this->taxAmount)) {
            return;
        }

        $validTaxAmount = false;
        $bestTaxAmount = (float) $this->baseAmount * ((float) $this->taxRate / 100);
        foreach ([0, -0.01, 0.01, -0.02, 0.02] as $tolerance) {
            $expectedTaxAmount = number_format($bestTaxAmount + $tolerance, 2, '.', '');
            if ($this->taxAmount === $expectedTaxAmount) {
                $validTaxAmount = true;
                break;
            }
        }

        if (!$validTaxAmount) {
            $bestTaxAmount = number_format($bestTaxAmount, 2, '.', '');
            $context->buildViolation("Expected tax amount of $bestTaxAmount, got {$this->taxAmount}")
                ->atPath('taxAmount')
                ->addViolation();
        }
    }
}
