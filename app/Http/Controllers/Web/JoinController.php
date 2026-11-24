<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EmployeeField;
use App\Models\JoinRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JoinController extends Controller
{
    /**
     * Display join page
     */
    public function index()
    {
        try {
            $joinBenefits = Setting::get('join_benefits', '');

            // Get all active employee fields for the form
            $employeeFields = EmployeeField::active()
                ->nonDocuments()
                ->ordered()
                ->get();
        } catch (\Exception $e) {
            $joinBenefits = '';
            $employeeFields = collect([]);
        }

        return view('web.join', compact('joinBenefits', 'employeeFields'));
    }

    /**
     * Store join request
     */
    public function store(Request $request)
    {
        // Validate exactly the fields index() renders. Document fields are not
        // on the public form, so requiring them here rejected every submission.
        $fields = EmployeeField::active()->nonDocuments()->get();

        // Build validation rules dynamically
        $rules = [];
        foreach ($fields as $field) {
            $fieldRules = [$field->is_required ? 'required' : 'nullable'];

            // Add type-specific validation
            switch ($field->type) {
                case 'email':
                    array_push($fieldRules, 'email', 'max:255');
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'phone':
                    array_push($fieldRules, 'string', 'max:20');
                    break;
                default:
                    array_push($fieldRules, 'string', 'max:1000');
            }

            // A select only accepts one of its options (the view renders a
            // plain text box when there are none).
            $options = $field->type === 'select' ? $field->optionList() : [];
            if ($options !== []) {
                $fieldRules[] = Rule::in($options);
            }

            $rules[$field->name] = $fieldRules;
        }

        $validated = $request->validate($rules);

        // Create join request
        JoinRequest::create([
            'field_data' => $validated,
            'status' => 'new',
        ]);

        return redirect()->back()
            ->with('success', 'Thank you for your interest! We will review your application and get back to you soon.');
    }
}
