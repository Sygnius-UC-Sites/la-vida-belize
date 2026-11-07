@extends('admin.layouts')

@section('title', 'Admin ')

@section('content_header')
    <h1 class="m-0 text-dark">Settings</h1>
@stop

@section('content')
		@parent
<div class="col-12 col-sm-12 col-lg-12">
            <div class="card card-primary card-outline card-outline-tabs">
              <div class="card-header p-0 border-bottom-0">
@php
    // Per-customer feature flags (dev panel): tab visibility. Devs see all.
    $tabShow = fn ($key) => \App\Helper\FeatureFlags::isDeveloper()
        || \App\Helper\FeatureFlags::enabled($key);
@endphp
                <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" id="custom-tabs-three-home-tab" data-toggle="pill" href="#custom-tabs-three-home" role="tab" aria-controls="custom-tabs-three-home" aria-selected="true">Main</a>
                  </li>
                  @if($tabShow('settings_tab_social'))
                  <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-profile-tab" data-toggle="pill" href="#custom-tabs-three-profile" role="tab" aria-controls="custom-tabs-three-profile" aria-selected="false">Social Media</a>
                  </li>
                  @endif
                  @if($tabShow('mail_settings'))
                  <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-messages-tab" data-toggle="pill" href="#custom-tabs-three-messages" role="tab" aria-controls="custom-tabs-three-messages" aria-selected="false">Mail</a>
                  </li>
                  @endif
                  @if($tabShow('settings_tab_branding'))
                  <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-developer-tab" data-toggle="pill" href="#custom-tabs-three-developer" role="tab" aria-controls="custom-tabs-three-developer" aria-selected="false">Branding</a>
                  </li>
                  @endif
                  @if($tabShow('settings_tab_navigation'))
                  <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-nav-tab" data-toggle="pill" href="#custom-tabs-three-nav" role="tab" aria-controls="custom-tabs-three-nav" aria-selected="false">Navigation</a>
                  </li>
                  @endif
                  @if($tabShow('pms'))
                  <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-pms-tab" data-toggle="pill" href="#custom-tabs-three-pms" role="tab" aria-controls="custom-tabs-three-pms" aria-selected="false">PMS</a>
                  </li>
                  @endif
                  <li class="nav-item">
                    <a class="nav-link" id="custom-tabs-three-password-tab" data-toggle="pill" href="#custom-tabs-three-password" role="tab" aria-controls="custom-tabs-three-password" aria-selected="false">Change password</a>
                  </li>

                </ul>
              </div>

              {{-- HTML5 trick: the password form lives outside the main settings form
                   (HTML disallows nested forms). The inputs inside the password tab
                   reference it via `form="adminPasswordForm"` so they submit here. --}}
              <form id="adminPasswordForm" method="POST" action="{{ route('change-password-admin') }}" autocomplete="off" style="display:none;">@csrf</form>

              {!! Form::open(["files"=>"true"]) !!}
              <div class="card-body">
                <div class="tab-content" id="custom-tabs-three-tabContent">
                  <div class="tab-pane fade active show" id="custom-tabs-three-home" role="tabpanel" aria-labelledby="custom-tabs-three-home-tab">
                    @include("admin.dashboard.sub.main")
                  </div>
                  @if($tabShow('settings_tab_social'))
                  <div class="tab-pane fade" id="custom-tabs-three-profile" role="tabpanel" aria-labelledby="custom-tabs-three-profile-tab">
                     @include("admin.dashboard.sub.social")
                  </div>
                  @endif
                  @if($tabShow('mail_settings'))
                  <div class="tab-pane fade" id="custom-tabs-three-messages" role="tabpanel" aria-labelledby="custom-tabs-three-messages-tab">
                     @include("admin.dashboard.sub.mail")
                  </div>
                  @endif
                  @if($tabShow('settings_tab_branding'))
                  <div class="tab-pane fade" id="custom-tabs-three-developer" role="tabpanel" aria-labelledby="custom-tabs-three-developer-tab">
                     @include("admin.dashboard.sub.developer")
                  </div>
                  @endif
                  @if($tabShow('settings_tab_navigation'))
                  <div class="tab-pane fade" id="custom-tabs-three-nav" role="tabpanel" aria-labelledby="custom-tabs-three-nav-tab">
                     @include("admin.dashboard.sub.nav")
                  </div>
                  @endif
                  @if($tabShow('pms'))
                  <div class="tab-pane fade" id="custom-tabs-three-pms" role="tabpanel" aria-labelledby="custom-tabs-three-pms-tab">
                     @include("admin.dashboard.sub.pms")
                  </div>
                  @endif

                  <div class="tab-pane fade" id="custom-tabs-three-password" role="tabpanel" aria-labelledby="custom-tabs-three-password-tab">
                    <div class="card-body p-0" style="max-width:540px;">
                      <p class="text-muted small">Both fields required. The new password must be at least 8 characters and different from the current.</p>

                      @if($errors->first('old_password') || $errors->first('new_password'))
                        <div class="alert alert-danger py-2">
                          {{ $errors->first('old_password') ?: $errors->first('new_password') }}
                        </div>
                      @endif

                      <div class="form-group">
                        <label for="cp_old_password">Current password</label>
                        <div class="input-group">
                          <input form="adminPasswordForm" type="password" id="cp_old_password" name="old_password"
                                 class="form-control" required autocomplete="current-password">
                          <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary cp-toggle" data-target="cp_old_password" tabindex="-1" aria-label="Show password">
                              <i class="fas fa-eye"></i>
                            </button>
                          </div>
                        </div>
                      </div>

                      <div class="form-group">
                        <label for="cp_new_password">New password</label>
                        <div class="input-group">
                          <input form="adminPasswordForm" type="password" id="cp_new_password" name="new_password"
                                 class="form-control" required autocomplete="new-password" minlength="8">
                          <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary cp-toggle" data-target="cp_new_password" tabindex="-1" aria-label="Show password">
                              <i class="fas fa-eye"></i>
                            </button>
                          </div>
                        </div>
                        <small class="form-text text-muted">At least 8 characters. Mix letters, numbers, and symbols.</small>
                      </div>

                      <button form="adminPasswordForm" type="submit" class="btn btn-primary">
                        <i class="fas fa-key mr-1"></i> Update password
                      </button>
                    </div>
                  </div>

                </div>
                <div class="alert"></div>
                {{-- Settings save button hides on the password tab — that tab uses its own form. --}}
                <button class="btn btn-success" type="submit" id="settingsSaveBtn"><i class="fa fa-save"></i> Save</button>
              </div>
              {!! Form::close() !!}
              <!-- /.card -->
            </div>
          </div>
    

