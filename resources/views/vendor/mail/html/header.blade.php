@props(['url'])
<tr>
<td class="header">
<a href="https://www.gpca.org.ae/" style="display: inline-block;">
@if (trim($slot) === 'GPCA Networking')
{{-- <img src="https://www.gpca.org.ae/wp-content/uploads/2023/08/gpca-networking-logo.png" height="80" class="logo" alt="GPCA Logo"> --}}

<img src="https://www.gpcaforum.com/wp-content/uploads/2026/10/banner_1792997924_VKBfCsOi3B-scaled.jpg" height="80" class="logo" alt="GPCA Logo">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>
