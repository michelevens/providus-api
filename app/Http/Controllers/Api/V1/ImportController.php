<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BulkImport;
use App\Models\Facility;
use App\Models\License;
use App\Models\Organization;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    // List imports
    public function index(Request $request): JsonResponse
    {
        $imports = BulkImport::where('agency_id', $request->user()->agency_id)
            ->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $imports]);
    }

    // Preview CSV data
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'import_type' => 'required|in:providers,organizations,licenses,facilities',
            'data' => 'required|array|min:1',
            'data.*.0' => 'required', // at least first column
        ]);

        $rows = $request->input('data');
        $headers = $rows[0] ?? [];
        $dataRows = array_slice($rows, 1);

        // Auto-detect column mapping
        $mapping = $this->autoMapColumns($request->import_type, $headers);

        // Preview first 10 rows with mapping applied
        $preview = [];
        foreach (array_slice($dataRows, 0, 10) as $row) {
            $mapped = [];
            foreach ($mapping as $field => $colIndex) {
                if ($colIndex !== null && isset($row[$colIndex])) {
                    $mapped[$field] = trim($row[$colIndex]);
                }
            }
            $preview[] = $mapped;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'headers' => $headers,
                'mapping' => $mapping,
                'preview' => $preview,
                'total_rows' => count($dataRows),
                'available_fields' => $this->getFieldsForType($request->import_type),
            ],
        ]);
    }

    // Execute import
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'import_type' => 'required|in:providers,organizations,licenses,facilities',
            'data' => 'required|array|min:2', // headers + at least 1 row
            'column_mapping' => 'required|array',
        ]);

        $rows = $request->input('data');
        $headers = $rows[0];
        $dataRows = array_slice($rows, 1);
        $mapping = $request->input('column_mapping');
        $agencyId = $request->user()->agency_id;

        $import = BulkImport::create([
            'agency_id' => $agencyId,
            'import_type' => $request->import_type,
            'file_name' => $request->input('file_name', 'import.csv'),
            'status' => 'processing',
            'total_rows' => count($dataRows),
            'column_mapping' => $mapping,
            'created_by' => $request->user()->id,
        ]);

        $errors = [];
        $success = 0;
        $skipped = 0;

        foreach ($dataRows as $i => $row) {
            try {
                $mapped = [];
                foreach ($mapping as $field => $colIndex) {
                    if ($colIndex !== null && isset($row[$colIndex])) {
                        $val = trim($row[$colIndex]);
                        if ($val !== '') $mapped[$field] = $val;
                    }
                }

                if (empty($mapped)) { $skipped++; continue; }

                $this->importRow($request->import_type, $mapped, $agencyId);
                $success++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $i + 2, 'error' => $e->getMessage(), 'data' => $row];
            }
        }

        $import->update([
            'status' => 'completed',
            'processed_rows' => count($dataRows),
            'success_count' => $success,
            'error_count' => count($errors),
            'skip_count' => $skipped,
            'errors' => $errors ?: null,
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $import->fresh()]);
    }

    private function importRow(string $type, array $data, int $agencyId): void
    {
        $data['agency_id'] = $agencyId;

        match ($type) {
            'providers' => Provider::create($data),
            'organizations' => Organization::create($data),
            'licenses' => License::create($data),
            'facilities' => Facility::create($data),
        };
    }

    private function autoMapColumns(string $type, array $headers): array
    {
        $fields = $this->getFieldsForType($type);
        $mapping = [];
        $lowerHeaders = array_map('strtolower', array_map('trim', $headers));

        foreach ($fields as $field) {
            $mapping[$field] = null;
            $fieldLower = strtolower($field);
            $fieldClean = str_replace('_', ' ', $fieldLower);
            $fieldCamel = lcfirst(str_replace('_', '', ucwords($field, '_')));

            foreach ($lowerHeaders as $i => $header) {
                $headerClean = str_replace(['-', '_', '.'], ' ', $header);
                if ($header === $fieldLower || $headerClean === $fieldClean
                    || strtolower($fieldCamel) === $header
                    || str_contains($headerClean, $fieldClean)
                    || str_contains($fieldClean, $headerClean)) {
                    $mapping[$field] = $i;
                    break;
                }
            }
        }

        return $mapping;
    }

    private function getFieldsForType(string $type): array
    {
        return match ($type) {
            'providers' => ['first_name', 'last_name', 'credentials', 'npi', 'taxonomy', 'specialty', 'email', 'phone', 'caqh_id', 'organization_id'],
            'organizations' => ['name', 'npi', 'tax_id', 'street', 'city', 'state', 'zip', 'phone', 'email', 'taxonomy'],
            'licenses' => ['provider_id', 'state', 'license_number', 'license_type', 'status', 'issue_date', 'expiration_date'],
            'facilities' => ['name', 'npi', 'facility_type', 'tax_id', 'street', 'city', 'state', 'zip', 'phone', 'email'],
            default => [],
        };
    }
}
