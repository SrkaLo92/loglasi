{{-- Google: Search Console verification (when set in the admin) and the Google tag, only in production.
     The tag starts with every consent denied; the visitor's choice in the cookie bar updates it. --}}
@use('App\Support\Settings')
@php
	$lgVerify = trim((string) Settings::get('google_site_verification'));
	$lgTag = trim((string) Settings::get('google_tag_id'));
	$lgTagOn = app()->environment('production') && preg_match('/^(GT|G|AW)-[A-Z0-9]{4,20}$/', $lgTag) === 1;
@endphp
@if ($lgVerify !== '')
<meta name="google-site-verification" content="{{ $lgVerify }}">
@endif
@if ($lgTagOn)
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', {analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500});
try { if (localStorage.getItem('lg-kolacici') === 'da') { gtag('consent', 'update', {analytics_storage: 'granted'}); } } catch (e) {}
gtag('js', new Date());
gtag('config', '{{ $lgTag }}');
// The tag (~180 KB) loads after the page, so it never delays the first paint; queued calls run then.
window.addEventListener('load', function () {
	var s = document.createElement('script');
	s.async = true;
	s.src = 'https://www.googletagmanager.com/gtag/js?id={{ $lgTag }}';
	document.head.appendChild(s);
});
</script>
@endif
