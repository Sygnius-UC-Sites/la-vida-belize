<title></title>
<meta http–equiv="Content-Type" content="text/html; charset=UTF-8" />
<style type="text/css">
    body { margin:0; padding:0; font-family: 'Poppins', sans-serif; }
    table { border-spacing:0; }
    td { border-collapse: collapse; }
    td { border: 1px solid #e53e3e; font-weight: bold; font-size: 15px; }
</style>
<table align="center" border="0" cellpadding="0" cellspacing="0" style="width:90%; margin: auto;">
    <tbody>
        <tr>
            <td align="center" bgcolor="#ffffff" style="padding:15px;" valign="top">
                <div><span style="display: block; font-size: 28px; font-weight: 600; margin: 20px 0 10px 0; color: #e53e3e; font-family: 'Poppins', sans-serif;">⚠ Scheduled Payment Failed</span></div>
            </td>
        </tr>
        <tr>
            <td align="left" bgcolor="#f9f9f9" style="padding:20px;" valign="top">
                <p style="font-size: 15px; color: #000; line-height: 24px; margin: 0 0 15px 0; font-family: 'Poppins', sans-serif;">
                    Hey {{ ModelHelper::getDataFromSetting('mailer_admin_name') }},
                </p>
                <p style="font-size: 15px; color: #000; line-height: 24px; margin: 0 0 20px 0;">
                    A scheduled installment payment has failed after {{ $attempts }} attempts and requires your attention.
                </p>
                <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tbody>
                        <tr>
                            <th colspan="2" align="center" style="padding: 10px; background: #e53e3e; color: #fff; font-size: 15px;" valign="top"><strong>Payment Details</strong></th>
                        </tr>
                        <tr>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; border-right: 0; color:#000;" valign="top"><strong>Booking ID:</strong></td>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; color:#000;" valign="top">#{{ $bookingId }}</td>
                        </tr>
                        <tr>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; border-right: 0; color:#000;" valign="top"><strong>Guest Name:</strong></td>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; color:#000;" valign="top">{{ $guestName }}</td>
                        </tr>
                        <tr>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; border-right: 0; color:#000;" valign="top"><strong>Guest Email:</strong></td>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; color:#000;" valign="top">{{ $guestEmail }}</td>
                        </tr>
                        <tr>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; border-right: 0; color:#000;" valign="top"><strong>Amount Due:</strong></td>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; color:#000;" valign="top">{{ strtoupper($currency) }} {{ number_format($amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; border-right: 0; color:#000;" valign="top"><strong>Due Date:</strong></td>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; color:#000;" valign="top">{{ $dueDate }}</td>
                        </tr>
                        <tr>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; border-right: 0; color:#000;" valign="top"><strong>PMS:</strong></td>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; color:#000;" valign="top">{{ strtoupper($pmsSource) }} (booking: {{ $pmsBookingId ?: 'N/A' }})</td>
                        </tr>
                        <tr>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; border-right: 0; color:#000;" valign="top"><strong>Failure Reason:</strong></td>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; color:#e53e3e;" valign="top">{{ $errorMessage }}</td>
                        </tr>
                        <tr>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; border-right: 0; color:#000;" valign="top"><strong>Attempts Made:</strong></td>
                            <td align="left" style="padding: 10px; border: 1px solid #e53e3e; color:#000;" valign="top">{{ $attempts }} of 3</td>
                        </tr>
                    </tbody>
                </table>
                <p style="font-size: 14px; color: #666; margin-top: 20px; line-height: 22px;">
                    Please log in to the admin panel to review this booking and arrange alternative payment if needed.
                </p>
            </td>
        </tr>
        <tr>
            <td align="center" bgcolor="#ffffff" style="padding:15px;" valign="top">
                <p style="font-size: 14px; color: #000; line-height: 24px; margin: 0;">
                    {!! ModelHelper::getDataFromSetting('mail_footer') !!}
                </p>
            </td>
        </tr>
    </tbody>
</table>
