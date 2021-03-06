<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;   
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\envioEMail;
use App\Mail\enviarFormuSinDNI;



class EnrollmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function login()
    {
         return view("login");
    }

    public function prueba(){

         $usu=DB::table('users')
            ->where('name','rcodarin')->first();

        // $hash=Hash::make("hola");
         $password=Hash::check('gRgQ7cEReF',  $usu->password);
         dd($password);
    }

    public function tieneDni(){
        return view("enrollment.tieneDni");
    }

    public function FormValidaRenaper(){
       return view("enrollment.FormValidaRenaper");
    }

    public function derivar(Request $request){
       //$datos=$request->all();
        if($request->input('tieneDni')=='si'){
          
            return view("enrollment.FormValidaRenaper");
        }else{
            return view("enrollment.StartForm");

        }
    }

    public function verificarEnRenaper(Request $request){

        $dni=$request->input('dni');
        $nroTramite=$request->input('nroTramite');
        //obtenter token
        $response = Http::asForm()->post('https://apirenaper.idear.gov.ar/CHUTROFINAL/API_ABIS/Autorizacion/token.php', [
        'username' => 'MMGDDATOS',
        'password' => 'H8ZRu6nC1Apaqwj',
        ]);
        $responseBody = json_decode($response->getBody());
        if($responseBody->data->codigo==0){

            $token=$responseBody->data->token;
            $consultaF='https://apirenaper.idear.gov.ar/apidatos/porDniSexoTramite.php?dni='.$dni.'&sexo=F&idtramite='.$nroTramite;
            $consultaM='https://apirenaper.idear.gov.ar/apidatos/porDniSexoTramite.php?dni='.$dni.'&sexo=M&idtramite='.$nroTramite;
            $response = Http::withToken($token)->get($consultaF);            
            $mensaje=json_decode($response->getBody()->getContents());
           
             if($mensaje->codigo!=99){
                 session()->flash('mensaje', 'Los datos ingresados no existen en el Registro Nacional de las Personas');
                 return view("enrollment.FormValidaRenaper");

            }
            //dd($mensaje);
            //$nombre=$mensaje->cuil;
            //Aquí debemos consultar si existe la promotora con el servicio de
            //planificacion, si exite nos retorna un id_promotorx
            $promotorx=DB::table('promotorxes')
            ->where('dni', $dni)->first();

            $id_promotorx=$promotorx->id;// 

            $usuario=DB::table('users')
            ->where('id_promotorx',$id_promotorx)->first();

            if($id_promotorx){               


                //Si el usuario existe, vemos si ya tiene usuario crezdo
                if($usuario && $usuario->fecha_pass_usuario!=null){



                    session()->flash('mensaje', 'La persona consultada ya tiene un usuario creado,
                    por favor haga click en olvide mi usuario y contraseña');
                   
                    return view("login");

                }else{
                    // cambiar el email por el que venga de la 
                    //consulta a la base de datos

                 $usuario=new User;
                 $usuario->nombre=$mensaje->nombres;
                 $usuario->apellido=$mensaje->apellido;
                 $usuario->email="rominacodarin@gmail.com";
                 $usuario->dni=$dni;
                 $usuario->cuil=$mensaje->cuil;
                 $usuario->fecha_nacimiento=$mensaje->fecha_nacimiento;
                 $usuario->id_promotorx=$id_promotorx;
                 session(['usuario' => $usuario ]);
                    return view("enrollment.AltaUsuario0")->with([
                        'usuario'=>$usuario,
                    ]);




                }


            }else{
               
                 session()->flash('mensaje', 'La persona no está registrada en la base de datos de promotores');
              
                return view("login");
            }



            
           
        }else{
            session()->flash('mensaje', 'No pudimos establecer la conexión para verificar sus datos');         
            return view("enrollment.FormValidaRenaper");

        }

    }

    public function altaUsuario($usuario){

        if(session('usuario')!=null){
         $usu= session('usuario');
        
        return view("enrollment.AltaUsuario1")->with([
                        'usuario'=>$usu,
                    ]);
        }else{

            return view("enrollment.FormValidaRenaper");

        }

    }

    
    public function nonDniForm()
    {
        return view("enrollment.nonDniForm");
    }

    public function grabarUsuario($id_promotorx)
    {
        if(session('usuario')!=null){

            $usuario= session('usuario');

            $usu=DB::table('users')
            ->where('email',$usuario->email)->first();

            if($usu && $usu->fecha_pass_usuario!=null){

                session()->flash('mensaje', 'El email ingresado ya esta registrado con otro usuario, utilizá uno distinto o escribinos a tejiendo@mingeneros.gob.ar');
                return view("enrollment.AltaUsuario1")->with(['usuario'=>session('usuario')]); 



            }else{

                $usu=DB::table('users')
                ->where('name',request()->usuario)->first(); 

                if($usu && $usu->fecha_pass_usuario!=null){

                    session()->flash('mensaje', 'El usuario ya está en uso. Elija uno diferente');
                    return view("enrollment.AltaUsuario1")->with(['usuario'=>session('usuario')]);

                }else{ 

                    if(request()->input('usuario')==request()->input('usuario2')){


                        $str = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
                        $password = "";       
                        for($i=0;$i<10;$i++) {
                            //obtenemos un caracter aleatorio escogido de la cadena de caracteres
                            $password .= substr($str,rand(0,62),1);
                        }
                        $usu=DB::table('users')
                        ->where('email',$usuario->email)->delete(); 

                          

                        $usuario=session('usuario');
                        
                        $usuario->name=request()->input('usuario');
                        $usuario->password=$password;


                        //enviar email
                        try {
                            
                            Mail::to($usuario->email)->send(new envioEmail($usuario));
                             $usuario->password=Hash::make($password);
                             $usuario->save();


                             return view("enrollment.ConfirmacionAltaUsuario")->with([
                                    'email'=>$usuario->email,
                                ]);
                             
                        } catch (Exception $e) {

                            report($e);
                            session()->flash('mensaje', 'No pudimos enviar
                                el email con la contraseña, intentelo nuevamente.');
                        return view("enrollment.AltaUsuario1")->with(['usuario'=>session('usuario')]);  


                        }
                       

                       

                       

               

                    }else{
                        session()->flash('mensaje', 'Los campos de Usuario y Repetir Usuario no coinciden');
                        return view("enrollment.AltaUsuario1")->with(['usuario'=>session('usuario')]);

                }

            }

        }//fin else if usuari

        }else{// si no hay sesion vuelve ingresar dni y tramite

            session()->flash('mensaje', 'Se produjo un error por favor iniciá el proceso nuevamente');
            return view("enrollment.FormValidaRenaper");
        }//fin else no tiene sesion    

    }

   
    public function ingresar(){

        $pass=Hash::make(request()->input('password'));

        $usu=DB::table('users')
        ->where('password',$pass)
        ->where('name',request()->input('name'))->first();
        $usuarios=$usu=DB::table('users')->get();
            dd($usuarios);
    }

    public function FormOlvidePass(){

        return view("enrollment.FormOlvidePass");
    }

    public function recuperoPass(){

        //cuando tengamos acceso al servicio de promotorxs verificar que 
        //existen los datos de nombre y apellido

      $usuario=\App\Models\User::where('email',request()->input('email'))->first();


        if($usuario){

        $verificar=Hash::check(request()->input('password'),  $usuario->password);
        
        if($verificar){

            return view('enrollment.CrearNuevoUsuario')->with(['usuario'=>$usuario]);
        }    

        $str = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
        $password = "";       
        for($i=0;$i<10;$i++) {
            //obtenemos un caracter aleatorio escogido de la cadena de caracteres
            $password .= substr($str,rand(0,62),1);
        }

         
        
         $usuario->fecha_pass_usuario=null; 
        // dd($usuario);
         $usuario->password=Hash::make($password); 
         $usuario->save();
         $usuario->password=$password;
         $usuario->nombres=request()->input('nombre');

         
        //enviar email
        try {
            
            Mail::to($usuario->email)->send(new envioEmail($usuario));
             


             return view("enrollment.ConfirmacionCambioPass")->with([
                    'email'=>$usuario->email,
                ]);
             
        } catch (Exception $e) {

            report($e);
            session()->flash('mensaje', 'No pudimos enviar
                el email con la contraseña, intentelo nuevamente.');
        return view("enrollment.AltaUsuario1")->with(['usuario'=>session('usuario')]);  


        }


        }else{ //fin if se encontro al usuario

              session()->flash('mensaje', 'Los datos no coinciden con los de unx promotorx registrado, por favor envianos un email contando el problema a tejiendo@mingeneros.gob.ar');
              return view("FormOlvidePass"); 


        }//fin else no se encontro al usuario
    }

     public function grabarUsuarioNuevo($id_usuarix)
    {
       
        $usuario=User::find($id_usuarix);
       

          
        $usu=DB::table('users')
        ->where('name',request()->usuario)->first(); 

                if($usu && $usu->fecha_pass_usuario!=null){

                    session()->flash('mensaje', 'El usuario ya está en uso. Elija uno diferente');
                    return view("enrollment.CrearNuevoUsuario")->with(['usuario'=>$usuario]);

                }else{ 

                    if(request()->input('usuario')==request()->input('usuario2')){


                        
                        $usuario->name=request()->input('usuario');
                        $usuario->save();

                         return view("enrollment.ConfirmacionAltaUsuarioNuevo");
               

                    }else{
                        session()->flash('mensaje', 'Los campos de Usuario y Repetir Usuario no coinciden');
                        return view("enrollment.CrearNuevoUsuario")->with(['usuario'=>$usuario]);

                }

            }

        

        

    }

    public function sendToEmail(){

        $nombre=request()->input('nombre');
        $apellido=request()->input('apellido');
        $provincia=request()->input('provincia');
        $organizacion=request()->input('organizacion');
        $email=request()->input('email');
   

       try {
            
            Mail::to('rominacodarin@gmail.com')->send(new enviarFormuSinDNI($nombre, $apellido, $provincia, $organizacion, $email));             

                session()->flash('mensaje', 'Recibimos su solicitud de creación de usuarix. Te contactaremos a la brevedad');
             return view("Login");
             
        } catch (Exception $e) {

            report($e);
            session()->flash('mensaje', 'No pudimos enviar
                la solicitud de creación de usuario, intentelo nuevamente.');
        return view("enrollment.nonDniForm");  


        }
    }






   
}
