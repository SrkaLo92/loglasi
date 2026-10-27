@php($p = 'margin:0 0 14px;')
<!doctype html>
<html lang="sr-Latn">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Osvežili smo loglasi.rs</title></head>
<body style="margin:0;padding:24px 12px;background:#f6f7f5;font-family:Arial,Helvetica,sans-serif;color:#1d2b26;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e7e4;border-radius:14px;">
<tr><td style="padding:24px 24px 8px;">@include('partials.mail-logo')</td></tr>
<tr><td style="padding:8px 24px 0;font-size:16px;line-height:1.6;">
<p style="{{ $p }}">Zdravo{{ $name !== '' ? ' '.$name : '' }},</p>
<p style="{{ $p }}">prethodnih nedelja smo dosta radili na loglasi.rs i drago nam je što konačno možemo da ti pokažemo rezultat.</p>
<p style="{{ $p }}">Postavljanje oglasa je sada mnogo jednostavnije, posebno sa telefona. Dodaš slike, upišeš cenu i oglas je objavljen. Kupci ti mogu pisati preko poruka na sajtu, tako da broj telefona daješ samo kome ti želiš. Ako nešto tražiš, sačuvaj pretragu i stići će ti mejl čim se pojavi odgovarajući oglas.</p>
<p style="{{ $p }}">Oglašavanje je i dalje besplatno. Možeš imati {{ $free }} u isto vreme, a kada oglasu prođe {{ $days }} dana, obnoviš ga jednim klikom.</p>
<p style="{{ $p }}">Tvoj nalog nismo dirali. Prijavljuješ se istim mejlom i lozinkom kao i do sada. Ako se lozinke ne sećaš, novu postavljaš za par minuta preko stranice „Zaboravljena lozinka“: <a href="{{ $resetUrl }}" style="color:#2b7a64;">{{ preg_replace('#^https?://#', '', $resetUrl) }}</a></p>
<p style="margin:0 0 20px;"><a href="{{ $postUrl }}" style="display:inline-block;padding:14px 24px;background:#2b7a64;color:#ffffff;border-radius:10px;font-weight:700;text-decoration:none;">Postavi oglas</a></p>
<p style="{{ $p }}">U pripremi je i aplikacija loglasi.rs za Android i iPhone, pa ćeš uskoro sve ovo moći da radiš i iz nje. Javićemo ti kad izađe.</p>
<p style="{{ $p }}">Ako primetiš da nešto ne radi kako treba ili imaš ideju kako da sajt bude bolji, odgovori na ovaj mejl. Svaku poruku čitamo.</p>
<p style="{{ $p }}">Hvala ti što si sa nama od početka.</p>
<p style="margin:0 0 24px;">Pozdrav iz Loznice,<br>Tim loglasi.rs</p>
</td></tr>
<tr><td style="padding:16px 24px 24px;border-top:1px solid #e3e7e4;font-size:13px;line-height:1.5;color:#5b6470;">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu. <a href="{{ $homeUrl }}" style="color:#5b6470;">loglasi.rs</a><br>Ovaj mejl dobijaš jer imaš nalog na loglasi.rs. Ne želiš ovakve mejlove? <a href="{{ $unsubscribeUrl }}" style="color:#5b6470;">Odjavi se</a></td></tr>
</table>
</body>
</html>
