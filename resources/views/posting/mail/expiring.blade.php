<!doctype html>
<html lang="sr-Latn">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Vaš oglas uskoro ističe</title></head>
<body style="margin:0;padding:24px 12px;background:#f6f7f5;font-family:Arial,Helvetica,sans-serif;color:#1d2b26;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e7e4;border-radius:14px;">
<tr><td style="padding:24px 24px 8px;">@include('partials.mail-logo')</td></tr>
<tr><td style="padding:8px 24px 0;font-size:16px;line-height:1.55;">
<p style="margin:0 0 12px;">Zdravo{{ $name ? ', '.$name : '' }}!</p>
<p style="margin:0 0 12px;">Vaš oglas <strong>„{{ $title }}”</strong> ističe {{ $expires }}. Posle toga se više neće prikazivati na sajtu.</p>
<p style="margin:0 0 20px;">Obnova je besplatna i oglas ostaje aktivan još mesec dana.</p>
<p style="margin:0 0 24px;"><a href="{{ $renewUrl }}" style="display:inline-block;padding:14px 24px;background:#2b7a64;color:#ffffff;border-radius:10px;font-weight:700;text-decoration:none;">Obnovi oglas</a></p>
<p style="margin:0 0 8px;font-size:14px;color:#5b6470;">Oglas: <a href="{{ $listingUrl }}" style="color:#2b7a64;">{{ $listingUrl }}</a></p>
<p style="margin:0 0 24px;font-size:14px;color:#5b6470;">Ako ste prodali, oglas možete da označite kao prodat u delu <a href="{{ $myListingsUrl }}" style="color:#2b7a64;">Moji oglasi</a>.</p>
</td></tr>
<tr><td style="padding:16px 24px 24px;border-top:1px solid #e3e7e4;font-size:13px;color:#5b6470;">Pozdrav, loglasi.rs<br>loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.</td></tr>
</table>
</body>
</html>
