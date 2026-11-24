<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * Employee photos and KYC documents used to be stored on the public disk,
 * where /storage/... serves them to anyone holding the URL. This moves them
 * to the private disk. Paths are kept as they are (both disks resolve the
 * same relative path), so field_data needs no rewrite; the app looks on the
 * private disk first and falls back to the public one until this has run.
 */
class MoveEmployeeFilesToPrivateDisk extends Command
{
    protected $signature = 'employees:move-files-private {--dry-run : Only report what would be moved}';

    protected $description = 'Move employee photos and documents from the public disk to the private disk';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $private = Storage::disk(Employee::FILE_DISK);
        $public = Storage::disk(Employee::LEGACY_FILE_DISK);
        $counts = ['moved' => 0, 'already_private' => 0, 'duplicates_removed' => 0, 'missing' => 0, 'failed' => 0];

        Employee::withTrashed()->orderBy('id')->chunkById(100, function ($employees) use ($dryRun, $private, $public, &$counts) {
            foreach ($employees as $employee) {
                foreach ($this->filesOf($employee) as $path) {
                    $inPrivate = $private->fileExists($path);
                    $inPublic = $public->fileExists($path);

                    if (! $inPublic) {
                        if ($inPrivate) {
                            $counts['already_private']++;
                        } else {
                            $counts['missing']++;
                            $this->warn("Employee #{$employee->id}: {$path} not found on either disk.");
                        }

                        continue;
                    }

                    // On both disks: a complete private copy means only the
                    // world-readable duplicate is left to remove. A private
                    // copy of another size is incomplete (an earlier run was
                    // interrupted) and is copied again from the public file.
                    if ($inPrivate && $this->sameSize($private, $public, $path)) {
                        $counts['duplicates_removed']++;
                        if ($dryRun) {
                            $this->line("Would remove the public duplicate of {$path} (employee #{$employee->id}).");
                        } else {
                            $public->delete($path);
                        }

                        continue;
                    }

                    if ($dryRun) {
                        $counts['moved']++;
                        $this->line("Would move {$path} (employee #{$employee->id}).");

                        continue;
                    }

                    if (! $this->copyToPrivate($private, $public, $path)) {
                        $counts['failed']++;
                        $this->error("Employee #{$employee->id}: could not copy {$path}; left on the public disk.");

                        continue;
                    }

                    $public->delete($path);
                    $counts['moved']++;
                }
            }
        });

        $this->info(($dryRun ? 'Would move' : 'Moved')." {$counts['moved']} file(s); {$counts['already_private']} already private, "
            .($dryRun ? 'would remove ' : 'removed ')."{$counts['duplicates_removed']} public duplicate(s), {$counts['missing']} missing, {$counts['failed']} failed.");

        return $counts['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Copy a public file to the same path on the private disk.
     *
     * The app reads the private disk first, so a partial file there would
     * hide the intact public one. The copy is therefore written beside the
     * target and only renamed into place once its size matches; on any
     * failure nothing is left on the private disk at that path.
     */
    private function copyToPrivate(FilesystemAdapter $private, FilesystemAdapter $public, string $path): bool
    {
        $partial = $path.'.part';

        $stream = $public->readStream($path);
        $written = $stream && $private->writeStream($partial, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $written || ! $this->sameSize($private, $public, $path, $partial) || ! $private->move($partial, $path)) {
            $private->delete($partial);

            return false;
        }

        if (! $this->sameSize($private, $public, $path)) {
            $private->delete($path);

            return false;
        }

        return true;
    }

    private function sameSize(FilesystemAdapter $private, FilesystemAdapter $public, string $path, ?string $privatePath = null): bool
    {
        $privatePath ??= $path;

        return $private->fileExists($privatePath) && $private->size($privatePath) === $public->size($path);
    }

    /**
     * Upload paths stored for the employee, limited to the employee upload folders.
     */
    private function filesOf(Employee $employee): array
    {
        $data = is_array($employee->field_data) ? $employee->field_data : [];
        $candidates = [[$data['employee_image_path'] ?? null, Employee::PHOTO_DIRECTORY]];

        foreach ($employee->uploadedDocuments() as $document) {
            $candidates[] = [$document['file_path'] ?? null, Employee::DOCUMENT_DIRECTORY];
        }

        $paths = [];
        foreach ($candidates as [$path, $directory]) {
            if (Employee::isUploadPath($path, $directory)) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }
}
