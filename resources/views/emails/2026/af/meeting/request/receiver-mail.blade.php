<x-mail::message>

{{-- <img src="https://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/Eshot-header.jpg" alt="app" width="600" style="margin-top:25px;display:block;max-width:100%;width:100%;height:auto;border:0;outline:none;text-decoration:none;"> --}}

<p class="normal" style="margin-top: 15px;">Hi {{ $details['receiverName'] }},</p>

<p class="normal" style="margin-top: 15px;">You have received a new meeting request from <strong>{{ $details['requesterName'] }}</strong> via the <strong>{{ $details['eventName'] }}</strong> networking app.</p>

<p class="normal" style="margin-top: 15px;">Meeting Details:</p>
<ul class="list">
    <li style="margin-top: 5px;">Title: {{ $details['meetingTitle'] }}</li>
    <li style="margin-top: 5px;">Date: {{ $details['meetingDate'] }}</li>
    <li style="margin-top: 5px;">Time: {{ $details['meetingStartTime'] }} - {{ $details['meetingEndTime'] }}</li>
    <li style="margin-top: 5px;">Location: {{ $details['meetingLocation'] }}</li>
    <li style="margin-top: 5px;">Notes: {{ $details['meetingNotes'] ?? 'None' }}</li>
    
</ul>

@if($details['isAttendee'])
<p class="normal" style="margin-top: 15px;">To respond to this meeting request, please open the networking app and accept or decline the request.</p>
@else
<p class="normal" style="margin-top: 15px;">To respond to this meeting request, click the button below.</p>
<x-mail::button :url="$details['meetingRespondLink']" color="registration">
Respond to Meeting Request
</x-mail::button>
@endif

<p class="normal" style="margin-top: 15px;">We look forward to helping you connect meaningfully during the event.</p>

<p class="normal" style="margin-top: 15px;">Best regards,</p>
<p class="normal" style="margin-top: 5px;">GPCA </p>
</x-mail::message>