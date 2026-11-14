<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Visitor check-in</h2>
    <p class="mt-2 text-xs leading-6 text-slate-500 dark:text-slate-400">Record the minimum information needed. The platform creates arrival and departure timestamps; no identification document is collected.</p>
    <form method="POST" action="{{ route('workspaces.store', [$institution, 'reception']) }}" class="mt-5 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
        @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}">
        <div><label for="visitor-name" class="mb-2 block text-sm font-semibold">Visitor name</label><input id="visitor-name" name="visitor_name" required maxlength="140" value="{{ old('visitor_name') }}" class="edvora-field">@error('visitor_name')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="visitor-purpose" class="mb-2 block text-sm font-semibold">Visit purpose</label><input id="visitor-purpose" name="purpose" required maxlength="180" value="{{ old('purpose') }}" class="edvora-field">@error('purpose')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <button type="submit" class="edvora-primary">Check in</button>
    </form>
</section>
<section class="edvora-surface overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800"><h2 class="text-lg font-bold">Visitor register</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[650px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50"><tr><th class="px-5 py-3">Visitor</th><th class="px-5 py-3">Purpose</th><th class="px-5 py-3">Arrival</th><th class="px-5 py-3">Departure</th><th class="px-5 py-3">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($records as $record)
                    <tr>
                        <td class="px-5 py-4 font-semibold">{{ $record->visitor_name }}</td>
                        <td class="px-5 py-4">{{ $record->purpose }}</td>
                        <td class="px-5 py-4 text-xs">{{ \Carbon\Carbon::parse($record->checked_in_at)->format('d M Y H:i') }}</td>
                        <td class="px-5 py-4 text-xs">{{ $record->checked_out_at ? \Carbon\Carbon::parse($record->checked_out_at)->format('d M Y H:i') : 'On site' }}</td>
                        <td class="px-5 py-4">
                            @if($record->checked_out_at === null)
                                <form method="POST" action="{{ route('workspaces.transition', [$institution, 'reception', $record->id]) }}">
                                    @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="action" value="checkout">
                                    <button type="submit" class="edvora-primary">Check out</button>
                                </form>
                            @else
                                <span class="text-xs text-slate-500">Completed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No visits match the current filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $records->links() }}</div>@endif
</section>