{{-- Satu aspek penilaian FR.IA.04B. $i (indeks unik), $aspek (lingkup/pertanyaan/kesesuaian) --}}
<div class="border rounded p-2 mb-2 baris-aspek">
    <div class="d-flex gap-2 align-items-start">
        <span class="pt-1 text-muted small nomor-aspek" style="width:24px;"></span>
        <div class="flex-grow-1">
            <div class="row g-2 mb-2">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold mb-1">Lingkup penyajian proyek / kegiatan terstruktur</label>
                    <input type="text" class="form-control form-control-sm" maxlength="500"
                        name="instrumen[ia04b][aspek][{{ $i }}][lingkup]" value="{{ $aspek['lingkup'] ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Kesesuaian (unit/elemen/KUK)</label>
                    <input type="text" class="form-control form-control-sm" maxlength="500" placeholder="mis. (TLCM5)"
                        name="instrumen[ia04b][aspek][{{ $i }}][kesesuaian]" value="{{ $aspek['kesesuaian'] ?? '' }}">
                </div>
            </div>
            <label class="form-label small fw-semibold mb-1">Pertanyaan</label>
            <textarea class="form-control form-control-sm" rows="3" maxlength="5000"
                name="instrumen[ia04b][aspek][{{ $i }}][pertanyaan]">{{ $aspek['pertanyaan'] ?? '' }}</textarea>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-aspek" title="Hapus aspek"><i class="mdi mdi-trash-can-outline"></i></button>
    </div>
</div>
