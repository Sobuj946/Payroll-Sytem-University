<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\PayGrade;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The grades of the pay scale and the salary range of each grade.
 * The amounts are not in the code: the administrator enters them from the official pay scale.
 */
class PayGradeController extends Controller
{
    public function index()
    {
        return view('salary.grades.index', [
            'grades' => PayGrade::withCount('employees')->orderBy('grade_no')->get(),
        ]);
    }

    public function create()
    {
        return view('salary.grades.form', ['grade' => new PayGrade(['status' => 'active', 'increment_percent' => 5])]);
    }

    public function store(Request $request)
    {
        $grade = PayGrade::create($this->validated($request));

        AuditService::log('created', 'salary', "Pay grade {$grade->grade_no} was created ({$grade->label})", $grade->id);

        return redirect()->route('salary.grades.index')->with('success', "Grade {$grade->grade_no} has been added.");
    }

    public function edit(PayGrade $payGrade)
    {
        return view('salary.grades.form', ['grade' => $payGrade]);
    }

    public function update(Request $request, PayGrade $payGrade)
    {
        $data = $this->validated($request, $payGrade);
        $min = (float) $data['min_basic'];
        $max = (float) ($data['max_basic'] ?? $data['min_basic']);

        // The new range must still fit everybody who is placed in this grade.
        $outside = $payGrade->employees()->where(fn ($q) => $q->where('basic_salary', '<', $min)->orWhere('basic_salary', '>', $max))->count();

        if ($outside > 0) {
            throw new BusinessRuleException("The new range would leave {$outside} employee(s) of Grade {$payGrade->grade_no} outside it. Change their salary or grade first.");
        }

        $payGrade->update($data);

        AuditService::log('updated', 'salary', "Pay grade {$payGrade->grade_no} was updated ({$payGrade->label})", $payGrade->id);

        return redirect()->route('salary.grades.index')->with('success', "Grade {$payGrade->grade_no} has been updated.");
    }

    public function toggleStatus(PayGrade $payGrade)
    {
        $payGrade->update(['status' => $payGrade->status === 'active' ? 'inactive' : 'active']);
        $word = $payGrade->status === 'active' ? 'activated' : 'deactivated';

        AuditService::log($word, 'salary', "Pay grade {$payGrade->grade_no} was {$word}", $payGrade->id);

        return back()->with('success', "Grade {$payGrade->grade_no} has been {$word}.");
    }

    private function validated(Request $request, ?PayGrade $grade = null): array
    {
        return $request->validate([
            'grade_no' => ['required', 'integer', 'between:1,50', Rule::unique('pay_grades', 'grade_no')->ignore($grade?->id)],
            'name' => ['required', 'string', 'max:60'],
            'min_basic' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'max_basic' => ['nullable', 'numeric', 'gte:min_basic', 'max:99999999'],
            'increment_percent' => ['required', 'numeric', 'between:0,50'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'grade_no.required' => 'Please enter the grade number.',
            'grade_no.unique' => 'This grade number already exists.',
            'name.required' => 'Please enter a name for the grade.',
            'min_basic.required' => 'Please enter the starting basic salary.',
            'min_basic.gt' => 'The starting basic salary must be more than zero.',
            'max_basic.gte' => 'The highest basic salary cannot be lower than the starting one. Leave it empty for a fixed scale.',
            'increment_percent.required' => 'Please enter the yearly increment percentage (0 for none).',
            'increment_percent.between' => 'The increment must be between 0 and 50 percent.',
        ]);
    }
}
