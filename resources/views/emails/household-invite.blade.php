<!doctype html>
<html lang="en">
<body style="margin:0;background:#eef2f5;font-family:Arial,Helvetica,sans-serif;color:#120c15;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;">
    <tr><td align="center">
      <table width="440" cellpadding="0" cellspacing="0" style="max-width:440px;background:#ffffff;border-radius:28px;overflow:hidden;">
        <tr><td style="background:#cabaef;padding:20px 32px;"><table cellpadding="0" cellspacing="0"><tr><td><img src="{{ url('/brand/mark-96.png') }}" width="40" height="40" alt="" style="display:block;border-radius:12px;"></td><td style="padding-left:12px;font-size:22px;font-weight:bold;color:#120c15;">Kindo</td></tr></table></td></tr>
        <tr><td style="padding:32px;">
          <p style="margin:0 0 8px;font-size:18px;font-weight:bold;">{{ $inviterName }} invited you</p>
          <p style="margin:0 0 20px;color:#5d5668;font-size:15px;line-height:22px;">You can share {{ $inviterName }}'s children on Kindo: see their lessons and progress, and answer their alerts together. To accept, sign in with this email address (a code will be sent to you) and choose Accept.</p>
          <p style="margin:0 0 24px;text-align:center;"><a href="{{ $url }}" style="display:inline-block;background:#120c15;color:#ffffff;text-decoration:none;font-weight:bold;border-radius:26px;padding:14px 28px;">Open Kindo</a></p>
          <p style="margin:0;color:#8b8a90;font-size:13px;line-height:19px;">If you do not know {{ $inviterName }}, ignore this email. Nothing is shared unless you accept.</p>
        </td></tr>
        <tr><td style="background:#f3f0fa;padding:14px 32px;font-size:12px;color:#6f6a78;text-align:center;">Kindo · learning that fits every child</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