@stop
@section("js")
@parent

<script>
  $(function(){
    
    //CKEDITOR.replace( "input['booking-second-data']",{ allowedContent:true} );
  });

   $(document).on("click",".nav-item",function(){
      var target_gaurav=$(this).find(".nav-link").attr("id");
      document.cookie = "target_jhon_data1="+target_gaurav;
  });


  @if(isset($_COOKIE['target_jhon_data1']))
    $(function(){
      $("#{{$_COOKIE['target_jhon_data1']}}").click();
    })
  @endif

  // Hide the main settings "Save" button while the password tab is active
  // (that tab posts to its own form via the form="adminPasswordForm" attribute).
  $(function () {
    function syncSaveBtn() {
      var pwActive = $('#custom-tabs-three-password').hasClass('active');
      $('#settingsSaveBtn').toggle(!pwActive);
    }
    $('a[data-toggle="pill"]').on('shown.bs.tab', syncSaveBtn);
    syncSaveBtn();
  });

  // Eye-toggle on password fields.
  $(document).on('click', '.cp-toggle', function () {
    var $input = $('#' + $(this).data('target'));
    var hidden = $input.attr('type') === 'password';
    $input.attr('type', hidden ? 'text' : 'password');
    $(this).find('i').attr('class', hidden ? 'fas fa-eye-slash' : 'fas fa-eye');
  });
</script>

{{-- Phase 13C — payment_interval JS removed; installments are managed on Payment Settings page now. --}}
@stop