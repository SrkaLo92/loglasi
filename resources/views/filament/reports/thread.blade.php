@php
	/** @var \App\Models\Report $report */
	$reporter = $report->reporter;
@endphp
<div style="display:grid;gap:12px;font-size:14px">
	<div>
		<strong>Prijavio:</strong>
		@if ($reporter)
			{{ $reporter->name }} ({{ $reporter->email }}{{ $reporter->phone ? ', '.$reporter->phone : '' }})
		@else
			gost (stara prijava, bez naloga)
		@endif
	</div>
	<div style="padding:10px 12px;border-radius:8px;background:rgba(127,127,127,.1)">
		<div style="font-size:12px;opacity:.7">{{ $report->created_at?->format('j. n. Y. H:i') }} · {{ \App\Services\Admin\ReportHandling::reasonLabel($report->reason) }}</div>
		<div style="white-space:pre-line">{{ $report->details ?: 'Bez opisa.' }}</div>
	</div>
	@foreach ($report->messages as $message)
		<div style="padding:10px 12px;border-radius:8px;{{ $message->from_staff ? 'background:rgba(59,130,246,.12);margin-left:24px' : 'background:rgba(34,197,94,.12);margin-right:24px' }}">
			<div style="font-size:12px;opacity:.7">{{ $message->created_at?->format('j. n. Y. H:i') }} · {{ $message->from_staff ? 'Administrator'.($message->user ? ' ('.$message->user->name.')' : '') : 'Prijavilac' }}</div>
			<div style="white-space:pre-line">{{ $message->body }}</div>
		</div>
	@endforeach
	@if ($report->messages->isEmpty())
		<p style="opacity:.7;margin:0">Još nema poruka. Pitanje stiže prijaviocu u zvonce, push i mejl, a odgovara na sajtu ili u aplikaciji.</p>
	@endif
</div>
