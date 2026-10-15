{{-- Kolom tanda tangan: gambar tersimpan + unggah baru + hapus. $nama = prefix nama input, $row = penandatangan|null --}}
@if ($row?->id && $row?->path_ttd)
    <div class="d-flex align-items-center gap-2 mb-1">
        <img src="{{ route('mapa-template.ttd', $row->id) }}" alt="Tanda tangan" class="border rounded bg-white" style="max-height:48px;">
        <label class="form-check-label small text-danger">
            <input type="checkbox" class="form-check-input" name="{{ $nama }}[hapus_ttd]" value="1"> Hapus
        </label>
    </div>
@endif
<input type="file" class="form-control form-control-sm" name="{{ $nama }}[ttd]" accept="image/png,image/jpeg">
<div class="form-text small">PNG/JPG, maks 1 MB.</div>
