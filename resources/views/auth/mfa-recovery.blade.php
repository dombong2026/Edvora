@extends('layouts.app')
@section('title', 'Save your recovery codes')
@section('content')
<section class="mx-auto max-w-2xl rounded-3xl border border-amber-200 bg-white p-8 shadow-sm dark:border-amber-900 dark:bg-slate-900">
    <h1 class="text-2xl font-bold">Save your recovery codes now</h1>
    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-300">These eight single-use recovery codes will not be shown again. Store them offline in a trusted password manager or secure document. Anyone with a code and your password could access the account.</p>
    <div class="mt-6 grid grid-cols-1 gap-2 rounded-xl bg-slate-50 p-5 font-mono text-base font-semibold tracking-wider sm:grid-cols-2 dark:bg-slate-950">
        @foreach($codes as $code)
            <div class="select-all">{{ $code }}</div>
        @endforeach
    </div>
    <a href="{{ route('dashboard') }}" class="mt-6 inline-flex rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">I have stored my recovery codes</a>
</section>
@endsection
