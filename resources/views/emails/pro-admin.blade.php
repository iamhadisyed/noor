<!doctype html>
<html lang="en">
<body style="margin:0;background:#eef2f5;font-family:Arial,Helvetica,sans-serif;color:#120c15;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px;">
    <tr><td align="center">
      <table width="480" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:28px;overflow:hidden;">
        <tr><td style="background:#cabaef;padding:28px 32px;font-size:22px;font-weight:bold;">Kindo</td></tr>
        <tr><td style="padding:32px;">
          <p style="margin:0 0 8px;font-size:18px;font-weight:bold;">{{ $kind === "paid" ? "A parent says they have paid" : "A parent asked for Kindo Pro" }}</p>
          <p style="margin:0 0 8px;font-size:15px;line-height:22px;"><b>{{ $name }}</b> ({{ $email }}){{ $phone ? " - ".$phone : "" }}</p>
          @if($detail)<p style="margin:0 0 16px;color:#120c15;font-size:15px;line-height:22px;white-space:pre-line;">{{ $detail }}</p>@endif
          <p style="margin:0;color:#5d5668;font-size:15px;line-height:22px;">Open the admin console, Upgrades, to {{ $kind === "paid" ? "check the payment and switch the account to Pro" : "see the request. They have been emailed how to pay" }}.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
