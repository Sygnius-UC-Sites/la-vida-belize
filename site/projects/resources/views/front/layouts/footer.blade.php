<footer class="footer" id="contact">
  <div class="container footer__inner">
    <div class="footer__col footer__col--brand">
      <a href="{{ url('/') }}" class="nav__logo footer__logo">
        <img src="{{ asset($setting_data['footer_logo'] ?? ($setting_data['logo'] ?? 'front/themes/base/img/logo.svg')) }}" alt="{{ $setting_data['website'] ?? 'Your Brand' }}" loading="lazy">
      </a>
      <p>{{ $setting_data['footer_brand_desc'] ?? '' }}</p>
      <div class="footer__socials">
        @if(!empty($setting_data['facebook_url']))
          <a href="{{ $setting_data['facebook_url'] }}" target="_blank" rel="noopener" aria-label="Facebook" class="social-btn">FB</a>
        @endif
        @if(!empty($setting_data['instagram_url']))
          <a href="{{ $setting_data['instagram_url'] }}" target="_blank" rel="noopener" aria-label="Instagram" class="social-btn">IG</a>
        @endif
      </div>
    </div>

    <div class="footer__col">
      <h4>{{ $setting_data['footer_explore_heading'] ?? 'Explore' }}</h4>
      <ul>
        @foreach(json_decode($setting_data['nav_menu'] ?? '[]', true) ?: [] as $navItem)
        <li><a href="{{ \Illuminate\Support\Str::startsWith((string) ($navItem['url'] ?? '/'), ['http://', 'https://']) ? $navItem['url'] : url($navItem['url'] ?? '/') }}">{{ $navItem['label'] ?? '' }}</a></li>
        @endforeach
      </ul>
    </div>

    <div class="footer__col">
      <h4>{{ $setting_data['footer_contact_heading'] ?? 'Contact' }}</h4>
      <ul class="footer__contact">
        <li>
          <span aria-hidden="true">{{ $setting_data['footer_label_email'] ?? 'Email' }}</span>
          <a href="mailto:{{ $setting_data['email'] ?? config('mail.from.address') }}">{{ $setting_data['email'] ?? config('mail.from.address') }}</a>
        </li>
        <li>
          <span aria-hidden="true">{{ $setting_data['footer_label_phone'] ?? 'Phone' }}</span>
          <a href="tel:{{ preg_replace('/[^0-9+]/', '', $setting_data['mobile'] ?? '') }}">{{ $setting_data['mobile'] ?? '' }}</a>
        </li>
        <li>
          <span aria-hidden="true">{{ $setting_data['footer_label_place'] ?? 'Place' }}</span>
          <a>{{ $setting_data['address'] ?? '' }}</a>
        </li>
      </ul>
    </div>
  </div>

  <div class="footer__bottom">
    <div class="container footer__bottom-inner">
      <p>{{ $setting_data['copyright'] ?? ('© ' . date('Y') . ' ' . ($setting_data['website'] ?? 'Your Brand') . '. All rights reserved.') }}</p>
      <div class="footer__legal">
        @if(!empty($setting_data['footer_privacy_label'] ?? 'Privacy Policy'))
          <a href="{{ $setting_data['footer_privacy_url'] ?? url('privacy-policy') }}">{{ $setting_data['footer_privacy_label'] ?? 'Privacy Policy' }}</a>
        @endif
        @if(!empty($setting_data['footer_terms_label'] ?? 'Terms of Service'))
          <a href="{{ $setting_data['footer_terms_url'] ?? url('terms-of-service') }}">{{ $setting_data['footer_terms_label'] ?? 'Terms of Service' }}</a>
        @endif
      </div>
    </div>
  </div>
</footer>
@include('front.layouts.js')
@yield('js')
