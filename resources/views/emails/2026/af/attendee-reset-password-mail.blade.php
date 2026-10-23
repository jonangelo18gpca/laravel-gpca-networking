<x-mail::message>

{{-- <img src="https://gpca.org.ae/conferences/rcc/wp-content/uploads/2025/10/Eshot-header.jpg" alt="app" width="600" style="margin-top:25px;display:block;max-width:100%;width:100%;height:auto;border:0;outline:none;text-decoration:none;"> --}}

<p class="normal" style="margin-top: 15px;">Dear {{ $details['name'] }},</p>

<p class="normal" style="margin-top: 15px;">We hope this email finds you well. This is to inform you that your account's password on the <strong>{{ $details['eventName'] }}</strong> has been successfully changed</p>

<p class="normal" style="margin-top: 15px;"><strong>Please Note:</strong></p>
<p class="normal">For security reasons, we recommend that you ensure your new password is strong and unique. Here are a few tips for maintaining your account security:</p>
<ol class="list">
    <li style="margin-top: 5px;">Use a combination of letters, numbers, and special characters.</li>
    <li>Avoid using easily guessable information such as birthdays or common words.</li>
    <li>Change your passwords regularly and avoid reusing passwords for different accounts.</li>
</ol>

<p class="normal" style="margin-top: 15px;">If you did not initiate this password change, please contact Jhoanna Kilat immediately at <a href="mailto:jhoanna@gpca.org.ae">jhoanna@gpca.org.ae</a> or call +971 4 451 0666 ext. 151. Your account's security is our top priority, and we are here to assist you with any concerns you may have.</p>

<p class="normal" style="margin-top: 15px;">Thank you for your attention to this matter. We appreciate your cooperation in ensuring the security of your account.</p>

<p class="normal" style="margin-top: 15px;">Kind regards,</p>
<p class="normal" style="margin-top: 5px;">GPCA Team</p>
</x-mail::message>