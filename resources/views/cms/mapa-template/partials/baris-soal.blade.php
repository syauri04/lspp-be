{{-- Satu soal esai FR.IA.06 + kunci jawaban. $i (indeks unik), $soal (array pertanyaan/kunci) --}}
<div class="border rounded p-2 mb-2 baris-soal">
    <div class="d-flex gap-2 align-items-start">
        <span class="pt-1 text-muted small nomor-soal" style="width:24px;"></span>
        <div class="flex-grow-1">
            <label class="form-label small fw-semibold mb-1">Pertanyaan</label>
            <textarea class="form-control form-control-sm mb-2" rows="2" maxlength="5000"
                name="instrumen[ia06][soal][{{ $i }}][pertanyaan]">{{ $soal['pertanyaan'] ?? '' }}</textarea>
            <label class="form-label small fw-semibold mb-1">Kunci Jawaban <span class="text-muted fw-normal">(hanya asesor)</span></label>
            <textarea class="form-control form-control-sm" rows="3" maxlength="10000"
                name="instrumen[ia06][soal][{{ $i }}][kunci]">{{ $soal['kunci'] ?? '' }}</textarea>
        </div>
        <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-soal" title="Hapus soal"><i class="mdi mdi-trash-can-outline"></i></button>
    </div>
</div>
