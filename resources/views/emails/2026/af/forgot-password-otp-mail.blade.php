<x-mail::message>

{{-- <img src="https://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/Eshot-header.jpg" alt="app" width="600" style="margin-top:25px;display:block;max-width:100%;width:100%;height:auto;border:0;outline:none;text-decoration:none;"> --}}

<p class="normal" style="margin-top: 15px;">Dear {{ $details['name'] }},</p>

<p class="normal" style="margin-top: 15px;">We received a request to reset your password for the <strong>{{ $details['eventName'] }}</strong> account. Please use the following OTP (One-Time Password) to reset your password:</p>

<p class="normal" style="margin-top: 15px;"><strong>OTP:</strong> {{ $details['otp'] }}</p>

<p class="normal" style="margin-top: 15px;">This OTP is valid for 10 minutes. Please enter this OTP in the app to proceed with resetting your password. If you did not request this password reset, please disregard this email. If you continue to receive such emails or have any concerns, please contact Jhoanna Kilat immediately at <a href="mailto:jhoanna@gpca.org.ae">jhoanna@gpca.org.ae</a> or call +971 4 451 0666 ext. 151.</p>

<p class="normal" style="margin-top: 15px;">Thank you for your attention to this matter. We appreciate your cooperation in ensuring the security of your account.</p>

<p class="normal" style="margin-top: 15px;">Kind regards,</p>
<p class="normal" style="margin-top: 5px;">GPCA Team</p>
</x-mail::message>