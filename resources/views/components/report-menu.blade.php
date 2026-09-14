@props(['mobile' => false])

<details {{ $attributes->class(['relative', 'w-full' => $mobile]) }}>
    <summary class="btn-monitor list-none [&::-webkit-details-marker]:hidden {{ $mobile ? 'w-full justify-center py-2.5' : '' }}"
             aria-label="Download current bias report">
        <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14a2 2 0 002-2v-3M3 16v3a2 2 0 002 2" />
        </svg>
        <span>{{ $mobile ? 'Download Bias Report' : 'Report' }}</span>
    </summary>
    <div class="absolute right-0 z-50 mt-2 w-72 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-surface)] p-2 shadow-2xl">
        <div class="border-b border-[var(--color-border-subtle)] px-2 pb-2 pt-1">
            <strong class="block text-xs text-[var(--color-text-primary)]">Current stored bias report</strong>
            <span class="text-[10px] leading-snug text-[var(--color-text-muted)]">No market-data or AI credits are used.</span>
        </div>
        <div class="mt-1 grid gap-1">
            <a href="{{ route('reports.current', ['format' => 'pdf']) }}" class="rounded-lg px-2.5 py-2 text-xs text-[var(--color-text-secondary)] transition hover:bg-[var(--color-bg-page-secondary)] hover:text-[var(--color-text-primary)]">
                <strong class="block">PDF report</strong><span class="text-[10px] text-[var(--color-text-muted)]">Concise, polished snapshot for sharing</span>
            </a>
            <a href="{{ route('reports.current', ['format' => 'docx']) }}" class="rounded-lg px-2.5 py-2 text-xs text-[var(--color-text-secondary)] transition hover:bg-[var(--color-bg-page-secondary)] hover:text-[var(--color-text-primary)]">
                <strong class="block">Word document</strong><span class="text-[10px] text-[var(--color-text-muted)]">Editable professional summary</span>
            </a>
            <a href="{{ route('reports.current', ['format' => 'print']) }}" target="_blank" rel="noopener" class="rounded-lg px-2.5 py-2 text-xs text-[var(--color-text-secondary)] transition hover:bg-[var(--color-bg-page-secondary)] hover:text-[var(--color-text-primary)]">
                <strong class="block">Print preview</strong><span class="text-[10px] text-[var(--color-text-muted)]">Themeable, print-ready report</span>
            </a>
        </div>
    </div>
</details>
