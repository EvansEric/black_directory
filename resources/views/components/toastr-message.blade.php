@props([
    'message',
    'type' => 'success',
])

<div
    x-data
    x-init="$nextTick(() => window.toastr.show(@js($message), @js($type)))"
    hidden
></div>
