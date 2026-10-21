@if(session('success'))<div class="container-xxl mt-4"><div class="alert alert-success" role="status">{{ session('success') }}</div></div>@endif
@if($errors->any())<div class="container-xxl mt-4"><div class="alert alert-danger" role="alert"><strong>Periksa kembali data Anda.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
