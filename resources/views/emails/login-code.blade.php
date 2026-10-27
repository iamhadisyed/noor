<!doctype html>
<html lang="en">
<body style="margin:0;background:#eef2f5;font-family:Arial,Helvetica,sans-serif;color:#120c15;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;">
    <tr><td align="center">
      <table width="440" cellpadding="0" cellspacing="0" style="max-width:440px;background:#ffffff;border-radius:28px;overflow:hidden;">
        <tr><td style="background:#cabaef;padding:28px 32px;font-size:22px;font-weight:bold;">Kindo</td></tr>
        <tr><td style="padding:32px;">
          @if (($purpose ?? 'login') === 'delete')
          <p style="margin:0 0 8px;font-size:18px;font-weight:bold;">Confirm deleting your account</p>
          <p style="margin:0 0 20px;color:#5d5668;font-size:15px;line-height:22px;">Enter this code to permanently delete your Kindo account, your children's profiles, lessons, pictures and voices. This cannot be undone. The code works once and expires in {{ $minutes }} minutes.</p>
          @else
          <p style="margin:0 0 8px;font-size:18px;font-weight:bold;">Your sign-in code</p>
          <p style="margin:0 0 20px;color:#5d5668;font-size:15px;line-height:22px;">Enter this code to sign in. It works once and expires in {{ $minutes }} minutes.</p>
          @endif
          <p style="margin:0 0 24px;text-align:center;font-size:40px;letter-spacing:10px;font-weight:bold;background:#fff0ad;border-radius:20px;padding:18px 0;">{{ $code }}</p>
          <p style="margin:0;color:#8b8a90;font-size:13px;line-height:19px;">If you did not ask for this code, you can ignore this email. Nobody can sign in without it. Kindo will never ask you for it.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
