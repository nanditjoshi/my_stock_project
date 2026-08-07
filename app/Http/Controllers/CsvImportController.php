<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CsvImportController extends Controller
{
    public function index()
    {
        $tables = $this->getTables();

        return view('csv-import', compact('tables'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $mode = $request->input('mode', 'existing');
        $table = $request->input('selected_table');
        $newTable = $request->input('new_table_name');

        $file = $request->file('file');
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return Redirect::back()->withErrors(['file' => 'Unable to open CSV file.']);
        }

        $headers = fgetcsv($handle);
        if ($headers === false || count($headers) === 0) {
            fclose($handle);
            return Redirect::back()->withErrors(['file' => 'CSV file is empty or invalid.']);
        }

        $headers = array_map(function ($value) {
            return trim($value);
        }, $headers);

        $normalizedHeaders = array_values(array_filter($headers, function ($value) {
            return $value !== '';
        }));

        if (count($normalizedHeaders) === 0) {
            fclose($handle);
            return Redirect::back()->withErrors(['file' => 'CSV file has no usable columns.']);
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 0 || (count($row) === 1 && trim($row[0]) === '')) {
                continue;
            }

            $row = array_map(function ($value) {
                return $value === null ? null : trim($value);
            }, $row);

            $rows[] = $row;
        }

        $columnTypes = $this->detectColumnTypes($normalizedHeaders, $rows);

        if ($mode === 'new' && $newTable) {
            $table = $this->normalizeTableName($newTable);

            if (Schema::hasTable($table)) {
                fclose($handle);
                return Redirect::back()->withErrors(['new_table_name' => 'Table already exists. Please choose another name or use existing table mode.']);
            }

            Schema::create($table, function (Blueprint $tableBlueprint) use ($normalizedHeaders, $columnTypes) {
                foreach ($normalizedHeaders as $index => $column) {
                    $columnName = $this->normalizeColumnName($column);

                    if ($columnName === 'created_at') {
                        continue;
                    }

                    $columnType = $columnTypes[$index] ?? 'string';

                    switch ($columnType) {
                        case 'integer':
                            $tableBlueprint->integer($columnName)->nullable();
                            break;
                        case 'double':
                            $tableBlueprint->double($columnName)->nullable();
                            break;
                        case 'boolean':
                            $tableBlueprint->boolean($columnName)->nullable();
                            break;
                        case 'date':
                            $tableBlueprint->date($columnName)->nullable();
                            break;
                        case 'datetime':
                            $tableBlueprint->dateTime($columnName)->nullable();
                            break;
                        default:
                            $tableBlueprint->string($columnName)->nullable();
                            break;
                    }
                }

                $tableBlueprint->date('created_at')->useCurrent()->nullable();
            });
        } else {
            $table = $request->input('selected_table');

            if (!$table || !Schema::hasTable($table)) {
                return Redirect::back()->withErrors(['selected_table' => 'Please select a valid existing table.']);
            }
        }

        $existingColumns = Schema::getColumnListing($table);
        $rowCount = 0;

        foreach ($rows as $row) {
            $rowData = [];
            foreach ($normalizedHeaders as $index => $column) {
                $columnName = $this->normalizeColumnName($column);

                if ($columnName === 'created_at') {
                    continue;
                }

                if (in_array($columnName, $existingColumns, true)) {
                    $rowData[$columnName] = $this->castValueByType($row[$index] ?? null, $columnTypes[$index] ?? 'string');
                }
            }

            if (!empty($rowData)) {
                DB::table($table)->insert($rowData);
                $rowCount++;
            }
        }

        return Redirect::back()->with('success', 'CSV imported successfully into table ' . $table . '. Records inserted: ' . $rowCount);
    }

    protected function getTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $list = [];

        foreach ($tables as $table) {
            $value = (array) $table;
            $name = reset($value);
            $list[] = $name;
        }

        return $list;
    }

    protected function detectColumnTypes(array $headers, array $rows): array
    {
        $types = [];

        foreach ($headers as $index => $header) {
            $columnValues = [];
            foreach ($rows as $row) {
                $columnValues[] = $row[$index] ?? null;
            }

            $types[$index] = $this->detectTypeForValues($columnValues);
        }

        return $types;
    }

    protected function detectTypeForValues(array $values): string
    {
        $cleanValues = array_values(array_filter(array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $values), function ($value) {
            return $value !== null && $value !== '';
        }));

        if (count($cleanValues) === 0) {
            return 'string';
        }

        $allIntegers = true;
        $allDoubles = true;
        $allBooleans = true;
        $allDates = true;
        $allDateTimes = true;

        foreach ($cleanValues as $value) {
            $stringValue = (string) $value;

            if (!preg_match('/^-?\d+$/', $stringValue)) {
                $allIntegers = false;
            }

            if (!preg_match('/^-?\d+(\.\d+)?$/', $stringValue)) {
                $allDoubles = false;
            }

            if (!in_array(strtolower($stringValue), ['true', 'false', '1', '0', 'yes', 'no', 'y', 'n'], true)) {
                $allBooleans = false;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $stringValue)) {
                $allDates = false;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $stringValue)) {
                $allDateTimes = false;
            }
        }

        if ($allIntegers) {
            return 'integer';
        }

        if ($allDoubles) {
            return 'double';
        }

        if ($allBooleans) {
            return 'boolean';
        }

        if ($allDates) {
            return 'date';
        }

        if ($allDateTimes) {
            return 'datetime';
        }

        return 'string';
    }

    protected function castValueByType($value, string $type)
    {
        if ($value === null || $value === '') {
            return null;
        }

        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'double':
                return (float) $value;
            case 'boolean':
                return in_array(strtolower(trim((string) $value)), ['true', '1', 'yes', 'y'], true) ? 1 : 0;
            case 'date':
                return $value;
            case 'datetime':
                return $value;
            default:
                return (string) $value;
        }
    }

    protected function normalizeTableName(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_]+/', '_', $value);
        $value = trim($value, '_');

        return $value !== '' ? strtolower($value) : 'csv_import_table';
    }

    protected function normalizeColumnName(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_]+/', '_', $value);
        $value = trim($value, '_');
        $value = strtolower($value);

        return $value !== '' ? $value : 'column';
    }
}
