@extends('layouts.publico')

@section('titulo')
Tenés DNI
@endsection

@section('content')

<section id="tieneDni">
  <div class="container">
<h1>¿Tenés DNI argentino?</h1>
@if(session()->has('mensaje'))
                <div class="alert alert-success">
                    
                    {{ session()->get('mensaje')}}
                </div> 
            @endif
<form method="post" enctype="multipart/form-data" action="{{ route('enrollment.tieneDni') }}">
    @csrf
    <div class="form-row">
        <label>Sí</label>
        <input type="radio" name="tieneDni" id="si" class="form-control"  value="si"   required >
    </div>
    <div class="form-row">
        <label>No</label>
       <input type="radio" name="tieneDni" id="no" class="form-control"  value="no"   required >
    </div>   
    
    <div class="form-row">
        <button type="submit" class="btn btn-primary btn-lg">CONTINUAR</button>
    </div>
    
</form>
</div>
</section>
@endsection