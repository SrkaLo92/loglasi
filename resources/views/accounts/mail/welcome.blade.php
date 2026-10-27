<!doctype html>
<html lang="sr-Latn">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Dobro došli na loglasi.rs</title></head>
<body style="margin:0;padding:24px 12px;background:#f6f7f5;font-family:Arial,Helvetica,sans-serif;color:#1d2b26;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e7e4;border-radius:14px;">
<tr><td style="padding:24px 24px 8px;">@include('partials.mail-logo')</td></tr>
<tr><td style="padding:8px 24px 0;font-size:16px;line-height:1.55;">
<p style="margin:0 0 12px;">Zdravo{{ $name ? ', '.$name : '' }}!</p>
<p style="margin:0 0 12px;">Dobro došli na loglasi.rs, sajt za besplatne oglase za Loznicu i okolinu. Vaš nalog je spreman.</p>
<p style="margin:0 0 8px;"><strong>Prvi oglas za par minuta:</strong></p>
<ol style="margin:0 0 16px;padding-left:20px;">
<li style="margin:0 0 6px;">Kliknite „Postavite oglas” i izaberite kategoriju.</li>
<li style="margin:0 0 6px;">Napišite naslov, opis i cenu, ili izaberite „Dogovor”.</li>
<li style="margin:0 0 6px;">Dodajte nekoliko fotografija. Oglasi sa slikama dobijaju više poziva.</li>
</ol>
<p style="margin:0 0 20px;">Privatna lica besplatno imaju {{ $free }}. Oglas traje {{ $days }} dana, a obnova je besplatna.</p>
<p style="margin:0 0 24px;"><a href="{{ $postUrl }}" style="display:inline-block;padding:14px 24px;background:#2b7a64;color:#ffffff;border-radius:10px;font-weight:700;text-decoration:none;">Postavite prvi oglas</a></p>
<p style="margin:0 0 12px;font-size:14px;color:#5b6470;">Tražite nešto? Svi oglasi su na <a href="{{ $listingsUrl }}" style="color:#2b7a64;">loglasi.rs/oglasi</a>.</p>
<p style="margin:0 0 24px;font-size:14px;color:#5b6470;">Imate firmu ili radnju? Profil firme sa logom i svim oglasima na jednom mestu: <a href="{{ $firmsUrl }}" style="color:#2b7a64;">Za firme</a>.</p>
</td></tr>
<tr><td style="padding:16px 24px 24px;border-top:1px solid #e3e7e4;font-size:13px;color:#5b6470;">Pozdrav, loglasi.rs<br>Pitanja: {{ $contact }}</td></tr>
</table>
</body>
</html>
