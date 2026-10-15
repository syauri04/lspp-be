{{-- Satu baris penyusun/validator. $bagian, $i (indeks), $row (penandatangan|object old()|null) --}}
@php $prefix = "penandatangan[{$bagian}][{$i}]"; @endphp
<tr>
    <td>
        <input type="hidden" name="{{ $prefix }}[id]" value="{{ $row->id ?? '' }}">
        <select class="form-select form-select-sm" name="{{ $prefix }}[peran]">
            <option value="penyusun" {{ ($row->peran ?? 'penyusun') === 'penyusun' ? 'selected' : '' }}>Penyusun</option>
            <option value="validator" {{ ($row->peran ?? '') === 'validator' ? 'selected' : '' }}>Validator</option>
        </select>
    </td>
    <td><input type="text" class="form-control form-control-sm" name="{{ $prefix }}[nama]" maxlength="255" value="{{ $row->nama ?? '' }}" required></td>
    <td><input type="text" class="form-control form-control-sm" name="{{ $prefix }}[no_reg]" maxlength="100" value="{{ $row->no_reg ?? '' }}" placeholder="MET. 000.000000 2025"></td>
    <td>@include('cms.mapa-template.partials.ttd', ['nama' => $prefix, 'row' => ($row instanceof \App\Models\MapaTemplatePenandatangan ? $row : null)])</td>
    <td>
        <input type="date" class="form-control form-control-sm" name="{{ $prefix }}[tanggal]"
            value="{{ $row instanceof \App\Models\MapaTemplatePenandatangan ? $row->tanggal?->format('Y-m-d') : ($row->tanggal ?? '') }}">
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-outline-danger btn-sm btn-hapus-baris" title="Hapus baris"><i class="mdi mdi-trash-can-outline"></i></button>
    </td>
</tr>
