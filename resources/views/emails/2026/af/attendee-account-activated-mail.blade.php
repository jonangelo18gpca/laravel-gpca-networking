<x-mail::message>

{{-- <img src="https://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/Eshot-header.jpg" alt="app" width="600" style="margin-top:25px;display:block;max-width:100%;width:100%;height:auto;border:0;outline:none;text-decoration:none;"> --}}

<p class="normal" style="margin-top: 15px;">Dear {{ $details['name'] }},</p>

<p class="normal" style="margin-top: 15px;">Your account for the <strong>{{ $details['eventName'] }}</strong> has been successfully activated. You may now log in to the GPCA Events Networking app using your email address and the password you created. </p>

<p class="normal" style="margin-top: 15px;">If you experience any issues accessing your account, please contact our support team at <a href="mailto:jhoanna@gpca.org.ae">jhoanna@gpca.org.ae</a> or call +971 4 451 0666 ext. 151.</p>

<p class="normal" style="margin-top: 15px;">We look forward to seeing you at the event!</p>

<p class="normal" style="margin-top: 15px;">Kind regards,</p>
<p class="normal" style="margin-top: 5px;">GPCA Team</p>
</x-mail::message>
