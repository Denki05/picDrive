<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    public function viewExcel(Request $request, $pic_name)
    {
        $file = $request->query('file');
        $fullPath = storage_path('app/files/' . $pic_name . '/' . $file);

        if (!file_exists($fullPath)) {
            return abort(404, 'File tidak ditemukan');
        }

        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $dataArray = $sheet->toArray();

        $luckysheetData = [
            [
                "name" => "Sheet1",
                "color" => "",
                "status" => 1,
                "order" => 0,
                "data" => array_map(function ($row) {
                    return array_map(function ($cell) {
                        return ['v' => $cell];
                    }, $row);
                }, $dataArray)
            ]
        ];

        return view('drive.excel_view', compact('luckysheetData', 'file', 'pic_name'));
    }

    public function updateExcel(Request $request, $pic_name)
    {
        $file = $request->input('file');
        $luckysheetData = $request->input('luckysheet');
        $fullPath = storage_path('app/files/' . $pic_name . '/' . $file);

        if (!$luckysheetData || !file_exists($fullPath)) {
            return response()->json(['error' => 'Data tidak valid'], Response::HTTP_BAD_REQUEST);
        }

        $sheetData = $luckysheetData[0]['data'] ?? [];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($sheetData as $rowIndex => $row) {
            foreach ($row as $colIndex => $cell) {
                $value = $cell['v'] ?? null;
                $cellCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . ($rowIndex + 1);
                $sheet->setCellValue($cellCoord, $value);
            }
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($fullPath);

        return response()->json(['success' => true]);
    }
}
