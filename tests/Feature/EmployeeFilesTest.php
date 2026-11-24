<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Employee photos and KYC documents live on the private disk and are only
 * reachable through the authenticated admin routes. Paths posted by the
 * browser are never trusted for keeping or deleting files.
 */
class EmployeeFilesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    private DocumentType $idProof;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->admin = User::factory()->superAdmin()->create();
        $this->service = Service::factory()->create();
        $this->idProof = DocumentType::create(['name' => 'Aadhaar Card', 'is_active' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Asha',
            'last_name' => 'Nair',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'service_ids' => [$this->service->id],
            'present_address' => [
                'address_line1' => '1 Main Street',
                'city' => 'Kochi',
                'state' => 'Kerala',
                'country' => 'India',
                'pincode' => '682001',
            ],
            'same_as_present_address' => 1,
            'emergency_contacts' => [
                ['name' => 'Ravi', 'relationship' => 'Brother', 'phone' => '9876500001'],
                ['name' => 'Mala', 'relationship' => 'Mother', 'phone' => '9876500002'],
            ],
        ], $overrides);
    }

    /**
     * An employee with a private photo and one private document.
     */
    private function employeeWithFiles(): Employee
    {
        Storage::disk('local')->put('employee_images/asha.jpg', 'photo');
        Storage::disk('local')->put('employee_documents_dynamic/own.pdf', '%PDF-own');

        return Employee::factory()->create([
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'field_data' => [
                'employee_image_path' => 'employee_images/asha.jpg',
                'employee_image_name' => 'asha.jpg',
                'documents' => [[
                    'type' => $this->idProof->id,
                    'type_name' => 'Aadhaar Card',
                    'file_path' => 'employee_documents_dynamic/own.pdf',
                    'file_name' => 'own.pdf',
                ]],
            ],
        ]);
    }

    public function test_new_uploads_are_stored_privately_and_served_through_the_admin_routes(): void
    {
        $this->actingAs($this->admin)->post('/admin/employees', $this->payload([
            'employee_image' => UploadedFile::fake()->create('asha.jpg', 20, 'image/jpeg'),
            'documents' => [[
                'type' => $this->idProof->id,
                'file' => UploadedFile::fake()->createWithContent('aadhaar.pdf', '%PDF-1.4 aadhaar'),
            ]],
        ]))->assertRedirect('/admin/employees')->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();
        $photo = $employee->field_data['employee_image_path'];
        $document = $employee->field_data['documents'][0]['file_path'];

        $this->assertStringStartsWith('employee_images/', $photo);
        $this->assertStringStartsWith('employee_documents_dynamic/', $document);
        Storage::disk('local')->assertExists([$photo, $document]);
        Storage::disk('public')->assertMissing([$photo, $document]);

        $this->get("/admin/employees/{$employee->id}/photo")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $response = $this->get("/admin/employees/{$employee->id}/documents/0")->assertOk();
        $this->assertSame('%PDF-1.4 aadhaar', $response->streamedContent());
        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('aadhaar.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_the_file_routes_require_a_login(): void
    {
        $employee = $this->employeeWithFiles();

        $this->get("/admin/employees/{$employee->id}/photo")->assertRedirect('/admin/login');
        $this->get("/admin/employees/{$employee->id}/documents/0")->assertRedirect('/admin/login');
    }

    public function test_missing_files_and_paths_outside_the_upload_folders_return_404(): void
    {
        Storage::disk('public')->put('logos/site.png', 'logo');
        $employee = Employee::factory()->create(['field_data' => [
            'employee_image_path' => 'logos/site.png',
            'documents' => [
                ['type' => $this->idProof->id, 'file_path' => 'employee_documents_dynamic/gone.pdf', 'file_name' => 'gone.pdf'],
            ],
        ]]);

        $this->actingAs($this->admin);
        $this->get("/admin/employees/{$employee->id}/photo")->assertNotFound();
        $this->get("/admin/employees/{$employee->id}/documents/0")->assertNotFound();
        $this->get("/admin/employees/{$employee->id}/documents/7")->assertNotFound();
        $this->get("/admin/employees/{$employee->id}/documents/99999999999999999999")->assertNotFound();
    }

    public function test_a_directory_at_a_stored_path_returns_404(): void
    {
        Storage::disk('local')->makeDirectory('employee_images/dir.jpg');
        Storage::disk('public')->makeDirectory('employee_documents_dynamic/dir.pdf');
        $employee = Employee::factory()->create(['field_data' => [
            'employee_image_path' => 'employee_images/dir.jpg',
            'documents' => [
                ['type' => $this->idProof->id, 'file_path' => 'employee_documents_dynamic/dir.pdf', 'file_name' => 'dir.pdf'],
            ],
        ]]);

        $this->actingAs($this->admin);
        $this->get("/admin/employees/{$employee->id}/photo")->assertNotFound();
        $this->get("/admin/employees/{$employee->id}/documents/0")->assertNotFound();
    }

    public function test_a_crafted_stored_file_name_cannot_break_the_download(): void
    {
        Storage::disk('local')->put('employee_documents_dynamic/own.pdf', '%PDF-own');
        $employee = Employee::factory()->create(['field_data' => [
            'documents' => [
                ['type' => $this->idProof->id, 'file_path' => 'employee_documents_dynamic/own.pdf', 'file_name' => "..\\../evil\r\nname\u{202E}.pdf"],
                ['type' => $this->idProof->id, 'file_path' => 'employee_documents_dynamic/own.pdf', 'file_name' => '/\\'],
                ['type' => $this->idProof->id, 'file_path' => 'employee_documents_dynamic/own.pdf', 'file_name' => "\x07\t\u{200B}"],
            ],
        ]]);
        $this->actingAs($this->admin);

        $disposition = $this->get("/admin/employees/{$employee->id}/documents/0")->assertOk()->headers->get('Content-Disposition');
        $this->assertStringContainsString('evilname.pdf', $disposition);
        $this->assertStringNotContainsString('/', $disposition);
        $this->assertStringNotContainsString("\r", $disposition);

        // Nothing usable left: the stored file's own name.
        $this->assertStringContainsString('own.pdf', $this->get("/admin/employees/{$employee->id}/documents/1")->assertOk()->headers->get('Content-Disposition'));
        $this->assertStringContainsString('own.pdf', $this->get("/admin/employees/{$employee->id}/documents/2")->assertOk()->headers->get('Content-Disposition'));
    }

    public function test_files_uploaded_before_the_move_are_still_served_from_the_public_disk(): void
    {
        Storage::disk('public')->put('employee_documents_dynamic/old.pdf', '%PDF-legacy');
        $employee = Employee::factory()->create(['field_data' => [
            'documents' => [
                ['type' => $this->idProof->id, 'file_path' => 'employee_documents_dynamic/old.pdf', 'file_name' => 'old.pdf'],
            ],
        ]]);

        $response = $this->actingAs($this->admin)->get("/admin/employees/{$employee->id}/documents/0")->assertOk();
        $this->assertSame('%PDF-legacy', $response->streamedContent());
    }

    public function test_a_posted_existing_file_path_cannot_delete_another_file(): void
    {
        Storage::disk('public')->put('logos/site.png', 'logo');
        $employee = $this->employeeWithFiles();

        $this->actingAs($this->admin)->put("/admin/employees/{$employee->id}", $this->payload([
            'documents' => [[
                'type' => $this->idProof->id,
                'existing_file' => 'logos/site.png',
                'file' => UploadedFile::fake()->createWithContent('new.pdf', '%PDF-new'),
            ]],
        ]))->assertRedirect("/admin/employees/{$employee->id}")->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists('logos/site.png');

        $documents = $employee->fresh()->uploadedDocuments();
        $this->assertCount(1, $documents);
        $this->assertSame('new.pdf', $documents[0]['file_name']);
        Storage::disk('local')->assertExists($documents[0]['file_path']);
    }

    public function test_a_posted_existing_file_path_cannot_attach_another_employees_document(): void
    {
        Storage::disk('local')->put('employee_documents_dynamic/other.pdf', '%PDF-other');
        $employee = $this->employeeWithFiles();

        $this->actingAs($this->admin)->put("/admin/employees/{$employee->id}", $this->payload([
            'documents' => [[
                'type' => $this->idProof->id,
                'existing_file' => 'employee_documents_dynamic/other.pdf',
                'existing_file_name' => 'other.pdf',
            ]],
        ]))->assertSessionHasErrors('documents.0.file');

        $paths = array_column($employee->fresh()->uploadedDocuments(), 'file_path');
        $this->assertSame(['employee_documents_dynamic/own.pdf'], $paths);
        Storage::disk('local')->assertExists('employee_documents_dynamic/other.pdf');
    }

    public function test_an_existing_document_is_kept_or_replaced_by_its_stored_path(): void
    {
        $employee = $this->employeeWithFiles();

        // Kept as-is when no new file is uploaded; the name comes from the record.
        $this->actingAs($this->admin)->put("/admin/employees/{$employee->id}", $this->payload([
            'documents' => [[
                'type' => $this->idProof->id,
                'existing_file' => 'employee_documents_dynamic/own.pdf',
                'existing_file_name' => 'renamed-by-client.pdf',
            ]],
        ]))->assertSessionHasNoErrors();

        $documents = $employee->fresh()->uploadedDocuments();
        $this->assertSame('employee_documents_dynamic/own.pdf', $documents[0]['file_path']);
        $this->assertSame('own.pdf', $documents[0]['file_name']);

        // Replaced: the old private file is deleted once the new one is saved.
        $this->put("/admin/employees/{$employee->id}", $this->payload([
            'employee_image' => UploadedFile::fake()->create('new-photo.png', 20, 'image/png'),
            'documents' => [[
                'type' => $this->idProof->id,
                'existing_file' => 'employee_documents_dynamic/own.pdf',
                'file' => UploadedFile::fake()->createWithContent('renewed.pdf', '%PDF-renewed'),
            ]],
        ]))->assertSessionHasNoErrors();

        $employee->refresh();
        Storage::disk('local')->assertMissing(['employee_documents_dynamic/own.pdf', 'employee_images/asha.jpg']);
        Storage::disk('local')->assertExists([
            $employee->field_data['documents'][0]['file_path'],
            $employee->field_data['employee_image_path'],
        ]);
    }

    public function test_the_employee_pages_link_files_through_the_admin_routes(): void
    {
        $employee = $this->employeeWithFiles();
        $this->actingAs($this->admin);

        $this->get('/admin/employees')
            ->assertOk()
            ->assertSee(route('admin.employees.photo', $employee), false)
            ->assertSee('1 docs')
            ->assertDontSee('/storage/employee_', false);

        $this->get("/admin/employees/{$employee->id}")
            ->assertOk()
            ->assertSee('Aadhaar Card')
            ->assertSee('own.pdf')
            ->assertSee(route('admin.employees.documents.show', [$employee, 0]), false)
            ->assertDontSee('No documents uploaded');

        $this->get("/admin/employees/{$employee->id}/edit")
            ->assertOk()
            ->assertSee(route('admin.employees.photo', $employee), false)
            ->assertSee(route('admin.employees.documents.show', [$employee, 0]), false)
            ->assertDontSee('/storage/employee_', false);
    }

    public function test_the_move_command_moves_legacy_public_files_to_the_private_disk(): void
    {
        Storage::disk('public')->put('employee_images/old.jpg', 'old-photo');
        Storage::disk('public')->put('employee_documents_dynamic/old.pdf', '%PDF-old');
        Storage::disk('public')->put('logos/site.png', 'logo');
        $employee = Employee::factory()->create(['field_data' => [
            'employee_image_path' => 'employee_images/old.jpg',
            'documents' => [
                ['type' => $this->idProof->id, 'file_path' => 'employee_documents_dynamic/old.pdf', 'file_name' => 'old.pdf'],
                ['type' => $this->idProof->id, 'file_path' => 'logos/site.png', 'file_name' => 'site.png'],
            ],
        ]]);

        $this->artisan('employees:move-files-private', ['--dry-run' => true])->assertSuccessful();
        Storage::disk('public')->assertExists('employee_documents_dynamic/old.pdf');

        $this->artisan('employees:move-files-private')->assertSuccessful();

        Storage::disk('local')->assertExists(['employee_images/old.jpg', 'employee_documents_dynamic/old.pdf']);
        Storage::disk('public')->assertMissing(['employee_images/old.jpg', 'employee_documents_dynamic/old.pdf']);
        Storage::disk('public')->assertExists('logos/site.png');
        $this->assertSame('%PDF-old', Storage::disk('local')->get('employee_documents_dynamic/old.pdf'));

        $response = $this->actingAs($this->admin)->get("/admin/employees/{$employee->id}/documents/0")->assertOk();
        $this->assertSame('%PDF-old', $response->streamedContent());
    }

    private function employeeWithLegacyDocument(): Employee
    {
        Storage::disk('public')->put('employee_documents_dynamic/old.pdf', '%PDF-old-and-complete');

        return Employee::factory()->create(['field_data' => [
            'documents' => [
                ['type' => $this->idProof->id, 'file_path' => 'employee_documents_dynamic/old.pdf', 'file_name' => 'old.pdf'],
            ],
        ]]);
    }

    public function test_the_move_command_replaces_a_truncated_private_copy_from_the_public_file(): void
    {
        $this->employeeWithLegacyDocument();
        // Left behind by an interrupted earlier run.
        Storage::disk('local')->put('employee_documents_dynamic/old.pdf', '%PDF-ol');

        $this->artisan('employees:move-files-private')->assertSuccessful();

        $this->assertSame('%PDF-old-and-complete', Storage::disk('local')->get('employee_documents_dynamic/old.pdf'));
        Storage::disk('public')->assertMissing('employee_documents_dynamic/old.pdf');
        Storage::disk('local')->assertMissing('employee_documents_dynamic/old.pdf.part');
    }

    public function test_the_move_command_removes_a_public_duplicate_of_a_complete_private_copy(): void
    {
        $this->employeeWithLegacyDocument();
        Storage::disk('local')->put('employee_documents_dynamic/old.pdf', '%PDF-old-and-complete');

        $this->artisan('employees:move-files-private', ['--dry-run' => true])->assertSuccessful();
        Storage::disk('public')->assertExists('employee_documents_dynamic/old.pdf');

        $this->artisan('employees:move-files-private')->assertSuccessful();

        Storage::disk('public')->assertMissing('employee_documents_dynamic/old.pdf');
        $this->assertSame('%PDF-old-and-complete', Storage::disk('local')->get('employee_documents_dynamic/old.pdf'));
    }

    public function test_a_failed_copy_leaves_nothing_on_the_private_disk_and_keeps_the_public_file(): void
    {
        $employee = $this->employeeWithLegacyDocument();

        // A private disk that runs out of space part-way through every write.
        $fake = Storage::disk('local');
        Storage::set('local', new class($fake->getDriver(), $fake->getAdapter(), $fake->getConfig()) extends FilesystemAdapter
        {
            public function writeStream($path, $resource, array $options = [])
            {
                return $this->put($path, substr(stream_get_contents($resource), 0, 5));
            }
        });

        $this->artisan('employees:move-files-private')->assertFailed();

        Storage::disk('local')->assertMissing(['employee_documents_dynamic/old.pdf', 'employee_documents_dynamic/old.pdf.part']);
        $this->assertSame('%PDF-old-and-complete', Storage::disk('public')->get('employee_documents_dynamic/old.pdf'));

        // Admins still get the intact file.
        $response = $this->actingAs($this->admin)->get("/admin/employees/{$employee->id}/documents/0")->assertOk();
        $this->assertSame('%PDF-old-and-complete', $response->streamedContent());
    }
}
