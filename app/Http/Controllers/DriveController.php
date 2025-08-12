<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DriveController extends Controller
{
    public function index($pic_name)
    {
        return $this->browse($pic_name);
    }

    public function browse($pic_name, $any = null)
    {
        $basePath = 'files/' . $pic_name;

        // Cek apakah folder root PIC sudah ada, jika belum maka buat berdasarkan API
        if (!Storage::exists($basePath)) {
            $matched = $this->createFoldersFromApi($pic_name);

            // Jika tidak ada officer yang cocok, abort 403 atau redirect
            if (!$matched) {
                abort(403, "PIC '{$pic_name}' tidak ditemukan di data API.");
            }
        }

        $path = $basePath . ($any ? '/' . $any : '');

        $this->logRecentAccess($pic_name, Str::after($path, 'files/' . $pic_name . '/'));

        $items = Storage::files($path);
        $folders = Storage::directories($path);

        // Ambil data favorit dan recent
        $favorites = File::exists(storage_path("app/favorites_{$pic_name}.json"))
            ? json_decode(File::get(storage_path("app/favorites_{$pic_name}.json")), true)
            : [];

        $recents = File::exists(storage_path("app/recent_{$pic_name}.json"))
            ? json_decode(File::get(storage_path("app/recent_{$pic_name}.json")), true)
            : [];

        return view('drive.index', compact('items', 'folders', 'pic_name', 'path', 'favorites', 'recents'));
    }

    public function editExcel(Request $request)
    {
        $fullPath = storage_path('app/' . $request->input('file_path'));
        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', $request->input('new_value'));
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($fullPath);

        return back()->with('success', 'Excel berhasil diedit.');
    }

    private function createFoldersFromApi($pic_name)
    {
        try {
            $response = Http::get('http://ppiapps.sytes.net:8000/api/member');

            if (!$response->successful()) {
                Log::error('Gagal akses API member');
                return false;
            }

            $data = $response->json();

            $matched = false;

            foreach ($data as $item) {
                if (strtolower($item['officer']) !== strtolower($pic_name)) continue;

                $matched = true;

                $prov = Str::slug($item['provinsi'], '_');
                $city = Str::slug($item['kota'], '_');
                $customer = Str::slug($item['name'], '_');

                $folderPath = "files/{$pic_name}/{$prov}/{$city}/{$customer}";

                if (!Storage::exists($folderPath)) {
                    Storage::makeDirectory($folderPath);
                }
            }

            return $matched;
        } catch (\Exception $e) {
            dd($e);
            Log::error("Gagal create folder dari API: " . $e->getMessage());
            return false;
        }
    }

    public function toggleFavorite(Request $request)
    {
        $pic = $request->input('pic_name');
        $path = $request->input('folder_path');

        $file = storage_path("app/favorites_{$pic}.json");
        $favorites = File::exists($file) ? json_decode(File::get($file), true) : [];

        if (($key = array_search($path, $favorites)) !== false) {
            unset($favorites[$key]);
        } else {
            $favorites[] = $path;
        }

        File::put($file, json_encode(array_values($favorites)));

        return back()->with('success', 'Perubahan favorit berhasil.');
    }

    protected function logRecentAccess($pic_name, $path)
    {
        $filePath = storage_path("app/recent_{$pic_name}.json");

        $recent = file_exists($filePath)
            ? json_decode(file_get_contents($filePath), true)
            : [];

        if (($key = array_search($path, $recent)) !== false) {
            unset($recent[$key]);
        }

        array_unshift($recent, $path);
        $recent = array_slice($recent, 0, 10);

        file_put_contents($filePath, json_encode(array_values($recent)));
    }

    public function uploadFile(Request $request, $pic_name)
    {
        $request->validate([
            'upload_file' => 'required|file|mimes:xlsx',
            'path' => 'required|string'
        ]);

        $file = $request->file('upload_file');
        $path = $request->input('path');

        $filename = $file->getClientOriginalName();
        $storagePath = $path . '/' . $filename;

        Storage::putFileAs($path, $file, $filename);

        $this->logUploadTime($pic_name, Str::after($storagePath, "files/$pic_name/"));

        return back()->with('success', 'File berhasil diupload.');
    }

    protected function logUploadTime($pic_name, $relativePath)
    {
        $file = storage_path("app/uploads_{$pic_name}.json");
        $uploads = File::exists($file) ? json_decode(File::get($file), true) : [];

        $uploads[$relativePath] = now()->toDateTimeString();

        File::put($file, json_encode($uploads));
    }

    public function viewExcel($pic_name, $file)
    {
        $file = urldecode($file);
        $full = storage_path("app/files/{$pic_name}/{$file}");
        if (!file_exists($full)) abort(404);

        $reader = IOFactory::createReaderForFile($full);
        $spreadsheet = $reader->load($full);

        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            $rows = $sheet->toArray(null, true, true, true);
            $celldata = [];
            foreach ($rows as $r => $row) {
                foreach ($row as $c => $v) {
                    $colIndex = Coordinate::columnIndexFromString($c);
                    $celldata[] = [
                        'r' => $r - 1,
                        'c' => $colIndex - 1,
                        'v' => ['v' => $v],
                    ];
                }
            }

            $sheets[] = [
                'name' => $sheet->getTitle(),
                'celldata' => $celldata,
            ];
        }

        $export = $this->excelToLuckysheet($full);

        return view('drive.excel_view', compact('file', 'pic_name', 'export', 'sheets'));
    }

    public function updateExcel(Request $r, $pic_name)
    {
        Log::info("Memulai updateExcel untuk PIC: {$pic_name}, file: {$r->input('file')}");
        Log::info("Data yang diterima:", $r->all());

        $allSheetsData = $r->input('sheets');
        $file = $r->input('file');
        $full = storage_path("app/files/{$pic_name}/{$file}");

        if (!$allSheetsData || !is_array($allSheetsData) || !file_exists($full)) {
            Log::error('Payload tidak valid atau file tidak ditemukan.', ['payload' => $r->all()]);
            return response()->json(['error' => 'Invalid payload or file not found.'], 400);
        }

        try {
            $reader = IOFactory::createReaderForFile($full);
            $spreadsheet = $reader->load($full);
        } catch (\Exception $e) {
            Log::error('Gagal memuat spreadsheet: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load spreadsheet: ' . $e->getMessage()], 500);
        }

        while ($spreadsheet->getSheetCount() > 0) {
            $spreadsheet->removeSheetByIndex(0);
        }

        foreach ($allSheetsData as $sheetIndex => $sheetData) {
            $sheetName = $sheetData['name'] ?? 'Sheet' . ($sheetIndex + 1);
            $celldata = $sheetData['celldata'] ?? [];
            $config = $sheetData['config'] ?? [];

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);

            if (isset($config['columnlen'])) {
                foreach ($config['columnlen'] as $colIndex => $width) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex + 1))->setWidth($width / 7.5);
                }
            }
            if (isset($config['rowlen'])) {
                foreach ($config['rowlen'] as $rowIndex => $height) {
                    $sheet->getRowDimension($rowIndex + 1)->setRowHeight($height);
                }
            }

            foreach ($celldata as $cell) {
                $r_coord = $cell['r'] ?? 0;
                $c_coord = $cell['c'] ?? 0;
                $value_obj = $cell['v'] ?? null;

                if (is_numeric($r_coord) && is_numeric($c_coord) && $value_obj !== null) {
                    $col = Coordinate::stringFromColumnIndex($c_coord + 1);
                    $row = $r_coord + 1;
                    $coord = "{$col}{$row}";
                    $cellStyle = $sheet->getStyle($coord);

                    // PENTING: Perbaikan ini mengatasi error sebelumnya dan memastikan nilai yang valid
                    $cellValue = '';
                    if (is_array($value_obj) && isset($value_obj['v'])) {
                        $cellValue = $value_obj['v'];
                    } else if (!is_array($value_obj)) {
                        $cellValue = $value_obj;
                    }

                    $sheet->setCellValue($coord, $cellValue);

                    if (is_array($value_obj)) {
                        if (isset($value_obj['bl']) && $value_obj['bl']) {
                            $cellStyle->getFont()->setBold(true);
                        }
                        if (isset($value_obj['it']) && $value_obj['it']) {
                            $cellStyle->getFont()->setItalic(true);
                        }
                        if (isset($value_obj['ff'])) {
                            $cellStyle->getFont()->setName($value_obj['ff']);
                        }
                        if (isset($value_obj['fs'])) {
                            $cellStyle->getFont()->setSize($value_obj['fs']);
                        }
                        if (isset($value_obj['fc'])) {
                            $cellStyle->getFont()->getColor()->setARGB(ltrim($value_obj['fc'], '#'));
                        }
                        if (isset($value_obj['bg'])) {
                            $cellStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(ltrim($value_obj['bg'], '#'));
                        }
                        if (isset($value_obj['vt'])) {
                             $alignmentMap = [0 => Alignment::VERTICAL_TOP, 1 => Alignment::VERTICAL_CENTER, 2 => Alignment::VERTICAL_BOTTOM];
                             if (isset($alignmentMap[$value_obj['vt']])) {
                                $cellStyle->getAlignment()->setVertical($alignmentMap[$value_obj['vt']]);
                             }
                        }
                        if (isset($value_obj['ht'])) {
                             $alignmentMap = [0 => Alignment::HORIZONTAL_LEFT, 1 => Alignment::HORIZONTAL_CENTER, 2 => Alignment::HORIZONTAL_RIGHT];
                             if (isset($alignmentMap[$value_obj['ht']])) {
                                $cellStyle->getAlignment()->setHorizontal($alignmentMap[$value_obj['ht']]);
                             }
                        }
                    }
                }
            }

            if (isset($config['merge'])) {
                foreach ($config['merge'] as $mergeRange) {
                    $sheet->mergeCells($mergeRange);
                }
            }

            if (isset($config['borderInfo'])) {
                foreach ($config['borderInfo'] as $borderSet) {
                    $borderType = $borderSet['borderType'] ?? null;
                    $borderColor = ltrim($borderSet['color'] ?? '000000', '#');
                    $borderStyle = $borderSet['style'] ?? 1;

                    // PERBAIKAN UTAMA: Memastikan range adalah string sebelum digunakan
                    if (isset($borderSet['range']) && is_array($borderSet['range'])) {
                        foreach ($borderSet['range'] as $rangeData) {
                            if (is_array($rangeData) && isset($rangeData['row']) && isset($rangeData['column'])) {
                                $startRow = $rangeData['row'][0] + 1;
                                $endRow = $rangeData['row'][1] + 1;
                                $startCol = Coordinate::stringFromColumnIndex($rangeData['column'][0] + 1);
                                $endCol = Coordinate::stringFromColumnIndex($rangeData['column'][1] + 1);
                                $range = "{$startCol}{$startRow}:{$endCol}{$endRow}";

                                if ($borderType) {
                                    $phpSpreadsheetBorderStyle = $this->convertLuckysheetBorderStyleToPhpSpreadsheet($borderStyle);
                                    $style = $sheet->getStyle($range);
                                    $borders = $style->getBorders();
            
                                    switch ($borderType) {
                                        case 'all':
                                            $borders->getAllBorders()->setBorderStyle($phpSpreadsheetBorderStyle)->setColor(new Color($borderColor));
                                            break;
                                        case 'top':
                                            $borders->getTop()->setBorderStyle($phpSpreadsheetBorderStyle)->setColor(new Color($borderColor));
                                            break;
                                        case 'bottom':
                                            $borders->getBottom()->setBorderStyle($phpSpreadsheetBorderStyle)->setColor(new Color($borderColor));
                                            break;
                                        case 'left':
                                            $borders->getLeft()->setBorderStyle($phpSpreadsheetBorderStyle)->setColor(new Color($borderColor));
                                            break;
                                        case 'right':
                                            $borders->getRight()->setBorderStyle($phpSpreadsheetBorderStyle)->setColor(new Color($borderColor));
                                            break;
                                        case 'outer':
                                            $borders->getOutline()->setBorderStyle($phpSpreadsheetBorderStyle)->setColor(new Color($borderColor));
                                            break;
                                        case 'innerHorizontal':
                                            $borders->getInsideHorizontal()->setBorderStyle($phpSpreadsheetBorderStyle)->setColor(new Color($borderColor));
                                            break;
                                        case 'innerVertical':
                                            $borders->getInsideVertical()->setBorderStyle($phpSpreadsheetBorderStyle)->setColor(new Color($borderColor));
                                            break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if ($spreadsheet->getSheetCount() > 1 && $spreadsheet->getSheet(0)->getHighestRow() == 0 && $spreadsheet->getSheet(0)->getHighestColumn() == 'A') {
             $spreadsheet->removeSheetByIndex(0);
        } else if ($spreadsheet->getSheetCount() > 1 && $spreadsheet->getSheet(0)->getHighestRow() == 1 && $spreadsheet->getSheet(0)->getCell('A1')->getValue() === null) {
            $spreadsheet->removeSheetByIndex(0);
        }

        try {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($full);
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan spreadsheet: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save spreadsheet: ' . $e->getMessage()], 500);
        }

        Log::info("Update file berhasil.");
        return response()->json(['success' => true, 'message' => 'File berhasil diperbarui!']);
    }

    private function convertLuckysheetBorderStyleToPhpSpreadsheet($luckysheetStyle)
    {
        switch ($luckysheetStyle) {
            case 1: return Border::BORDER_THIN;
            case 2: return Border::BORDER_MEDIUM;
            case 3: return Border::BORDER_THICK;
            case 4: return Border::BORDER_DOTTED;
            case 5: return Border::BORDER_DASHED;
            case 6: return Border::BORDER_DASHDOT;
            case 7: return Border::BORDER_DASHDOTDOT;
            case 8: return Border::BORDER_DOUBLE;
            case 9: return Border::BORDER_HAIR;
            case 10: return Border::BORDER_MEDIUMDASHED;
            case 11: return Border::BORDER_MEDIUMDASHDOT;
            case 12: return Border::BORDER_SLANTDASHDOT;
            default: return Border::BORDER_NONE;
        }
    }

    private function excelToLuckysheet($filePath)
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheets = $spreadsheet->getAllSheets();
        $sheetsData = [];

        foreach ($worksheets as $sheet) {
            $celldata = [];
            $maxRow = $sheet->getHighestRow();
            $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

            for ($i = 1; $i <= $maxRow; $i++) {
                for ($j = 1; $j <= $maxCol; $j++) {
                    $cell = $sheet->getCellByColumnAndRow($j, $i);
                    $value = $cell->getValue();
                    if ($value !== null) {
                        $celldata[] = [
                            'r' => $i - 1,
                            'c' => $j - 1,
                            'v' => ['v' => $value],
                        ];
                    }
                }
            }

            $sheetsData[] = [
                'name' => $sheet->getTitle(),
                'celldata' => $celldata,
            ];
        }

        return $sheetsData;
    }

    public function apiViewExcelAuto(Request $request)
    {
        $token = $request->header('Authorization');
        if ($token !== env('DRIVE_API_TOKEN')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'pic' => 'required|string',
            'prov' => 'required|string',
            'kota' => 'required|string',
            'customer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Parameter tidak lengkap atau salah', 'details' => $validator->errors()], 422);
        }

        $pic = $request->pic;
        $prov = $request->prov;
        $kota = $request->kota;
        $customer = $request->customer;

        $folderPath = storage_path("app/files/{$pic}/{$prov}/{$kota}/{$customer}");

        if (!is_dir($folderPath)) {
            return response()->json(['error' => 'Folder tidak ditemukan'], 404);
        }

        $files = glob($folderPath . '/*.xlsx');

        if (empty($files)) {
            return response()->json(['error' => 'Tidak ada file Excel ditemukan'], 404);
        }

        $firstFile = $files[0];

        try {
            $spreadsheet = IOFactory::load($firstFile);
            $sheetsData = [];

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $rows = $sheet->toArray();
                $sheetsData[] = [
                    'name' => $sheet->getTitle(),
                    'data' => $rows
                ];
            }

            return response()->json($sheetsData);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal membaca file Excel',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
