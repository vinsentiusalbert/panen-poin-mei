@extends('layouts.app_v3')

@section('title', 'Laporan Redeem V3')

@section('content')
<div class="container my-5">
    <div class="section-card mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
            <div>
                <h3 class="mb-1">Laporan Redeem Hadiah V3</h3>
                <small class="text-muted">Data akun, alamat, nomor telp, dan hadiah yang diredeem</small>
            </div>
            <a href="{{ route('admin.redeems.export') }}" class="btn btn-contact mt-3 mt-md-0">
                Download Excel
            </a>
        </div>
    </div>

    <div class="section-card">
        <div class="table-responsive">
            <table class="table table-transparent align-middle mb-0">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Akun</th>
                        <th>Email</th>
                        <th>Nomor Telp</th>
                        <th>Alamat</th>
                        <th>Remark</th>
                        <th>Hadiah</th>
                        <th>Tanggal Redeem</th>
                        <th>Status</th>
                        <th>Bukti Kirim</th>
                        <th>Bukti Terima</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($redeems as $index => $row)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $row->nama_akun }}</td>
                            <td>{{ $row->email_client }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{ $row->address }}</td>
                            <td>{{ $row->remark }}</td>
                            <td>{{ $row->prize_name }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y') }}</td>
                            <td>
                                @if($row->shipped_at)
                                    <span class="badge bg-success">Terkirim</span>
                                @else
                                    <span class="badge bg-warning text-dark">Belum Dikirim</span>
                                @endif
                            </td>
                            <td>
                                @if($row->shipping_proof_path)
                                    <a class="btn btn-outline-light btn-sm" href="{{ asset('storage/'.$row->shipping_proof_path) }}" target="_blank">
                                        Lihat
                                    </a>
                                @else
                                    <span class="text-muted">Belum ada</span>
                                @endif
                            </td>
                            <td>
                                @if($row->proof_path)
                                    <a class="btn btn-outline-light btn-sm" href="{{ asset('storage/'.$row->proof_path) }}" target="_blank">
                                        Lihat
                                    </a>
                                @else
                                    <span class="text-muted">Belum ada</span>
                                @endif
                            </td>
                            <td>
                                @if(!$row->shipped_at)
                                    <button
                                        type="button"
                                        class="btn btn-contact btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#shipModal{{ $row->id }}"
                                    >
                                        Input Bukti Kirim
                                    </button>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted">Belum ada data redeem</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@foreach($redeems as $row)
    @if(!$row->shipped_at)
        <div class="modal fade" id="shipModal{{ $row->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.redeems.ship', $row->id) }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="source_version" value="{{ $row->source_version }}">
                        <div class="modal-header">
                            <h5 class="modal-title">Input Bukti Kirim</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="small text-muted mb-2">
                                    {{ $row->nama_akun }} - {{ $row->prize_name }}
                                </div>
                                <label for="shipping_proof_{{ $row->id }}" class="form-label">Upload bukti kirim</label>
                                <input
                                    type="file"
                                    class="form-control"
                                    id="shipping_proof_{{ $row->id }}"
                                    name="shipping_proof"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    required
                                >
                                <small class="text-muted">Format: JPG, JPEG, PNG, PDF. Maksimal 5 MB.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-contact">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endforeach
@endsection
