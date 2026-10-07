<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\SalaryComponent;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The master list of earnings and deductions. */
class SalaryComponentController extends Controller
{
    public function index()
    {
        return view('salary.components.index', [
            'components' => SalaryComponent::orderBy('type')->orderBy('source')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('salary.components.form', [
            'component' => new SalaryComponent(['type' => 'earning', 'calc_type' => 'fixed', 'source' => 'structure', 'default_value' => 0, 'is_taxable' => true, 'status' => 'active']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(true), $this->messages());
        $this->checkValue($data);

        // Amounts typed in every month cannot be percentages.
        if ($data['source'] === 'adjustment') {
            $data['calc_type'] = 'fixed';
        }

        $component = SalaryComponent::create($data);

        AuditService::log('created', 'salary', "Salary component {$component->name} ({$component->code}) was created", $component->id);

        return redirect()->route('salary.components.index')->with('success', "\"{$component->name}\" has been added.");
    }

    public function edit(SalaryComponent $component)
    {
        $this->ensureEditable($component);

        return view('salary.components.form', compact('component'));
    }

    public function update(Request $request, SalaryComponent $component)
    {
        $this->ensureEditable($component);

        // Code, type and source stay as created: payroll and old payslips refer to them.
        $data = $request->validate($this->rules(false), $this->messages());
        $data['calc_type'] = $component->source === 'adjustment' ? 'fixed' : $data['calc_type'];
        $this->checkValue($data);

        $component->update($data);

        AuditService::log('updated', 'salary', "Salary component {$component->name} ({$component->code}) was updated", $component->id);

        return redirect()->route('salary.components.index')->with('success', "\"{$component->name}\" has been updated.");
    }

    public function toggleStatus(SalaryComponent $component)
    {
        $this->ensureEditable($component);

        $component->update(['status' => $component->status === 'active' ? 'inactive' : 'active']);
        $word = $component->status === 'active' ? 'activated' : 'deactivated';

        AuditService::log($word, 'salary', "Salary component {$component->name} was {$word}", $component->id);

        return back()->with('success', "\"{$component->name}\" has been {$word}.");
    }

    private function ensureEditable(SalaryComponent $component): void
    {
        if ($component->source === 'system') {
            throw new BusinessRuleException("\"{$component->name}\" is calculated by the payroll engine and cannot be changed here.");
        }
    }

    private function rules(bool $creating): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'calc_type' => ['required', Rule::in(['fixed', 'percent'])],
            'default_value' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'is_taxable' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];

        if ($creating) {
            $rules += [
                'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_]+$/', Rule::unique('salary_components', 'code')],
                'type' => ['required', Rule::in(['earning', 'deduction'])],
                'source' => ['required', Rule::in(['structure', 'adjustment'])],
            ];
        }

        return $rules;
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Please enter the component name.',
            'code.required' => 'Please enter a short code.',
            'code.regex' => 'The code may only contain capital letters, numbers and underscores.',
            'code.unique' => 'This code is already used by another component.',
            'type.required' => 'Please choose whether this is an earning or a deduction.',
            'source.required' => 'Please choose how this component is used.',
            'default_value.required' => 'Please enter the default amount (0 if there is none).',
            'default_value.numeric' => 'The default amount must be a number.',
        ];
    }

    private function checkValue(array $data): void
    {
        if (($data['calc_type'] ?? 'fixed') === 'percent' && (float) $data['default_value'] > 100) {
            throw new BusinessRuleException('A percentage cannot be more than 100.');
        }
    }
}
