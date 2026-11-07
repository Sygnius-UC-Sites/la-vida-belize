@extends('admin.layouts')

@section('title', 'Admin ')

@section('content_header')
      <h1 class="m-0 text-dark">Media Center</h1>
@stop

@section('content')
		@parent
    <div class="row">
        <div class="col-12">
            <div class="card ">
                <div class="card-header">
              <h3 class="card-title">Media Center</h3>
            </div>

            <!-- /.card-header -->
            <div class="card-body">
            {!! Form::open(["route"=>"new-file-uploads","files"=>true]) !!}
            <h5>New File Uploads</h5>
                <div class="row">
                    <div class="col md-9">
                        <input type="file" name="file[]" multiple required class="form-control">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-success"> Uploads New Files</button>
                    </div>
                </div>
            {!! Form::close() !!}
            <div class="alert"></div>
                <table id="data-table-gaurav" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Image</th>
                        <th>Path</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                        @php $i=1; @endphp
                        @foreach($data as $d)
                        @if($d!="." && $d!="..")
                        @php 
                            $ar=explode(".",$d);
                            $img=false;
                            if(is_array($ar)){
                                if(count($ar)>1){
                                    if(in_array(strtolower(end($ar)), ["jpg","jpeg","gif","bmp","png","webp"])){
                                        $img=true;
                                    }
                                }
                            }
                        @endphp
                        @if($img==true)
                        <tr>
                            <td>{{ $i++ }}</td>
                            <td><img src="{{asset('uploads/uploads/'.$d) }}" alt="" width="100px;" height="100px"></td>
                            <td>{{ asset('uploads/uploads/'.$d) }}</td>
                            <td>
                                <form method="post" action="{!! route('medias-destroy') !!}"
                                      style="display: inline-block;">
                                    {!! csrf_field() !!}
                                    {!! method_field('DELETE') !!}
                                    <input type="hidden" name="file" value="{{ $d }}"/>
                                    <button type="submit" class="btn btn-outline-danger btn-sm raw-margin-right-8"
                                            onclick="return confirm('Are you sure you want to delete this media file?')"><i
                                                class="fa fa-trash"></i> 
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endif
                        @endif
                        @endforeach
                    </tbody>
                </table>
        
            </div>
            <!-- /.card-body -->
            </div>
        </div>
    </div>
@stop
@section("js")
<script>
      $("#data-table-gaurav").dataTable({

});
</script>
@stop