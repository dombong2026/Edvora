@extends('layouts.app')
@section('title', 'Student lifecycle review')
@section('content')
<header class="mb-8">
    <p class="text-xs font-bold uppercase tracking-[.18em] text-blue-600 dark:text-blue-400">{{ $institution->name }} · Student lifecycle</p>
    <h1 class="mt-2 text-3xl font-extrabold tracking-tight">{{ $profile->display_name }}</h1>
    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $profile->student_number }} · {{ $profile->campus->name }} · {{ ucfirst($profile->status) }}</p>
    <a class="mt-4 inline-flex text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400" href="{{ route('students.show', [$institution, $profile->id]) }}">Back to student record</a>
</header>
@if($canRequest && $profile->status === 'active')
<section class="edvora-surface p-6" aria-labelledby="lifecycle-form">
    <h2 id="lifecycle-form" class="text-lg font-bold">Request a student record change</h2>
    <p class="mt-2 text-sm leading-7 text-slate-500 dark:text-slate-400">The current record remains unchanged until a separate authorized leader approves the request. Withdrawals stop active enrollment; promotions add an enrollment to a different class. Only unlinked students without guardian or enrollment relationships can move between campuses automatically.</p>
    <form method="POST" action="{{ route('students.lifecycle.store', [$institution, $profile->id]) }}" class="mt-5 grid gap-4 md:grid-cols-2">
        @csrf
        <div>
            <label for="lifecycle-kind" class="mb-2 block text-sm font-semibold">Change type</label>
            <select id="lifecycle-kind" name="kind" class="edvora-field" required>
                <option value="">Select action</option>
                @foreach(\App\Services\StudentLifecycleService::KINDS as $kind)
                    <option value="{{ $kind }}" @selected(old('kind') === $kind)>{{ ucfirst($kind) }}</option>
                @endforeach
            </select>
            @error('kind')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="lifecycle-target-section" class="mb-2 block text-sm font-semibold">Destination class (promotion only)</label>
            <select id="lifecycle-target-section" name="target_section_id" class="edvora-field">
                <option value="">Not applicable</option>
                @foreach($classes as $class)
                    <option value="{{ $class->id }}" @selected((string)old('target_section_id') === (string)$class->id)>{{ $class->code }} · {{ $class->name }}</option>
                @endforeach
            </select>
            @error('target_section_id')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="lifecycle-target-campus" class="mb-2 block text-sm font-semibold">Destination campus (transfer only)</label>
            <select id="lifecycle-target-campus" name="target_campus_id" class="edvora-field">
                <option value="">Not applicable</option>
                @foreach($campuses as $campus)
                    <option value="{{ $campus->id }}" @selected((string)old('target_campus_id') === (string)$campus->id)>{{ $campus->name }}</option>
                @endforeach
            </select>
            @error('target_campus_id')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="lifecycle-reason" class="mb-2 block text-sm font-semibold">Administrative reason</label>
            <textarea id="lifecycle-reason" name="reason" rows="3" minlength="10" maxlength="255" required class="edvora-field" placeholder="Include a brief, policy-appropriate reason; avoid sensitive student details.">{{ old('reason') }}</textarea>
            @error('reason')<p role="alert" class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2"><button type="submit" class="edvora-primary">Submit for independent review</button></div>
    </form>
</section>
@endif
<section class="edvora-surface mt-6 overflow-hidden">
    <div class="border-b border-slate-200 px-6 py-5 dark:border-slate-800"><h2 class="text-lg font-bold">Review history</h2></div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50"><tr><th class="px-5 py-3">Type</th><th class="px-5 py-3">Reason</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Applied</th><th class="px-5 py-3">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($history as $row)
                <tr><td class="px-5 py-4 font-bold">{{ ucfirst($row->kind) }}</td>
                    <td class="max-w-xs break-words px-5 py-4">{{ $row->reason }}</td>
                    <td class="px-5 py-4">{{ ucfirst($row->status) }}</td>
                    <td class="px-5 py-4">{{ $row->applied_at ? 'Yes' : 'No' }}</td>
                    <td class="px-5 py-4">
                        @if($canReview && $row->status === 'pending' && (int)$row->requested_by !== (int)auth()->id())
                            <div class="flex gap-2">
                                @foreach(['approve' => 'Approve & apply', 'reject' => 'Reject'] as $decision => $title)
                                <form action="{{ route('students.lifecycle.review', [$institution, $profile->id, $row->id]) }}" method="POST">
                                    @csrf<input type="hidden" name="decision" value="{{ $decision }}">
                                    <button class="{{ $decision === 'approve' ? 'edvora-primary' : 'edvora-secondary' }}" type="submit">{{ $title }}</button>
                                </form>
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $row->status === 'pending' ? 'Independent review pending' : 'Reviewed' }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No lifecycle changes requested yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($history->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $history->links() }}</div>@endif
</section>
@endsection
