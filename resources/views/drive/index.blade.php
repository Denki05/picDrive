@extends('layouts.app')

@section('title', 'Drive - ' . ucfirst($pic_name))

@section('content')
<div class="d-flex align-items-center flex-wrap mb-4">
    


  {{-- Drive label --}}
  <span class="me-3 fw-bold text-dark">
    <i class="bi bi-hdd-fill"></i> Drive {{ ucfirst($pic_name) }}
  </span>

  {{-- Root --}}
  <a href="{{ url("drive/$pic_name") }}" class="text-decoration-none text-primary me-2">
    <i class="bi bi-house-door-fill"></i> Root
  </a>

  {{-- Folder path --}}
  @php
    $relativePath = trim(Str::after($path, "files/$pic_name"), '/');
    $segments = $relativePath ? explode('/', $relativePath) : [];
    $built = '';
    @endphp


  @foreach ($segments as $seg)
    @php $built .= $seg . '/'; @endphp
    <span class="mx-1 text-muted">/</span>
    <a href="{{ route('drive.browse', ['pic_name' => $pic_name, 'any' => trim($built, '/')]) }}"
        class="text-decoration-none text-primary me-2">
        <i class="bi bi-folder2-open"></i> {{ $seg }}
    </a>
    @endforeach
</div>

<div class="card mb-4">
  <div class="card-body">
    <form action="{{ route('drive.upload_file', ['pic_name' => $pic_name]) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
      @csrf
      <input type="hidden" name="path" value="{{ $path }}">

      <div class="col-auto">
        <input type="file" name="upload_file" accept=".xlsx" class="form-control" required>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-upload"></i> Upload
        </button>
      </div>
    </form>
  </div>
</div>


<div class="row g-3">
  {{-- Folders --}}
  @foreach ($folders as $folder)
    @php
        $relative = Str::after($folder, "files/$pic_name/");
        $isFavorite = in_array($relative, $favorites);
    @endphp
    <div class="col-6 col-md-4 col-lg-3">
        <div class="card shadow-sm h-100 position-relative {{ $isFavorite ? 'border-warning border-2' : '' }}">
            <a href="{{ route('drive.browse', ['pic_name' => $pic_name, 'any' => $relative]) }}" class="text-decoration-none">
            <div class="card-body text-center">
                <i class="bi bi-folder-fill display-4 {{ $isFavorite ? 'text-warning' : 'text-primary' }}"></i>
                <p class="mt-2 mb-0 text-dark small fw-bold">{{ basename($folder) }}</p>
            </div>
            </a>

            {{-- Tombol bintang --}}
            <form method="POST" action="{{ route('drive.toggle_favorite', ['pic_name' => $pic_name]) }}" class="position-absolute top-0 end-0 m-2">
                @csrf
                <input type="hidden" name="pic_name" value="{{ $pic_name }}">
                <input type="hidden" name="folder_path" value="{{ $relative }}">
                <button type="submit" class="btn btn-sm {{ $isFavorite ? 'btn-warning' : 'btn-outline-secondary' }}" title="Tandai">
                    <i class="bi {{ $isFavorite ? 'bi-star-fill' : 'bi-star' }}"></i>
                </button>
            </form>
        </div>
    </div>
  @endforeach

  {{-- Files --}}
  @foreach ($items as $file)
  @php
    $relativeFile = Str::after($file, "files/$pic_name/");
    $isFavorite = in_array($relativeFile, $favorites);
  @endphp

  <div class="col-6 col-md-4 col-lg-3">
    <div class="card shadow-sm h-100 position-relative {{ $isFavorite ? 'border-warning border-2' : '' }}">
      <div class="card-body text-center">
        <i class="bi bi-file-earmark-excel display-4 text-success"></i>
        <p class="mt-2 mb-1 text-dark small fw-bold">{{ basename($file) }}</p>

        @if (Str::endsWith($file, '.xlsx'))
           <form action="{{ route('drive.edit_excel', ['pic_name' => $pic_name]) }}" method="POST" class="d-flex justify-content-center mb-2">
                @csrf
                <input type="hidden" name="pic_name" value="{{ $pic_name }}">
                <input type="hidden" name="file_path" value="{{ $file }}">
                <a href="{{ url("drive/$pic_name/excel/view?file=" . urlencode($relativeFile)) }}"
                    class="btn btn-sm btn-outline-primary me-1">
                    View
                </a>
            </form>
        @endif
      </div>

      {{-- Tombol Bintang --}}
      <form method="POST" action="{{ route('drive.toggle_favorite', ['pic_name' => $pic_name]) }}"
            class="position-absolute top-0 end-0 m-2">
        @csrf
        <input type="hidden" name="pic_name" value="{{ $pic_name }}">
        <input type="hidden" name="folder_path" value="{{ $relativeFile }}">
        <button type="submit" class="btn btn-sm {{ $isFavorite ? 'btn-warning' : 'btn-outline-secondary' }}"
                title="{{ $isFavorite ? 'Hapus dari Favorit' : 'Tandai sebagai Favorit' }}">
          <i class="bi {{ $isFavorite ? 'bi-star-fill' : 'bi-star' }}"></i>
        </button>
      </form>
    </div>
  </div>
@endforeach
</div>
@endsection