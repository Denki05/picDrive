<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Drive App - @yield('title', 'Dashboard')</title>

  <!-- Bootstrap 5 CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Optional Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  @stack('styles')
</head>
<body class="bg-light">

@php
  $pic_name = $pic_name ?? request()->segment(2); // Fallback jika belum diset
@endphp

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">DriveApp</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarCollapse">
        <ul class="navbar-nav ms-auto">
          @isset($pic_name)
            <li class="nav-item">
              <a class="nav-link active" href="#">PIC: <strong>{{ ucfirst($pic_name) }}</strong></a>
            </li>
          @endisset
        </ul>
      </div>
    </div>
  </nav>

  <!-- Layout Container -->
  <div class="container-fluid">
      <div class="row">
        
        <!-- Sidebar -->
        <div class="col-md-3 col-lg-2 d-none d-md-block bg-white border-end min-vh-100">
          <div class="p-3">
            <h6 class="text-uppercase text-muted small mb-3">Navigasi</h6>
            <ul class="nav flex-column mb-4">
              {{-- Folder Saya --}}
              <li class="nav-item">
                <a class="nav-link d-flex align-items-center" href="{{ url("drive/$pic_name") }}">
                  <i class="bi bi-folder2 me-2 text-primary"></i> Folder
                </a>
              </li>
            </ul>

            {{-- Favorit --}}
            @if (!empty($favorites ?? []))
              <h6 class="text-uppercase text-muted small">Favorite</h6>
              <ul class="nav flex-column mb-4">
                @foreach ($favorites as $fav)
                  <li class="nav-item">
                    <a class="nav-link d-flex align-items-center"
                      href="{{ route('drive.browse', ['pic_name' => $pic_name, 'any' => $fav]) }}">
                      <i class="bi bi-star-fill text-warning me-2"></i> {{ basename($fav) }}
                    </a>
                  </li>
                @endforeach
              </ul>
            @endif

            {{-- Recently (masih statis, bisa dinamis nanti) --}}
            @if (!empty($recents ?? []))
              <h6 class="text-uppercase text-muted small">Recently</h6>
              <ul class="nav flex-column mb-4">
                @foreach ($recents as $recentPath)
                  <li class="nav-item">
                    <a class="nav-link d-flex align-items-center"
                      href="{{ route('drive.browse', ['pic_name' => $pic_name, 'any' => $recentPath]) }}">
                      <i class="bi bi-clock-history me-2 text-info"></i> {{ basename($recentPath) }}
                    </a>
                  </li>
                @endforeach
              </ul>
            @endif

            {{-- Upload Terakhir (placeholder) --}}
            {{--<h6 class="text-uppercase text-muted small">Upload Terakhir</h6>
            <ul class="nav flex-column">
              <li class="nav-item">
                <a class="nav-link d-flex align-items-center" href="#">
                  <i class="bi bi-upload me-2 text-success"></i> (Belum tersedia)
                </a>
              </li>
            </ul>--}}
          </div>
        </div>

      <!-- Main Content -->
      <main class="col-md-9 col-lg-10 px-md-4 py-4">
        @if (session('success'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @yield('content')
      </main>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-white border-top py-3 text-center text-muted small">
    <div class="container-fluid">
      <span>&copy; {{ date('Y') }} DriveApp. All rights reserved.</span>
    </div>
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
