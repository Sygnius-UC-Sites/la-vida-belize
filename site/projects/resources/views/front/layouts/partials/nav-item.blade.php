@php
    $href = ($item['url'] ?? '/') === '/'
        ? url('/')
        : (isset($item['url']) && Str::startsWith($item['url'], '#')
            ? $item['url']
            : url($item['url'] ?? '#'));
@endphp
@if(($item['type'] ?? '') === 'link')
  <li class="nav-item">
    <a class="nav-link pv-link" href="{{ $href }}">{{ $item['label'] }}</a>
  </li>
@elseif(($item['type'] ?? '') === 'dropdown')
  <li class="nav-item dropdown">
    <a class="nav-link pv-link dropdown-toggle" href="#" data-bs-toggle="dropdown">{{ $item['label'] }}</a>
    <ul class="dropdown-menu">
      @foreach($item['children'] ?? [] as $child)
        <li><a class="dropdown-item" href="{{ url($child['url']) }}">{{ $child['label'] }}</a></li>
      @endforeach
    </ul>
  </li>
@elseif(($item['type'] ?? '') === 'property' && $navProperty)
  <li class="nav-item">
    <a class="nav-link pv-link" href="{{ url($navProperty->seo_url) }}">{{ $navProperty->name }}</a>
  </li>
@endif
