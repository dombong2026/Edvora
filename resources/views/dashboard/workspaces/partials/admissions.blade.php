<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Add an admissions enquiry</h2>
    <p class="mt-2 text-xs leading-6 text-slate-500 dark:text-slate-400">Campus-restricted prospect tracking. No email, SMS, account creation or automatic enrolment happens here.</p>
    <form method="POST" action="{{ route('workspaces.store', [$institution, 'admissions']) }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}">
        <div><label for="enquiry-name" class="mb-2 block text-sm font-semibold">Contact name</label><input id="enquiry-name" name="contact_name" required maxlength="140" value="{{ old('contact_name') }}" class="edvora-field">@error('contact_name')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="enquiry-email" class="mb-2 block text-sm font-semibold">Email</label><input id="enquiry-email" name="contact_email" type="email" required maxlength="255" value="{{ old('contact_email') }}" class="edvora-field">@error('contact_email')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="enquiry-program" class="mb-2 block text-sm font-semibold">Interested program</label><input id="enquiry-program" name="program" required maxlength="160" value="{{ old('program') }}" class="edvora-field">@error('program')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="flex items-end"><button type="submit" class="edvora-primary w-full">Save enquiry</button></div>
    </form>
</section>
<section class="edvora-surface overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800"><h2 class="text-lg font-bold">Enquiry pipeline</h2><p class="mt-1 text-xs text-slate-500">Stages: New → Contacted → Qualified → Closed. Closure may happen earlier.</p></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[680px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50"><tr><th class="px-5 py-3">Contact</th><th class="px-5 py-3">Program</th><th class="px-5 py-3">Stage</th><th class="px-5 py-3">Next action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($records as $record)
                    <tr>
                        <td class="px-5 py-4"><p class="font-semibold">{{ $record->contact_name }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $record->contact_email }}</p></td>
                        <td class="px-5 py-4">{{ $record->program }}</td>
                        <td class="px-5 py-4 font-semibold">{{ ucfirst($record->stage) }}</td>
                        <td class="px-5 py-4"><div class="flex flex-wrap gap-2">
                            @php $next = match($record->stage) { 'new' => 'contacted', 'contacted' => 'qualified', 'qualified' => 'closed', default => null }; @endphp
                            @if($next)
                                <form method="POST" action="{{ route('workspaces.transition', [$institution, 'admissions', $record->id]) }}">@csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="action" value="{{ $next }}"><button type="submit" class="edvora-primary">Mark {{ str_replace('_', ' ', $next) }}</button></form>
                                @if($next !== 'closed')
                                    <form method="POST" action="{{ route('workspaces.transition', [$institution, 'admissions', $record->id]) }}">@csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="action" value="closed"><button type="submit" class="edvora-secondary">Close</button></form>
                                @endif
                            @else
                                <span class="text-xs text-slate-500">Closed</span>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No admissions enquiries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $records->links() }}</div>@endif
</section>