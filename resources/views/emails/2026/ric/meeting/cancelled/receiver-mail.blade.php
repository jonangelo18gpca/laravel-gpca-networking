<x-mail::message>

{{-- <img src="https://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/Eshot-header.jpg" alt="app" width="600" style="margin-top:25px;display:block;max-width:100%;width:100%;height:auto;border:0;outline:none;text-decoration:none;"> --}}

<p class="normal" style="margin-top: 15px;">Hi {{ $details['receiverName'] }},</p>

<p class="normal">The meeting request from <strong>{{ $details['requesterName'] }}</strong> has been cancelled.</p>

@if($details['cancelledReason'])
<p class="normal">Reason: "{{ $details['cancelledReason'] }}"</p>
@endif

<p class="normal">We apologize for any inconvenience this may cause.</p>

<p class="normal" style="margin-top: 15px;">Best regards,</p>
<p class="normal" style="margin-top: 5px;">GPCA </p>
</x-mail::message>
