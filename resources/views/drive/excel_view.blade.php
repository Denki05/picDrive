@extends('layouts.app')

@section('title','Excel - '.basename($file))

@section('content')
  <h4>Edit Excel: <code>{{ basename($file) }}</code></h4>

  <div id="luckysheet" style="width:100%;height:600px;"></div>

  <button id="saveBtn" class="btn btn-success mt-3"><i class="bi bi-save"></i> Simpan</button>
  <a href="{{ route('drive.browse',['pic_name'=>$pic_name]) }}" class="btn btn-secondary mt-3">Kembali</a>
  <input type="hidden" id="saveUrl" value="{{ route('drive.update_excel',['pic_name'=>$pic_name]) }}">
  <input type="hidden" id="fileName" value="{{ $file }}">
@endsection

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/css/plugins.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/css/luckysheet.css">
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/plugins/js/plugin.js"></script>
<script src="https://cdn.jsdelivr.net/npm/luckysheet@latest/dist/luckysheet.umd.js"></script>
<script>
    const exportData = @json($sheets);

    luckysheet.create({
      container: 'luckysheet',
      lang: 'en',
      showinfobar: false,
      allowEdit: true,
      data: exportData
    });

    // Fungsi Tambah Sheet
    

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
          data: allSheets,
          file: document.getElementById('fileName').value
        })
      }).then(res => res.ok ? alert('Tersimpan') : alert('Error'));
    };
</script>
@endpush