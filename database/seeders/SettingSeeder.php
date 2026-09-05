<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Company Settings
            ['key' => 'company_name', 'value' => 'Home Nursing Care', 'type' => 'text', 'group' => 'company', 'label' => 'Company Name', 'description' => 'Your company name'],
            ['key' => 'company_description', 'value' => 'Professional home nursing and healthcare staffing services', 'type' => 'textarea', 'group' => 'company', 'label' => 'Company Description', 'description' => 'Brief description of your company'],
            ['key' => 'company_logo', 'value' => null, 'type' => 'image', 'group' => 'company', 'label' => 'Company Logo', 'description' => 'Upload company logo'],
            ['key' => 'company_email', 'value' => 'info@homenursing.com', 'type' => 'email', 'group' => 'company', 'label' => 'Company Email', 'description' => 'Primary contact email'],
            ['key' => 'company_phone', 'value' => '+1 234 567 8900', 'type' => 'text', 'group' => 'company', 'label' => 'Company Phone', 'description' => 'Primary contact phone'],
            ['key' => 'company_address', 'value' => '123 Healthcare Street, Medical City, MC 12345', 'type' => 'textarea', 'group' => 'company', 'label' => 'Company Address', 'description' => 'Full company address'],
            
            // Website Settings
            ['key' => 'website_title', 'value' => 'Home Nursing - Professional Healthcare Staffing', 'type' => 'text', 'group' => 'general', 'label' => 'Website Title', 'description' => 'Browser title'],
            ['key' => 'home_intro_title', 'value' => 'Quality Care at Your Doorstep', 'type' => 'text', 'group' => 'cms', 'label' => 'Home Page Intro Title', 'description' => 'Main heading on home page'],
            ['key' => 'home_intro_content', 'value' => 'We provide professional nursing care services in the comfort of your home. Our experienced nurses are dedicated to your health and wellbeing.', 'type' => 'textarea', 'group' => 'cms', 'label' => 'Home Page Intro Content', 'description' => 'Introduction text on home page'],
            
            // Services Page
            ['key' => 'services_intro', 'value' => 'We offer comprehensive home nursing services tailored to your needs', 'type' => 'textarea', 'group' => 'cms', 'label' => 'Services Page Intro', 'description' => 'Introduction text for services page'],
            
            // Join With Us Page
            ['key' => 'join_benefits', 'value' => "Join our team of healthcare professionals and enjoy:\n- Competitive compensation\n- Flexible schedules\n- Professional development\n- Supportive work environment\n- Make a difference in people's lives", 'type' => 'textarea', 'group' => 'cms', 'label' => 'Join With Us Benefits', 'description' => 'Benefits text for join page'],
            
            // About Us Page
            ['key' => 'about_title', 'value' => 'About Us', 'type' => 'text', 'group' => 'cms', 'label' => 'About Page Title', 'description' => 'Title for about page'],
            ['key' => 'about_content', 'value' => 'We are a leading home nursing service provider committed to delivering exceptional healthcare at home. With years of experience and a team of qualified professionals, we ensure the best care for your loved ones.', 'type' => 'textarea', 'group' => 'cms', 'label' => 'About Page Content', 'description' => 'Content for about page'],
            ['key' => 'about_banner', 'value' => null, 'type' => 'image', 'group' => 'cms', 'label' => 'About Page Banner', 'description' => 'Banner image for about page'],
            
            // Notification Settings
            ['key' => 'contract_alert_days', 'value' => '7', 'type' => 'number', 'group' => 'notification', 'label' => 'Contract Alert Days', 'description' => 'Days before contract expiry to show alerts'],
            ['key' => 'contract_max_assignments', 'value' => '10', 'type' => 'number', 'group' => 'system', 'label' => 'Max Employee Changes Per Service Line', 'description' => 'Includes initial assignment and replacements'],
            
            // Payment Settings
            ['key' => 'employee_payment_enabled', 'value' => 'false', 'type' => 'boolean', 'group' => 'payment', 'label' => 'Enable Employee Payment', 'description' => 'Require employees to pay for activation'],
            ['key' => 'employee_payment_amount', 'value' => '1000', 'type' => 'number', 'group' => 'payment', 'label' => 'Employee Payment Amount', 'description' => 'Amount for employee activation'],
            ['key' => 'employee_payment_validity_months', 'value' => '12', 'type' => 'number', 'group' => 'payment', 'label' => 'Payment Validity (Months)', 'description' => 'How long the payment is valid'],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }
}

