<section class="edvora-surface p-6">
    <h2 class="text-lg font-bold">Create a draft fee proposal</h2>
    <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Proposal amounts are stored accurately in minor units. No live billing, invoices, ledger posting or payment collection occurs.</p>
    <form method="POST" action="{{ route('workspaces.store', [$institution, 'cashier']) }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}">
        <div class="xl:col-span-2"><label for="fee-name" class="mb-2 block text-sm font-semibold">Fee name</label><input id="fee-name" name="name" required maxlength="160" value="{{ old('name') }}" placeholder="Program fee proposal" class="edvora-field">@error('name')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <div><label for="fee-currency" class="mb-2 block text-sm font-semibold">Currency</label><select id="fee-currency" name="currency" required class="edvora-field"><option value="USD" @selected(old('currency', 'USD') === 'USD')>USD</option><option value="KHR" @selected(old('currency') === 'KHR')>KHR</option></select></div>
        <div><label for="fee-amount" class="mb-2 block text-sm font-semibold">Amount (USD or whole KHR)</label><input id="fee-amount" name="amount" required maxlength="16" inputmode="decimal" value="{{ old('amount') }}" placeholder="25.00" class="edvora-field">@error('amount')<p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
        <button type="submit" class="edvora-primary sm:justify-self-start">Save draft proposal</button>
    </form>
</section>
<section class="edvora-surface overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800"><h2 class="text-lg font-bold">Fee proposal register</h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Reviewed proposals are still non-payable, until an independent billing system is approved.</p></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[650px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50"><tr><th class="px-5 py-3">Proposal</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($records as $record)
                    <tr>
                        <td class="px-5 py-4 font-semibold">{{ $record->name }}</td>
                        <td class="px-5 py-4 font-semibold">{{ $record->currency === 'USD' ? '$'.number_format($record->amount_minor / 100, 2) : number_format($record->amount_minor).' KHR' }}</td>
                        <td class="px-5 py-4">{{ ucfirst($record->status) }}</td>
                        <td class="px-5 py-4">
                            @if($record->status === 'draft')
                                <div class="flex flex-wrap gap-2">
                                    @if($canReviewFees && (int)$record->created_by !== (int)auth()->id())
                                        <form method="POST" action="{{ route('workspaces.transition', [$institution, 'cashier', $record->id]) }}">@csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="action" value="review"><button class="edvora-primary" type="submit">Review</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('workspaces.transition', [$institution, 'cashier', $record->id]) }}">@csrf<input type="hidden" name="campus_id" value="{{ $campus->id }}"><input type="hidden" name="action" value="void"><button class="edvora-secondary" type="submit">Void draft</button></form>
                                </div>
                            @else
                                <span class="text-xs text-slate-500">No further actions</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No fee proposals found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $records->links() }}</div>@endif
</section>