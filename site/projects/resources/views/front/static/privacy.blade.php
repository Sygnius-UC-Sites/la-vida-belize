@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Privacy Policy — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Privacy policy for La Vida Belize LTD.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-copy legal"><div class="wrap">
<div class="eyebrow">Legal</div>
<h1>Privacy Policy</h1>
<p>Privacy Policy for La Vida Belize LTD, accessible at www.lavidabelize.com. One of our main priorities is the privacy of our visitors. Email questions to info@lavidabelize.com.</p>
<p>This policy applies to our online activities and to information visitors share or that is collected on this website. It does not apply to information collected offline or through other channels.</p>
<h2>Consent</h2>
<p>By using our website, you consent to this Privacy Policy and agree to its terms.</p>
<h2>Information we collect</h2>
<p>When we ask for personal information, we will explain what is requested and why. If you contact us directly, we may receive your name, email address, phone number, message contents, attachments, and any other information you choose to provide.</p>
<h2>How we use your information</h2>
<p>We use collected information to operate and improve the website, understand how it is used, communicate with you about your stay or updates, send emails, and help prevent fraud.</p>
<h2>Log files and cookies</h2>
<p>Like most websites, we use log files and cookies. Logs may include IP address, browser type, ISP, date and time, referring pages, and click counts, used to analyze trends and administer the site. Cookies store preferences and visited pages to improve the experience.</p>
<h2>Your rights</h2>
<p>Depending on where you live, you may have rights to access, correct, delete, restrict, or object to processing of personal data, and to data portability. California residents may also request disclosure or deletion of personal data collected about them. We have one month to respond. Contact us to exercise these rights.</p>
<h2>Children</h2>
<p>We do not knowingly collect personal information from children under 13. If you believe a child has provided such information, contact us and we will work to remove it.</p>
</div></section>
</main>
@endsection
