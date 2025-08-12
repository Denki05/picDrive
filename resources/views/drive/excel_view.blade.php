@extends('layouts.app')

@section('title','Excel - '.basename($file))

@section('content')
<div class="d-flex flex-column h-100 p-3">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <h5 style="font-size: 14px; margin-bottom: 0;">Edit Excel: <code>{{ basename($file) }}</code></h5>
        <div>
            <button id="saveBtn" class="btn btn-success"><i class="bi bi-save"></i> Simpan</button>
            <a href="{{ route('drive.browse',['pic_name'=>$pic_name]) }}" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <div id="luckysheet" class="flex-grow-1"></div>

    <input type="hidden" id="saveUrl" value="{{ route('drive.update_excel',['pic_name'=>$pic_name]) }}">
    <input type="hidden" id="fileName" value="{{ $file }}">
</div>
@endsection

@push('styles')
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/css/pluginsCss.css' />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/plugins.css' />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/css/luckysheet.css' />
<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/assets/iconfont/iconfont.css' />
<style>
    /* CSS untuk memastikan layout stabil dan Luckysheet mengisi sisa ruang */
    html, body, .h-100 {
        height: 100%;
        margin: 0;
        padding: 0;
        overflow: hidden; /* Mencegah scrollbar halaman utama */
    }
    .d-flex {
        display: flex;
    }
    .flex-column {
        flex-direction: column;
    }
    .flex-grow-1 {
        flex-grow: 1; /* Agar Luckysheet mengisi sisa ruang */
        height: 0; /* Penting untuk flexbox */
        min-height: 400px; /* Minimal tinggi Luckysheet */
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/js/plugin.js"></script>
<script src="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/luckysheet.umd.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', (event) => {
        const exportData = @json($sheets);

        luckysheet.create({ 
            container: 'luckysheet',
            lang: 'en',
            showinfobar: true, // Pastikan infobar (toolbar) muncul
            showtoolbar: true, // Pastikan toolbar utama juga muncul
            allowEdit: true,
            data: exportData
        });
    });

    // Simpan perubahan
    document.getElementById('saveBtn').onclick = () => {
        const allSheets = luckysheet.getAllSheets();
        fetch(document.getElementById('saveUrl').value, {
            method:'POST',
            headers:{
                'Content-Type':'application/json',
                'X-CSRF-TOKEN':'{{ csrf_token() }}'
            },
            body: JSON.stringify({
                sheets: allSheets,
                file: document.getElementById('fileName').value
            })
        }).then(res => {
            if (res.ok) {
                alert('Tersimpan!');
            } else {
                // Baris ini akan mencetak error dari server ke console browser
                res.text().then(text => {
                    console.error('Server error:', text);
                    alert('Error saat menyimpan. Cek console untuk detail.');
                });
            }
        }).catch(error => {
            console.error('Fetch error:', error);
            alert('Terjadi kesalahan jaringan.');
        });
    };
</script>
@endpush