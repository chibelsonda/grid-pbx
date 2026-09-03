@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    $primaryStyle = in_array($color, ['primary', 'blue'], true)
        ? 'background-color: #3f6ad8; border-bottom: 8px solid #3f6ad8; border-left: 18px solid #3f6ad8; border-right: 18px solid #3f6ad8; border-top: 8px solid #3f6ad8;'
        : null;
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" @if ($primaryStyle) style="{{ $primaryStyle }}" @endif>{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
