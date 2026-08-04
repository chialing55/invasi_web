@props(['target'])

<button type="button"
    data-password-toggle="{{ $target }}"
    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-gray-500 hover:text-gray-800"
    aria-label="顯示密碼"
    aria-pressed="false">
    <svg data-password-show class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
        <circle cx="12" cy="12" r="2.75" />
    </svg>
    <svg data-password-hide class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.6 5.4A9.8 9.8 0 0 1 12 5.25c6 0 9.75 6.75 9.75 6.75a17.5 17.5 0 0 1-3.05 3.85M6.1 6.1C3.7 7.85 2.25 12 2.25 12S6 18.75 12 18.75c1.45 0 2.75-.4 3.9-1" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
    </svg>
</button>
