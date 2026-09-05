<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\JoinRequest;
use App\Models\EmployeeField;
use App\Models\Setting;
use Illuminate\Http\Request;

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
        // Get all active fields
        $fields = EmployeeField::active()->get();

        // Build validation rules dynamically
        $rules = [];
        foreach ($fields as $field) {
            $fieldRules = [];
            
            if ($field->is_required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            // Add type-specific validation
            switch ($field->type) {
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'phone':
                    $fieldRules[] = 'string|max:20';
                    break;
                default:
                    $fieldRules[] = 'string|max:1000';
            }

            $rules[$field->name] = implode('|', $fieldRules);
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

