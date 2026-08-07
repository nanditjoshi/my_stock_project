<?php

namespace Tests\Feature;

use App\Http\Controllers\CsvImportController;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CsvImportFeatureTest extends \Tests\TestCase
{
    public function test_csv_import_creates_table_from_first_row_headers_and_infers_column_types(): void
    {
        $table = 'csv_auto_test_' . uniqid();

        Schema::dropIfExists($table);

        $file = UploadedFile::fake()->createWithContent(
            'sample.csv',
            "name,age,amount,active,created_at\nAlice,25,100.50,true,2026-01-01\nBob,30,75.25,false,2026-01-02\n"
        );

        $request = new Request();
        $request->setMethod('POST');
        $request->request->add([
            'mode' => 'new',
            'new_table_name' => $table,
        ]);
        $request->files->add([
            'file' => $file,
        ]);

        $controller = new CsvImportController();
        $response = $controller->store($request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue(Schema::hasTable($table));
        $this->assertEquals(2, DB::table($table)->count());

        $columns = DB::select('SHOW COLUMNS FROM ' . $table);
        $columnTypes = [];
        $columnDefaults = [];

        foreach ($columns as $column) {
            $columnTypes[$column->Field] = $column->Type;
            $columnDefaults[$column->Field] = $column->Default;
        }

        $this->assertStringContainsString('varchar', $columnTypes['name']);
        $this->assertStringContainsString('int', $columnTypes['age']);
        $this->assertStringContainsString('double', $columnTypes['amount']);
        $this->assertStringContainsString('tinyint', $columnTypes['active']);
        $this->assertStringContainsString('date', $columnTypes['created_at']);
        $this->assertTrue(array_key_exists('created_at', $columnTypes));

        Schema::dropIfExists($table);
    }
}
