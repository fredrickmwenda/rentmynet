<?php

namespace App\Http\Controllers;
// require 'autoload.php';

use Illuminate\Http\Request;
use App\SystemUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Twilio\Rest\Client;

class AuthController extends Controller
{
    //
     /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    public function create(Request $request)
    {

            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'phone_number' => ['required', 'numeric', 'unique:system_users'],
                'email' => ['required', 'unique:system_users'],
                'type' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);
    
            $token = getenv("TWILIO_AUTH_TOKEN");
            $twilio_sid = getenv("TWILIO_ACCOUNT_SID");
            $twilio_verify_sid = getenv("TWILIO_VERIFICATION_SID");
    
            $twilio = new Client($twilio_sid, $token);
    
            $twilio->verify->v2->services($twilio_verify_sid)
            ->verifications
            ->create($data['phone_number'], "sms");
    
       $system_user = SystemUser::create([
                        'name' => $data['name'],
                        'phone_number' => $data['phone_number'],
                        'email' => $data['email'],
                        'type' => $data['type'],
                        'password' => Hash::make($data['password']),
                      ]);

        return redirect()->route('verify')->with(['phone_number' => $data['phone_number']]);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'verification_code' => ['required', 'numeric'],
            'phone_number' => ['required', 'string'],
        ]);

        /* Get credentials from .env */
        $token = getenv("TWILIO_AUTH_TOKEN");
        $twilio_sid = getenv("TWILIO_ACCOUNT_SID");
        $twilio_verify_sid = getenv("TWILIO_VERIFICATION_SID");

        $twilio = new Client($twilio_sid, $token);

        $verification = $twilio->verify->v2->services($twilio_verify_sid)
            ->verificationChecks
            ->create($data['verification_code'], array('to' => $data['phone_number']));

        if ($verification->valid) {
            $system_user = tap(SystemUser::where('phone_number', $data['phone_number']))->update(['phone_verified_at' => true]);
            /* Authenticate user */
            Auth::login($system_user->first());
            return redirect()->route('dashboard')->with(['message' => 'Phone number verified']);
        }
        return back()->with(['phone_number' => $data['phone_number'], 'error' => 'Invalid verification code entered!']);
    }


}
