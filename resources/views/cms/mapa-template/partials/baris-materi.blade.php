{{-- Satu materi/substansi wawancara FR.IA.08: dropdown KUK unit ($unit) dikelompokkan per elemen. $dipilih = id KUK terpilih|null --}}
<div class="d-flex gap-2 mb-2 align-items-center baris-materi">
    <select class="form-select form-select-sm" name="instrumen[ia08][materi][]">
        <option value="">— Pilih KUK —</option>
        @foreach ($unit->elemenKompetensi as $e => $elemen)
            <optgroup label="Elemen {{ $elemen->nomor_elemen ?? $e + 1 }}. {{ \Illuminate\Support\Str::limit($elemen->nama_elemen, 80) }}">
                @foreach ($elemen->kriteriaUnjukKerja as $kuk)
                    <option value="{{ $kuk->id }}" @selected((int) $dipilih === $kuk->id)>{{ $kuk->nomor_kuk }} {{ \Illuminate\Support\Str::limit($kuk->deskripsi, 140) }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-materi" title="Hapus"><i class="mdi mdi-close"></i></button>
</div>
