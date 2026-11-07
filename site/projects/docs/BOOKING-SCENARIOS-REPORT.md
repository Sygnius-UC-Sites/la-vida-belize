# Booking scenarios test report

_Generated Mon, May 4, 2026 8:37 AM_

| Scenario | Result | Summary | Booking |
|---|---|---|---|
| `happy_path_full_pay` | ✅ pass | total $2257.75 status=paid · 12 emails | #6715 |
| `two_instalment_split` | ✅ pass | 2 receipts ($497.2 + $497.20) · status=paid | #6716 |
| `three_instalment_split` | ✅ pass | 3 receipts ($298.32+$298.32+$397.76) · status=paid | #6717 |
| `partial_then_cancel` | ✅ pass | paid $497.2 of $994.4 then cancelled; cancellation email sent | #6718 |
| `enquiry_then_cancel` | ✅ pass | no payments, cancellation email sent | #6719 |
| `gateway_paypal` | ✅ pass | paypal → status=paid · 4 emails | #6720 |
| `gateway_square` | ✅ pass | square → status=paid · 4 emails | #6721 |
| `gateway_authorize` | ✅ pass | authorize → status=paid · 4 emails | #6722 |
| `reject_min_stay` | ✅ pass | quote rejected: Minimum stay is 2 nights | — |
| `reject_overcapacity` | ✅ pass | quote rejected: This property accommodates a maximum of 4 guests — you requested 999. | — |
| `reject_date_overlap` | ✅ pass | second booking rejected: Selected dates include unavailable nights | #6723 |
| `with_coupon` | ✅ pass | booking #6724 created with coupon, total=$2139.1 | #6724 |
