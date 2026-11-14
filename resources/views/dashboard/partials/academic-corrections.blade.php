@if($canRequest || $canReview)
<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" aria-label="Controlled corrections">
    <h2 class="text-lg font-bold">Controlled record corrections</h2>
    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Published records cannot be silently edited. The assigned teacher requests a correction; a different MFA-enabled institutional officer reviews it. The original value and the decision are retained for audit.</p>

    @if($canRequest)
    <div class="mt-6">
        <h3 class="text-sm font-bold">Request a correction</h3>
        <div class="mt-3 divide-y divide-slate-200 dark:divide-slate-800">
            @forelse($items as $record)
                <form method="POST" action="{{ route('corrections.store', [$institution, $section]) }}" class="grid gap-3 py-4 md:grid-cols-[minmax(0,1fr)_minmax(0,140px)_minmax(0,190px)_auto] md:items-end">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <input type="hidden" name="target_id" value="{{ $record->id }}">
                    <div class="text-sm">
                        <p class="font-semibold">{{ $record->enrollment->student->display_name }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $record->enrollment->student->student_number }}
                            · Current: {{ $kind === 'attendance' ? ucfirst($record->status) : $record->points_earned }}
                        </p>
                    </div>
                    <div>
                        <label for="correction-value-{{ $kind }}-{{ $record->id }}" class="mb-1 block text-xs font-semibold">New value</label>
                        @if($kind === 'attendance')
                            <select id="correction-value-{{ $kind }}-{{ $record->id }}" name="new_value" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                                <option value="">Select</option>
                                @foreach(\App\Services\ClassAttendanceService::STATUSES as $status)
                                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="correction-value-{{ $kind }}-{{ $record->id }}" name="new_value" type="number" min="0" max="{{ $assessment->max_points }}" step="0.01" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                        @endif
                    </div>
                    <div>
                        <label for="correction-reason-{{ $kind }}-{{ $record->id }}" class="mb-1 block text-xs font-semibold">Reason</label>
                        <select id="correction-reason-{{ $kind }}-{{ $record->id }}" name="reason_code" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950">
                            @foreach(\App\Services\AcademicCorrectionService::REASONS as $reason)
                                <option value="{{ $reason }}">{{ ucfirst(str_replace('_', ' ', $reason)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-xl border border-blue-200 px-4 py-2.5 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950">Request review</button>
                </form>
            @empty
                <p class="py-5 text-sm text-slate-500">There are no finalized marks available for correction.</p>
            @endforelse
        </div>
        @error('new_value') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('reason_code') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('correction') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    @endif

    @if($pendingCorrections->isNotEmpty())
        <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800">
            <h3 class="text-sm font-bold">Pending independent reviews</h3>
            <ul class="mt-3 divide-y divide-slate-200 dark:divide-slate-800">
                @foreach($pendingCorrections as $correction)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="text-sm">
                            <p class="font-semibold">{{ $kind === 'attendance' ? $correction->attendanceMark?->enrollment?->student?->display_name : $correction->assessmentResult?->enrollment?->student?->display_name }}</p>
                            <p class="mt-1 text-xs text-slate-500">Requested by {{ $correction->requester?->name }} · {{ ucfirst(str_replace('_', ' ', $correction->reason_code)) }}</p>
                            <p class="mt-1 text-xs text-slate-500">Original {{ $correction->from_value }} → Proposed {{ $correction->to_value }}</p>
                        </div>
                        @if($canReview && (int)$correction->requested_by !== (int)auth()->id())
                            <div class="flex flex-wrap gap-2">
                                @foreach(['approve' => 'Approve', 'reject' => 'Reject'] as $decision => $label)
                                    <form method="POST" action="{{ route('corrections.review', [$institution, $section, $correction]) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="{{ $decision }}">
                                        <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800">{{ $label }}</button>
                                    </form>
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs font-semibold text-slate-500">Awaiting separate reviewer</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
@endif
