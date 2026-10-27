<!doctype html>
<html lang="sr-Latn">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Novi loglasi.rs je stigao!</title></head>
<body style="margin:0;padding:24px 12px;background:#f6f7f5;font-family:Arial,Helvetica,sans-serif;color:#1d2b26;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e7e4;border-radius:14px;">
<tr><td style="padding:24px 24px 8px;font-size:22px;font-weight:800;">loglasi<span style="color:#379b7f;">.rs</span></td></tr>
<tr><td style="padding:8px 24px 0;font-size:16px;line-height:1.55;">
<p style="margin:0 0 12px;">Zdravo{{ $name !== '' ? ' '.$name : '' }},</p>
<p style="margin:0 0 12px;">novi loglasi.rs je stigao! 🎉</p>
<p style="margin:0 0 8px;">Potpuno smo ga napravili iznova, da ti prodaja i kupovina u Loznici i okolini budu lakše nego ikad:</p>
<ul style="margin:0 0 16px;padding-left:20px;">
<li style="margin:0 0 6px;"><strong>Oglas za minut, sa telefona:</strong> slikaš, upišeš cenu, objaviš.</li>
<li style="margin:0 0 6px;"><strong>{{ \Illuminate\Support\Str::ucfirst($free) }} besplatno.</strong> Oglas traje {{ $days }} dana i obnoviš ga jednim klikom, opet besplatno.</li>
<li style="margin:0 0 6px;"><strong>Poruke na sajtu:</strong> kupci ti pišu direktno, bez deljenja broja ako to ne želiš.</li>
<li style="margin:0 0 6px;"><strong>Sačuvana pretraga:</strong> javljamo ti čim se pojavi ono što tražiš.</li>
<li style="margin:0 0 6px;"><strong>Firme iz Loznice:</strong> sve lokalne <a href="{{ $firmsUrl }}" style="color:#2b7a64;">firme</a> i njihova ponuda na jednom mestu.</li>
</ul>
<p style="margin:0 0 12px;"><strong>Tvoj nalog je ostao isti.</strong> Prijavljuješ se istim mejlom i istom lozinkom. Ako si zaboravio lozinku, na stranici za prijavu klikni „Zaboravljena lozinka“ i za minut postaviš novu: <a href="{{ $resetUrl }}" style="color:#2b7a64;">nova lozinka</a>.</p>
<p style="margin:0 0 12px;"><a href="{{ $postUrl }}" style="display:inline-block;padding:14px 24px;background:#2b7a64;color:#ffffff;border-radius:10px;font-weight:700;text-decoration:none;">Postavi oglas</a></p>
<p style="margin:0 0 20px;font-size:14px;color:#5b6470;">Prijava: <a href="{{ $loginUrl }}" style="color:#2b7a64;">{{ preg_replace('#^https?://#', '', $loginUrl) }}</a></p>
<p style="margin:0 0 12px;"><strong>Uskoro i u telefonu:</strong> stiže aplikacija loglasi.rs za Android i iPhone. Javićemo ti čim bude u prodavnicama. 📱</p>
<p style="margin:0 0 20px;">Imaš nešto što ti ne treba? Sad je pravi trenutak. Ko oglasi, taj i proda.</p>
<p style="margin:0 0 24px;">Pozdrav iz Loznice,<br>Srđan i tim loglasi.rs</p>
</td></tr>
<tr><td style="padding:16px 24px 24px;border-top:1px solid #e3e7e4;font-size:13px;line-height:1.5;color:#5b6470;">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu. <a href="{{ $homeUrl }}" style="color:#5b6470;">loglasi.rs</a><br>Ovaj mejl dobijaš jer imaš nalog na loglasi.rs. Ne želiš ovakve mejlove? <a href="{{ $unsubscribeUrl }}" style="color:#5b6470;">Odjavi se</a></td></tr>
</table>
</body>
</html>
