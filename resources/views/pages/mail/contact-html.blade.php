<!doctype html>
<html lang="sr-Latn">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Poruka sa kontakt forme</title></head>
<body style="margin:0;padding:24px 12px;background:#f6f7f5;font-family:Arial,Helvetica,sans-serif;color:#1d2b26;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e3e7e4;border-radius:14px;">
<tr><td style="padding:24px 24px 8px;font-size:22px;font-weight:800;">loglasi<span style="color:#379b7f;">.rs</span></td></tr>
<tr><td style="padding:8px 24px 0;font-size:16px;line-height:1.55;">
<p style="margin:0 0 16px;">Nova poruka sa kontakt forme na sajtu.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 16px;font-size:15px;">
<tr><td style="padding:2px 12px 2px 0;color:#5b6470;">Ime</td><td style="padding:2px 0;"><strong>{{ $data['ime'] }}</strong></td></tr>
<tr><td style="padding:2px 12px 2px 0;color:#5b6470;">{{ $data['email'] !== '' ? 'E-mail' : 'Telefon' }}</td><td style="padding:2px 0;">{{ $data['email'] !== '' ? $data['email'] : $data['telefon'] }}</td></tr>
<tr><td style="padding:2px 12px 2px 0;color:#5b6470;">Tema</td><td style="padding:2px 0;">{{ $data['tema'] }}</td></tr>
</table>
<div style="margin:0 0 20px;padding:14px 16px;background:#f6f7f5;border-radius:10px;white-space:pre-wrap;">{{ $data['poruka'] }}</div>
</td></tr>
<tr><td style="padding:16px 24px 24px;border-top:1px solid #e3e7e4;font-size:13px;color:#5b6470;">{{ $data['email'] !== '' ? 'Odgovorom na ovu poruku pišete pošiljaocu.' : 'Pošiljalac je ostavio telefon, pozovite ga.' }}</td></tr>
</table>
</body>
</html>
