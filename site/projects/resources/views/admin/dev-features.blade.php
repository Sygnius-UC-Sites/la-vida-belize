@extends('admin.layouts')
@section('title', 'Developer — Feature flags')

@section('content')
@parent
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-12 d-flex align-items-center" style="gap:12px;">
        <h1 class="m-0 text-dark" style="font-size:1.4rem;"><i class="fa fa-toggle-on mr-2"></i>Feature flags</h1>
        <span class="badge badge-dark">Developer only — not visible to the customer</span>
      </div>
    </div>
  </div>
</section>

<section class="content">
  <div class="row">
    <div class="col-lg-8">
      <div class="callout callout-warning" style="border-left:4px solid #d9a24a; background:#fdf8ef; padding:16px 20px; border-radius:6px; margin-bottom:20px;">
        <p style="margin:0; color:#5a4a2a;">
          Switch off the admin features this customer hasn't purchased. Disabled features
          disappear from the customer's admin menu <strong>and</strong> their URLs return
          403 for non-developer accounts. Your developer account always sees everything.
          This page itself is hidden — bookmark <code>{{ url('admin/dev/features') }}</code>.
        </p>
      </div>

      <form method="POST" action="{{ url('admin/dev/features') }}">
        @csrf
        <div class="card">
          <div class="card-header"><h3 class="card-title">Sold features for this install</h3></div>
          <div class="card-body p-0">
            <table class="table table-hover mb-0">
              <thead>
                <tr><th style="width:60px;">On</th><th>Feature</th><th>What it controls</th></tr>
              </thead>
              <tbody>
                @foreach($features as $key => $def)
                  <tr>
                    <td class="text-center align-middle">
                      <input type="checkbox" name="features[{{ $key }}]" value="1"
                             style="transform:scale(1.5); cursor:pointer;"
                             @checked($flags[$key] ?? true)>
                    </td>
                    <td class="align-middle"><strong>{{ $def['label'] }}</strong></td>
                    <td class="align-middle text-muted" style="font-size:13px;">
                      {{ $def['desc'] }}
                      <div style="font-size:11px; color:#9aa;">{{ implode(' · ', $def['prefixes']) }}</div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        {{-- ── Sub-features: controls INSIDE pages ── --}}
        @foreach($subFeatures as $groupLabel => $group)
          <div class="card mt-3">
            <div class="card-header"><h3 class="card-title"><i class="fa fa-sliders-h mr-1"></i> {{ $groupLabel }}</h3></div>
            <div class="card-body p-0">
              <table class="table table-hover mb-0">
                <tbody>
                  @foreach($group as $key => $def)
                    <tr>
                      <td class="text-center align-middle" style="width:60px;">
                        <input type="checkbox" name="features[{{ $key }}]" value="1"
                               style="transform:scale(1.5); cursor:pointer;"
                               @checked($flags[$key] ?? true)>
                      </td>
                      <td class="align-middle"><strong>{{ $def['label'] }}</strong></td>
                      <td class="align-middle text-muted" style="font-size:13px;">{{ $def['desc'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endforeach

        <div class="card mt-3">
          <div class="card-footer">
            <button class="btn btn-success"><i class="fa fa-save"></i> Save flags</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</section>
@stop
