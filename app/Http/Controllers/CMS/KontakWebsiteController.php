<?php

namespace App\Http\Controllers\CMS;

use App\Http\Controllers\Controller;
use App\Support\KontakLembaga;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** CMS: kontak lembaga di website (footer "Kontak Kami" & tombol WhatsApp mengambang). */
class KontakWebsiteController extends Controller
{
    public function edit(): View
    {
        $kontak = KontakLembaga::ambil();

        return view('cms.kontak-website.edit', [
            'kontak' => $kontak,
            'whatsappTampil' => KontakLembaga::formatWhatsapp($kontak['whatsapp']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'whatsapp' => ['required', 'string', 'max:25', function ($attr, $nilai, $gagal) {
                if (! KontakLembaga::whatsappSah((string) $nilai)) {
                    $gagal('Nomor WhatsApp tidak valid. Gunakan nomor Indonesia, mis. 0822-4962-8093 atau +62 822-4962-8093.');
                }
            }],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'alamat' => ['required', 'string', 'max:500'],
            'pesan_wa' => ['nullable', 'string', 'max:300'],
        ], [], [
            'nama' => 'nama kontak',
            'whatsapp' => 'nomor WhatsApp',
            'pesan_wa' => 'pesan awal WhatsApp',
        ]);

        KontakLembaga::simpan($data, $request->user());

        return redirect()->route('kontak-website.edit')->with('success', 'Kontak website berhasil disimpan.');
    }
}
