<?php

namespace App\Http\Controllers;

use App\Mail\SendVerification;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class AccountController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function show()
    {
      $user = Auth::user();
      return view('layouts.account.show', ['user' => $user, 'accountsLinked' => $user->identities()]);
    }

    public function emailAuthToken(Request $request) {
      $user = Auth::user();

      if (!$user->discordUsername) {
        $validatedData = $request->validate([
          'usaf_email' => 'required|unique:users|max:255|regex:/(.*).mil$/i',
          'discordUsername' => 'required|unique:users|max:255|regex:/[#]/',
          'component' => 'required',
        ]);
      } else {
        $validatedData = $request->validate([
          'usaf_email' => 'required|unique:users|max:255|regex:/(.*).mil$/i',
          'component' => 'required',
        ]);
      }

      $user->usaf_email =  $request->input('usaf_email');
      $user->component =  $request->input('component');
      $user->discordUsername =  $request->input('discordUsername');

      $user->save();

      Mail::to($user->usaf_email)->send(new SendVerification($user->usaf_email));

      return back();
    }

    public function resendEmailAuthToken() {
      $user = Auth::User();

      Mail::to($user->usaf_email)->send(new SendVerification($user->usaf_email));

      return back();
    }

    public function handleVerifyAttempt($token, Request $request) {

      $user = User::where('usaf_verification', $token)->first();

      if ($user->usaf_verified == true) {
        return redirect('account');
      } else {
        $user->usaf_verified = true;
        $user->save();

        if ($user->usaf_verified === true) {
          $client = new \GuzzleHttp\Client([
            'headers' => ['Content-Type' => 'application/json']
          ]);
    
          $reqParamArray['message_id'] = Str::uuid();
          $reqParamArray['type'] = 'Verification';
          $reqParamArray['disc_name'] = $user->discordUsername;
          $reqParamArray['real_name'] = $user->name;
          $reqParamArray['email'] = $user->usaf_email;
          $reqParamArray['url'] = 'https://airforcegaming.com/user/123';
    
          $params[] = $reqParamArray;
          $data = json_encode($params);
    
          $response = $client->post('http://localhost:3000/verified', 
            ['body' => $data]
          );

          return view('layouts.verified');
        } 
      }
    }

    public function logout () {
      Auth::logout();
      return redirect()->route('/');
    }
}