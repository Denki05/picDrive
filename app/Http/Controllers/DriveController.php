<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class DriveController extends Controller
{
    public function index($pic_name)
    {
        return $this->browse($pic_name);
    }

    public function browse($pic_name, $any = null)
    {
        // Cek dan buat folder default PIC jika belum ada
        $basePath = 'files/' . $pic_name;
        if (!Storage::exists($basePath)) {
            $this->createDefaultFoldersForPic($pic_name);
        }

        $path = $basePath . ($any ? '/' . $any : '');

        // Simpan sebagai akses terakhir
        $this->logRecentAccess($pic_name, Str::after($path, 'files/' . $pic_name . '/'));

        $items = Storage::files($path);
        $folders = Storage::directories($path);

        // Favorit
        $fileFav = storage_path("app/favorites_{$pic_name}.json");
        $favorites = File::exists($fileFav) ? json_decode(File::get($fileFav), true) : [];

        // Akses terakhir
        $fileRecent = storage_path("app/recent_{$pic_name}.json");
        $recents = File::exists($fileRecent) ? json_decode(File::get($fileRecent), true) : [];

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

    private function createDefaultFoldersForPic($pic)
    {
        $struktur = config('drive_structure.default_structure', []);

        foreach ($struktur as $prov => $kotas) {
            foreach ($kotas as $kota) {
                Storage::makeDirectory("files/{$pic}/{$prov}/{$kota}");
            }
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
}
