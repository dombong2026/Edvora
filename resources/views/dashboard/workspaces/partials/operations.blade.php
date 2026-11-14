<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Create a campus task</h2>
    <p class="mt-2 text-xs leading-6 text-slate-500 dark:text-slate-400">A shared action register for authorized school leadership and supervisors; each update is recorded.</p>
    <form method="POST" action="{{ route('workspaces.store', [$institution, 'operations']) }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}">
        <div class="xl:col-span-2"><label for="operation-title" class="mb-2 block text-sm font-semibold">Task title</label><input id="operation-title" name="title" required maxlength="180" value="{{ old('title') }}" class="edvora-field">@error('title')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="operation-priority" class="mb-2 block text-sm font-semibold">Priority</label><select id="operation-priority" name="priority" required class="edvora-field"><option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option><option value="high" @selected(old('priority') === 'high')>High</option></select></div>
        <div><label for="operation-due" class="mb-2 block text-sm font-semibold">Due date (optional)</label><input id="operation-due" type="date" name="due_on" min="{{ now()->toDateString() }}" value="{{ old('due_on') }}" class="edvora-field">@error('due_on')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <button type="submit" class="edvora-primary sm:justify-self-start">Add task</button>
    </form>
</section>
<section class="edvora-surface overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800"><h2 class="text-lg font-bold">Tasks in this campus</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[650px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50"><tr><th class="px-5 py-3">Task</th><th class="px-5 py-3">Priority</th><th class="px-5 py-3">Due</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Next action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($records as $record)
                    <tr>
                        <td class="px-5 py-4 font-semibold">{{ $record->title }}</td>
                        <td class="px-5 py-4"><span class="{{ $record->priority === 'high' ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500 dark:text-slate-300' }}">{{ ucfirst($record->priority) }}</span></td>
                        <td class="px-5 py-4 text-xs">{{ $record->due_on ? \Carbon\Carbon::parse($record->due_on)->format('d M Y') : 'Not set' }}</td>
                        <td class="px-5 py-4">{{ ucfirst(str_replace('_', ' ', $record->status)) }}</td>
                        <td class="px-5 py-4"><div class="flex flex-wrap gap-2">
                            @if($record->status === 'open')
                                <form method="POST" action="{{ route('workspaces.transition', [$institution, 'operations', $record->id]) }}">@csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="action" value="in_progress"><button type="submit" class="edvora-primary">Start task</button></form>
                            @endif
                            @if($record->status === 'in_progress')
                                <form method="POST" action="{{ route('workspaces.transition', [$institution, 'operations', $record->id]) }}">@csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="action" value="open"><button type="submit" class="edvora-secondary">Reopen</button></form>
                            @endif
                            @if($record->status !== 'resolved')
                                <form method="POST" action="{{ route('workspaces.transition', [$institution, 'operations', $record->id]) }}">@csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="action" value="resolved"><button type="submit" class="edvora-secondary">Resolve</button></form>
                            @else
                                <span class="text-xs text-slate-500">Complete</span>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No tasks match this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $records->links() }}</div>@endif
</section>