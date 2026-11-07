@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Terms — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Website terms and conditions for La Vida Belize.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-copy legal"><div class="wrap">
<div class="eyebrow">Legal</div>
<h1>Terms and conditions</h1>
<p>Welcome to La Vida Belize. These terms outline the rules for using this website. By accessing it, you accept these terms.</p>
<h2>License</h2>
<p>Unless otherwise stated, La Vida Belize and its licensors own the intellectual property rights for material on this site. You may access it for personal use, subject to these restrictions. You must not republish, sell, rent, sublicense, reproduce, or redistribute material from La Vida Belize without permission.</p>
<h2>Comments</h2>
<p>If parts of the website allow comments, those comments reflect the views of the person who posts them, not La Vida Belize. We may monitor and remove comments that are inappropriate or in breach of these terms. You warrant that you have the right to post, that comments do not infringe third-party rights or contain unlawful material, and that they will not be used to solicit business. You grant La Vida Belize a non-exclusive license to use and reproduce comments.</p>
<h2>Hyperlinking</h2>
<p>Certain organizations may link to our website without prior approval if the link is not deceptive, does not imply sponsorship, and fits the linking site’s context. No use of the La Vida Belize logo is allowed for linking without a trademark license. We may request removal of links at any time.</p>
<h2>Disclaimer</h2>
<p>We do not warrant that information on this website is complete or accurate, or that the site will remain available. To the maximum extent permitted by law, we exclude representations, warranties and conditions relating to the website, without limiting liability that cannot be excluded, including for death, personal injury, or fraud.</p>
</div></section>
</main>
@endsection
