<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f7f7f7;font-family:'Poppins',Arial,sans-serif;">
<table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="background:#fff;border-radius:8px;margin:32px auto;box-shadow:0 2px 8px rgba(0,0,0,.08);">
    <tr>
        <td style="background:#ff7713;padding:24px 32px;border-radius:8px 8px 0 0;text-align:center;">
            <h2 style="color:#fff;margin:0;font-size:20px;">Upcoming Payment Reminder</h2>
        </td>
    </tr>
    <tr>
        <td style="padding:32px;">
            <p style="font-size:15px;color:#454545;margin:0 0 16px;">Dear {{ $guestName }},</p>
            <p style="font-size:15px;color:#454545;margin:0 0 20px;">
                This is a friendly reminder that your upcoming payment for <strong>{{ $propertyName }}</strong> is due in <strong>7 days</strong>.
            </p>

            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border:1px solid #ff7713;border-radius:6px;margin-bottom:24px;">
                <tr>
                    <th colspan="2" style="padding:10px;background:#ff7713;color:#fff;font-size:14px;text-align:center;">Payment Details</th>
                </tr>
                <tr>
                    <td style="padding:10px;border:1px solid #ff7713;font-weight:bold;font-size:14px;color:#000;width:40%;">Amount Due</td>
                    <td style="padding:10px;border:1px solid #ff7713;font-size:14px;color:#000;">{!! $currency !!}{{ number_format($amount, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:10px;border:1px solid #ff7713;font-weight:bold;font-size:14px;color:#000;">Due Date</td>
                    <td style="padding:10px;border:1px solid #ff7713;font-size:14px;color:#000;">{{ \Carbon\Carbon::parse($dueDate)->format('F j, Y') }}</td>
                </tr>
                <tr>
                    <td style="padding:10px;border:1px solid #ff7713;font-weight:bold;font-size:14px;color:#000;">Booking Reference</td>
                    <td style="padding:10px;border:1px solid #ff7713;font-size:14px;color:#000;">#{{ $bookingId }}</td>
                </tr>
                <tr>
                    <td style="padding:10px;border:1px solid #ff7713;font-weight:bold;font-size:14px;color:#000;">Property</td>
                    <td style="padding:10px;border:1px solid #ff7713;font-size:14px;color:#000;">{{ $propertyName }}</td>
                </tr>
                <tr>
                    <td style="padding:10px;border:1px solid #ff7713;font-weight:bold;font-size:14px;color:#000;">Check-in</td>
                    <td style="padding:10px;border:1px solid #ff7713;font-size:14px;color:#000;">{{ $checkin }}</td>
                </tr>
            </table>

            <p style="font-size:14px;color:#717171;background:#fff8f2;border-left:4px solid #ff7713;padding:12px 16px;border-radius:4px;margin:0 0 20px;">
                <strong>No action needed</strong> — this payment will be automatically charged to your card on file on {{ \Carbon\Carbon::parse($dueDate)->format('F j, Y') }}.
                Please ensure your card has sufficient funds available.
            </p>

            <p style="font-size:14px;color:#454545;margin:0;">If you have any questions, please reply to this email or contact us directly.</p>
        </td>
    </tr>
    <tr>
        <td style="padding:16px 32px;background:#f7f7f7;border-radius:0 0 8px 8px;text-align:center;">
            <p style="font-size:12px;color:#999;margin:0;">This is an automated reminder. Your card will be charged automatically.</p>
        </td>
    </tr>
</table>
</body>
</html>
