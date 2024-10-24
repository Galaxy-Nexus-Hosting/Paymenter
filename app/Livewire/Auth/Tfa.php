<?php

namespace App\Livewire\Auth;

use App\Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

class Tfa extends Component
{
    public $code;

    public function mount()
    {
        $session = Session::get('2fa');
        if (!$session || $session['expires'] < now()) {
            return $this->redirect(route('login'), true);
        }
    }

    public function verify()
    {
        $this->validate([
            'code' => 'required|numeric',
        ]);

        if (RateLimiter::tooManyAttempts('2fa', 5)) {
            $this->addError('code', 'Too many attempts.');

            return;
        }

        RateLimiter::increment('2fa');

        $session = Session::get('2fa');
        $user = User::findOrfail($session['user_id']);

        if (!$user->tfa_secret) {
            return $this->redirect(route('login'), true);
        }

        $tfa = new TwoFactorAuth(new EndroidQrCodeProvider, config('app.name'));
        if (!$tfa->verifyCode($user->tfa_secret, $this->code)) {
            return $this->addError('code', 'Invalid code.');
        }

        Auth::loginUsingId($user->id, $session['remember']);

        Session::forget('2fa');

        RateLimiter::clear('2fa');

        return $this->redirect(route('dashboard'), true);
    }

    public function render()
    {
        return view('auth.2fa');
    }
}