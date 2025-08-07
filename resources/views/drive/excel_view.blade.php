@extends('layouts.app')

@section('title', 'View Excel - ' . basename($file))

@section('content')
<h4 class="mb-3">Lihat/Edit Excel - <code>{{ basename($file) }}</code></h4>

<div id="luckysheet" style="margin:0;padding:0;width:100%;height:600px;"></div>

<button id="saveBtn" class="btn btn-success mt-3">
  <i class="bi bi-save"></i> Simpan Perubahan
</button>

<a href="{{ route('drive.browse', ['pic_name' => $pic_name]) }}" class="btn btn-secondary mt-3">Kembali ke Drive</a>

<input type="hidden" id="saveUrl" value="{{ route('drive.update_excel', ['pic_name' => $pic_name]) }}">
<input type="hidden" id="fileName" value="{{ $file }}">
@endsection

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/css/plugins.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/css/luckysheet.css" />
@endpush

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/js/plugin.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/luckysheet.umd.js"></script>

  <script>
    const luckysheetData = @json($luckysheetData);

    luckysheet.create({
      container: 'luckysheet',
      data: luckysheetData,
    });

    document.getElementById('saveBtn').addEventListener('click', function () {
      const data = luckysheet.getAllSheets();
      const url = document.getElementById('saveUrl').value;
      const file = document.getElementById('fileName').value;

      fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
          luckysheet: data,
          file: file
        })
      }).then(response => {
        if (response.ok) {
          alert('Berhasil disimpan.');
        } else {
          alert('Gagal menyimpan.');
        }
      });
    });
  </script>
@endpush