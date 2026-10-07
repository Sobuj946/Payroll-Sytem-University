<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use App\Models\TaxSlab;
use Illuminate\Database\Seeder;

class SalaryComponentSeeder extends Seeder
{
    public function run(): void
    {
        // code, name, type, calc_type, default value, source, taxable
        // source: structure = assigned per employee, adjustment = entered monthly, system = calculated by payroll
        $components = [
            ['HRA', 'House Rent Allowance', 'earning', 'percent', 20, 'structure', true],
            ['MED', 'Medical Allowance', 'earning', 'fixed', 5000, 'structure', true],
            ['TRN', 'Transport Allowance', 'earning', 'fixed', 3000, 'structure', true],
            ['FOOD', 'Food Allowance', 'earning', 'fixed', 2000, 'structure', true],
            ['MOB', 'Mobile Allowance', 'earning', 'fixed', 1000, 'structure', true],
            ['OTHALW', 'Other Allowance', 'earning', 'fixed', 0, 'structure', true],
            ['OT', 'Overtime', 'earning', 'fixed', 0, 'adjustment', true],
            ['BONUS', 'Performance Bonus', 'earning', 'fixed', 0, 'adjustment', true],
            ['FBONUS', 'Festival Bonus', 'earning', 'fixed', 0, 'adjustment', true],
            ['PF', 'Provident Fund', 'deduction', 'percent', 5, 'structure', false],
            ['LOAN', 'Loan Installment', 'deduction', 'fixed', 0, 'adjustment', false],
            ['ADV', 'Salary Advance', 'deduction', 'fixed', 0, 'adjustment', false],
            ['OTHDED', 'Other Deduction', 'deduction', 'fixed', 0, 'adjustment', false],
            ['TAX', 'Income Tax', 'deduction', 'fixed', 0, 'system', false],
            ['LATE', 'Late Deduction', 'deduction', 'fixed', 0, 'system', false],
            ['ABS', 'Absence Deduction', 'deduction', 'fixed', 0, 'system', false],
            ['UNPAID', 'Unpaid Leave Deduction', 'deduction', 'fixed', 0, 'system', false],
        ];

        foreach ($components as [$code, $name, $type, $calc, $value, $source, $taxable]) {
            SalaryComponent::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => $type, 'calc_type' => $calc, 'default_value' => $value,
                'source' => $source, 'is_taxable' => $taxable, 'status' => 'active',
            ]);
        }

        // SAMPLE annual slabs for the demo. Not an official rate table.
        if (TaxSlab::count() === 0) {
            $slabs = [
                [0, 350000, 0],
                [350000, 450000, 5],
                [450000, 750000, 10],
                [750000, 1150000, 15],
                [1150000, 1650000, 20],
                [1650000, null, 25],
            ];
            foreach ($slabs as [$from, $to, $rate]) {
                TaxSlab::create([
                    'from_amount' => $from, 'to_amount' => $to,
                    'rate_percent' => $rate, 'effective_from' => '2026-07-01',
                ]);
            }
        }
    }
}
