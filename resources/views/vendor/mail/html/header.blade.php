@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
                <img src="{{ asset('statis/images/logo.png') }}" class="logo" alt="lspp306 Logo">
            @else
                {!! $slot !!}
            @endif
        </a>
    </td>
</tr>
