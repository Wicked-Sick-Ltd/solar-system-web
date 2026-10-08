Public Universe — {{ $category }}

Reply address: {{ $replyEmail ?? 'Not supplied; no reply requested.' }}

{{-- This view is text/plain only: preserve bug-report snippets verbatim. --}}
{!! $feedbackText !!}
