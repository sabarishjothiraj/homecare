<?php

namespace Database\Seeders;

use App\Models\EmployeeField;
use Illuminate\Database\Seeder;

class EmployeeFieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fields = [
            // Basic Information Fields
            ['name' => 'first_name', 'label' => 'First Name', 'type' => 'text', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 1],
            ['name' => 'last_name', 'label' => 'Last Name', 'type' => 'text', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 2],
            ['name' => 'email', 'label' => 'Email Address', 'type' => 'email', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 3],
            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'phone', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 4],
            ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 5],
            ['name' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 6],

            // Professional Information
            ['name' => 'years_of_experience', 'label' => 'Years of Experience', 'type' => 'number', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 7],
            ['name' => 'previous_experience', 'label' => 'Previous Experience Details', 'type' => 'textarea', 'is_required' => false, 'is_mandatory' => false, 'is_document' => false, 'order' => 8],
            ['name' => 'specialization', 'label' => 'Specialization', 'type' => 'text', 'is_required' => false, 'is_mandatory' => false, 'is_document' => false, 'order' => 9],

            // Emergency Contact
            ['name' => 'emergency_contact_name', 'label' => 'Emergency Contact Name', 'type' => 'text', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 10],
            ['name' => 'emergency_contact_phone', 'label' => 'Emergency Contact Phone', 'type' => 'phone', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 11],
            ['name' => 'emergency_contact_relationship', 'label' => 'Emergency Contact Relationship', 'type' => 'text', 'is_required' => true, 'is_mandatory' => false, 'is_document' => false, 'order' => 12],

            // MANDATORY NURSING DOCUMENTS (Cannot be removed/modified)
            [
                'name' => 'nursing_license',
                'label' => 'Nursing License',
                'type' => 'file',
                'options' => ['formats' => ['pdf', 'jpg', 'jpeg', 'png']],
                'is_required' => true,
                'is_mandatory' => true,
                'is_document' => true,
                'order' => 100,
            ],
            [
                'name' => 'id_proof',
                'label' => 'Government ID Proof',
                'type' => 'file',
                'options' => ['formats' => ['pdf', 'jpg', 'jpeg', 'png']],
                'is_required' => true,
                'is_mandatory' => true,
                'is_document' => true,
                'order' => 101,
            ],
            [
                'name' => 'medical_clearance',
                'label' => 'Medical Clearance Certificate',
                'type' => 'file',
                'options' => ['formats' => ['pdf', 'jpg', 'jpeg', 'png']],
                'is_required' => true,
                'is_mandatory' => true,
                'is_document' => true,
                'order' => 102,
            ],
            [
                'name' => 'background_check',
                'label' => 'Background Check Certificate',
                'type' => 'file',
                'options' => ['formats' => ['pdf', 'jpg', 'jpeg', 'png']],
                'is_required' => true,
                'is_mandatory' => true,
                'is_document' => true,
                'order' => 103,
            ],
            [
                'name' => 'vaccination_record',
                'label' => 'Vaccination Record',
                'type' => 'file',
                'options' => ['formats' => ['pdf', 'jpg', 'jpeg', 'png']],
                'is_required' => true,
                'is_mandatory' => true,
                'is_document' => true,
                'order' => 104,
            ],

            // Optional Documents
            [
                'name' => 'resume',
                'label' => 'Resume/CV',
                'type' => 'file',
                'options' => ['formats' => ['pdf', 'doc', 'docx']],
                'is_required' => false,
                'is_mandatory' => false,
                'is_document' => true,
                'order' => 200,
            ],
        ];

        // name is the field's key; firstOrCreate so a re-run neither
        // duplicates fields nor overwrites the office's edits.
        foreach ($fields as $field) {
            EmployeeField::firstOrCreate(['name' => $field['name']], $field);
        }
    }
}
