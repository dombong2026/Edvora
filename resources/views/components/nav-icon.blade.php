@props(['name'])
<svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
    @case('dashboard')
        <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
        @break
    @case('account')
        <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
        @break
    @case('security')
        <path d="M12 2 4 6v6c0 5 3 8 8 10 5-2 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-4"/>
        @break
    @case('platform')
        <rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10M14 15h3"/>
        @break
    @case('institution')
        <path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/>
        @break
    @case('members')
        <circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M17 5a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5v1"/>
        @break
    @case('academic')
        <path d="M4 5c3-1 5-1 8 1 3-2 5-2 8-1v15c-3-1-5-1-8 1-3-2-5-2-8-1V5ZM12 6v15"/>
        @break
    @case('students')
        <path d="m3 9 9-5 9 5-9 5-9-5ZM6 12v5c4 3 8 3 12 0v-5"/>
        @break
    @case('classrooms')
        <rect x="3" y="4" width="18" height="15" rx="2"/><path d="M7 23v-4M17 23v-4M7 9h10M7 13h6"/>
        @break
    @case('library')
        <path d="M4 4c3-1 5-.5 8 1.5 3-2 5-2.5 8-1.5v15.5c-3-1-5-.5-8 1.5-3-2-5-2.5-8-1.5V4Z"/><path d="M12 5.5V21"/>
        @break
    @case('cashier')
        <rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18M16 15h2M7 15h4"/>
        @break
    @case('reception')
        <rect x="3" y="4" width="18" height="17" rx="2"/><circle cx="12" cy="10" r="2"/><path d="M8 17c.5-2 2-3 4-3s3.5 1 4 3"/>
        @break
    @case('admissions')
        <circle cx="10" cy="8" r="3"/><path d="M3 20v-2c0-3 3-5 7-5 2 0 4 .6 5 2M17 16h6M20 13v6"/>
        @break
    @case('operations')
        <rect x="5" y="4" width="15" height="18" rx="2"/><path d="M9 4V2h7v2M9 10h6m-6 5 2 2 4-4"/>
        @break
    @case('reports')
        <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 17v-4M12 17V8M17 17v-6"/>
        @break
    @case('announcements')
        <rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 10h8M8 14h5M8 18h8M8 3h8"/>
        @break
    @case('helpdesk')
        <path d="M4 12a8 8 0 0 1 16 0v5a2 2 0 0 1-2 2h-3v-7h5M4 12h5v7H6a2 2 0 0 1-2-2v-5ZM12 21h4"/>
        @break
    @case('role')
        <rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="10" r="2"/><path d="M8 17a4 4 0 0 1 8 0"/>
        @break
    @default
        <circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>
@endswitch
</svg>
