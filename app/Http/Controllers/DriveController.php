<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

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
                \Log::error('Gagal akses API member');
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
            \Log::error("Gagal create folder dari API: " . $e->getMessage());
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

        // Jangan duplikat
        if (($key = array_search($path, $recent)) !== false) {
            unset($recent[$key]);
        }

        array_unshift($recent, $path); // Masukkan paling atas
        $recent = array_slice($recent, 0, 10); // Maks 10 entri

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

        // Simpan file ke storage
        Storage::putFileAs($path, $file, $filename);

        // (Opsional) Simpan waktu upload untuk fitur “Upload Terakhir”
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

    // public function viewExcel2($pic_name, $file)
    // {
    //     \Log::info("viewExcel called with:", [
    //         'pic_name' => $pic_name,
    //         'file' => $file,
    //         'full_path' => storage_path("app/files/{$pic_name}/{$file}")
    //     ]);

    //     $file = urldecode($file);
    //     $path = storage_path("app/files/{$pic_name}/{$file}");

    //     if (!file_exists($path)) {
    //         abort(404, "File not found at: $path");
    //     }

    //     $luckysheetData = $this->excelToLuckysheet($path);
    //     return view('drive.excel_view', compact('file', 'pic_name', 'luckysheetData'));
    // }

    public function viewExcel($pic_name, $file)
    {
        $file = urldecode($file);
        $full = storage_path("app/files/{$pic_name}/{$file}");
        if (!file_exists($full)) abort(404);

        $reader = IOFactory::createReaderForFile($full);
        $spreadsheet = $reader->load($full);

        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            $rows = $sheet->toArray(null, true, true, true); // key A, B, C, ...
            $celldata = [];
            foreach ($rows as $r => $row) {
                foreach ($row as $c => $v) {
                    $colIndex = Coordinate::columnIndexFromString($c);
                    $celldata[] = [
                        'r' => $r - 1, // Luckysheet index from 0
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
        $payload = $r->input('data');
        $file = $r->input('file');
        $full = storage_path("app/files/{$pic_name}/{$file}");

        if (!$payload || !file_exists($full)) {
            return response()->json(['error' => 'Invalid'], 400);
        }

        // Load spreadsheet lama
        $reader = IOFactory::createReaderForFile($full);
        $spreadsheet = $reader->load($full);

        // Hapus semua sheet jika ingin mulai dari awal
        while ($spreadsheet->getSheetCount() > 0) {
            $spreadsheet->removeSheetByIndex(0);
        }

        foreach ($payload as $sheetIndex => $sheetData) {
            $sheetName = $sheetData['name'] ?? 'Sheet' . ($sheetIndex + 1);
            $celldata = $sheetData['celldata'] ?? [];

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);

            foreach ($celldata as $cell) {
                $r = $cell['r'] ?? 0;
                $c = $cell['c'] ?? 0;
                $v = is_array($cell['v']) ? ($cell['v']['v'] ?? '') : '';

                // Pastikan r dan c numerik
                if (is_numeric($r) && is_numeric($c)) {
                    $col = Coordinate::stringFromColumnIndex($c + 1);
                    $row = $r + 1;
                    $coord = "{$col}{$row}";
                    $sheet->setCellValue($coord, $v);
                }
            }
        }

        // Hapus sheet pertama kosong jika ada
        if ($spreadsheet->getSheetCount() > 1 && $spreadsheet->getSheet(0)->getHighestRow() == 1) {
            $spreadsheet->removeSheetByIndex(0);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($full);

        return response()->json(['success' => true]);
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
                            'r' => $i - 1, // row index starts from 0
                            'c' => $j - 1, // column index starts from 0
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
        
            // Langsung return array, bukan dibungkus 'file' dan 'data'
            return response()->json($sheetsData);
        
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal membaca file Excel',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}