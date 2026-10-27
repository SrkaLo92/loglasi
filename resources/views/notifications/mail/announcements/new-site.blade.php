@php($p = 'margin:0 0 14px;')
<!doctype html>
<html lang="sr-Latn">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>loglasi.rs je dobio veliki update</title></head>
<body style="margin:0;padding:24px 12px;background:#f6f7f5;font-family:Arial,Helvetica,sans-serif;color:#1d2b26;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e7e4;border-radius:14px;">
<tr><td style="padding:24px 24px 8px;font-size:22px;font-weight:800;">loglasi<span style="color:#379b7f;">.rs</span></td></tr>
<tr><td style="padding:8px 24px 0;font-size:16px;line-height:1.6;">
<p style="{{ $p }}">Zdravo{{ $name !== '' ? ' '.$name : '' }},</p>
<p style="{{ $p }}">pišem ti jer imaš nalog na loglasi.rs, pa želim da prvi čuješ na čemu smo radili prethodnih nedelja.</p>
<p style="{{ $p }}">Sajt je dobio veliki update. Najviše smo se trudili da oglas lako postaviš sa telefona, za minut-dva: uslikaš, upišeš cenu i to je to. Kupci ti sada mogu pisati poruku direktno na sajtu, pa ne moraš svima da daješ broj telefona. A ako nešto tražiš, sačuvaj pretragu i javićemo ti mejlom čim se pojavi.</p>
<p style="{{ $p }}">Postavljanje oglasa je i dalje besplatno. Možeš imati {{ $free }}, svaki traje {{ $days }} dana, a kad istekne, obnoviš ga jednim klikom.</p>
<p style="{{ $p }}">Tvoj nalog je ostao isti i prijavljuješ se istim mejlom i lozinkom. Ako se ne sećaš lozinke, novu možeš da napraviš ovde: <a href="{{ $resetUrl }}" style="color:#2b7a64;">{{ preg_replace('#^https?://#', '', $resetUrl) }}</a></p>
<p style="{{ $p }}">Ako imaš nešto što ti više ne treba, sada je dobar trenutak da to postaviš: <a href="{{ $postUrl }}" style="color:#2b7a64;">{{ preg_replace('#^https?://#', '', $postUrl) }}</a></p>
<p style="margin:0 0 20px;"><a href="{{ $postUrl }}" style="display:inline-block;padding:14px 24px;background:#2b7a64;color:#ffffff;border-radius:10px;font-weight:700;text-decoration:none;">Postavi oglas</a></p>
<p style="{{ $p }}">I još nešto: uskoro izlazi i aplikacija loglasi.rs za Android i iPhone. Javiću ti kad bude dostupna.</p>
<p style="{{ $p }}">Ako ti nešto ne radi kako treba ili imaš predlog, samo odgovori na ovaj mejl. Čitam svaku poruku.</p>
<p style="margin:0 0 24px;">Pozdrav iz Loznice,<br>Srđan<br>loglasi.rs</p>
</td></tr>
<tr><td style="padding:16px 24px 24px;border-top:1px solid #e3e7e4;font-size:13px;line-height:1.5;color:#5b6470;">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu. <a href="{{ $homeUrl }}" style="color:#5b6470;">loglasi.rs</a><br>Ovaj mejl dobijaš jer imaš nalog na loglasi.rs. Ne želiš ovakve mejlove? <a href="{{ $unsubscribeUrl }}" style="color:#5b6470;">Odjavi se</a></td></tr>
</table>
</body>
</html>
