{{-- Satu baris teks template (pertanyaan FR.IA.03 / FR.IA.09, bukti FR.IA.08). $teks, $nama (nama input array), $maks --}}
<div class="d-flex gap-2 mb-2 align-items-start baris-pertanyaan">
    <span class="pt-1 text-muted small nomor-pertanyaan" style="width:24px;"></span>
    <textarea class="form-control form-control-sm" rows="2" maxlength="{{ $maks ?? 2000 }}" name="{{ $nama ?? 'instrumen[ia03][pertanyaan][]' }}">{{ $teks }}</textarea>
    <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-pertanyaan" title="Hapus"><i class="mdi mdi-trash-can-outline"></i></button>
</div>
