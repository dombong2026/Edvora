<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Add a catalog item</h2>
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Codes are unique within each campus. Stock counts represent physical copies available for lending.</p>
    <form method="POST" action="{{ route('workspaces.store', [$institution, 'library']) }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @csrf
        <input type="hidden" name="campus_id" value="{{ $campus->id }}">
        <div><label for="library-code" class="mb-2 block text-sm font-semibold">Catalog code</label><input id="library-code" name="code" required maxlength="40" pattern="[A-Za-z0-9_-]+" value="{{ old('code') }}" class="edvora-field">@error('code')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div class="xl:col-span-2"><label for="library-title" class="mb-2 block text-sm font-semibold">Title</label><input id="library-title" name="title" required maxlength="180" value="{{ old('title') }}" class="edvora-field">@error('title')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="library-author" class="mb-2 block text-sm font-semibold">Author (optional)</label><input id="library-author" name="author" maxlength="140" value="{{ old('author') }}" class="edvora-field"></div>
        <div><label for="library-stock" class="mb-2 block text-sm font-semibold">Copies</label><input id="library-stock" name="stock_total" type="number" min="1" max="1000" required value="{{ old('stock_total', 1) }}" class="edvora-field">@error('stock_total')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <button type="submit" class="edvora-primary sm:justify-self-start">Add item</button>
    </form>
</section>
<section class="edvora-surface overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
        <h2 class="text-lg font-bold">Campus catalog</h2><span class="text-xs text-slate-500 dark:text-slate-400">Use Issue loan to record a real checkout</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[740px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50"><tr><th class="px-5 py-3">Code</th><th class="px-5 py-3">Title / author</th><th class="px-5 py-3">Available / total</th><th class="px-5 py-3">Loan action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($records as $record)
                    <tr>
                        <td class="px-5 py-4 font-mono text-xs">{{ $record->code }}</td>
                        <td class="px-5 py-4"><p class="font-semibold">{{ $record->title }}</p><p class="mt-1 text-xs text-slate-500">{{ $record->author ?: 'Author not specified' }}</p></td>
                        <td class="px-5 py-4 font-bold">{{ $available[$record->id] }} / {{ $record->stock_total }}</td>
                        <td class="px-5 py-4">
                            @if($available[$record->id] > 0)
                                <details class="group">
                                    <summary class="cursor-pointer text-sm font-bold text-blue-600 hover:underline dark:text-blue-400">Issue loan</summary>
                                    <form method="POST" action="{{ route('workspaces.loans.issue', [$institution, $record->id]) }}" class="mt-3 grid gap-2">
                                        @csrf
                                        <input type="hidden" name="campus_id" value="{{ $campus->id }}">
                                        <label class="text-xs font-semibold" for="borrower-{{ $record->id }}">Borrower's campus-account email</label>
                                        <input id="borrower-{{ $record->id }}" type="email" name="borrower_email" required maxlength="255" autocomplete="off" class="edvora-field">
                                        <label class="text-xs font-semibold" for="due-{{ $record->id }}">Due date</label>
                                        <input id="due-{{ $record->id }}" type="date" name="due_on" required min="{{ now()->addDay()->toDateString() }}" max="{{ now()->addYear()->toDateString() }}" value="{{ now()->addDays(14)->toDateString() }}" class="edvora-field">
                                        <button type="submit" class="edvora-primary">Confirm issue</button>
                                    </form>
                                </details>
                            @else
                                <span class="text-xs text-slate-500 dark:text-slate-400">All copies on loan</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">No catalog items found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $records->links() }}</div>@endif
</section>
<section class="edvora-surface overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800"><h2 class="text-lg font-bold">Active loans</h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Most recent 30 open loans for the selected campus</p></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[600px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50"><tr><th class="px-5 py-3">Item</th><th class="px-5 py-3">Borrower</th><th class="px-5 py-3">Due</th><th class="px-5 py-3">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($loans as $loan)
                    <tr><td class="px-5 py-4 font-semibold">{{ $loan->title }}</td><td class="px-5 py-4">{{ $loan->name }}<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $loan->email }}</p></td><td class="px-5 py-4">{{ \Carbon\Carbon::parse($loan->due_on)->format('d M Y') }}</td><td class="px-5 py-4">
                        <form method="POST" action="{{ route('workspaces.loans.return', [$institution, $loan->id]) }}">
                            @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}">
                            <button type="submit" class="edvora-secondary">Return item</button>
                        </form>
                    </td></tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No active loans for this campus.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>