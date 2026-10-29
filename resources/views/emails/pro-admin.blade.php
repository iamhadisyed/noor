<!doctype html>
<html lang="en">
<body style="margin:0;background:#eef2f5;font-family:Arial,Helvetica,sans-serif;color:#120c15;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;">
    <tr><td align="center">
      <table width="480" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:28px;overflow:hidden;">
        <tr><td style="background:#cabaef;padding:20px 32px;"><table cellpadding="0" cellspacing="0"><tr><td><img src="{{ url('/brand/mark-96.png') }}" width="40" height="40" alt="" style="display:block;border-radius:12px;"></td><td style="padding-left:12px;font-size:22px;font-weight:bold;color:#120c15;">Kindo</td></tr></table></td></tr>
        <tr><td style="padding:32px;">
          <p style="margin:0 0 8px;font-size:18px;font-weight:bold;">{{ $kind === "paid" ? "A parent says they have paid" : "A parent asked for Kindo Pro" }}</p>
          <p style="margin:0 0 8px;font-size:15px;line-height:22px;"><b>{{ $name }}</b> ({{ $email }}){{ $phone ? " - ".$phone : "" }}</p>
          @if($detail)<p style="margin:0 0 16px;color:#120c15;font-size:15px;line-height:22px;white-space:pre-line;">{{ $detail }}</p>@endif
          <p style="margin:0;color:#5d5668;font-size:15px;line-height:22px;">Open the admin console, Upgrades, to {{ $kind === "paid" ? "check the payment and switch the account to Pro" : "see the request. They have been emailed how to pay" }}.</p>
        </td></tr>
        <tr><td style="background:#f3f0fa;padding:14px 32px;font-size:12px;color:#6f6a78;text-align:center;">Kindo · learning that fits every child</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
