<?php

namespace Laravel\Fortify\Http\Requests;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Fortify\Contracts\FailedTwoFactorLoginResponse;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class TwoFactorLoginRequest extends FormRequest
{
    /**
     * The user attempting the two factor challenge.
     *
     * @var mixed
     */
    protected $challengedUser;

    /**
     * Indicates if the user wished to be remembered after login.
     *
     * @var bool
     */
    protected $remember;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ];
    }

    /**
     * Determine if the request has a valid two factor code.
     *
     * @return bool
     */
    public function hasValidCode()
    {
        return $this->code && tap(app(TwoFactorAuthenticationProvider::class)->verify(
            decrypt($this->challengedUser()->two_factor_secret), $this->code
        ), function ($result) {
            if ($result) {
                $this->session()->forget('login.id');
            }
        });
    }

    /**
     * Get the valid recovery code if one exists on the request.
     *
     * @return string|null
     */
    public function validRecoveryCode(): bool
    {
        if (! $this->recovery_code) {
            return false;
        }

        $item = collect($this->challengedUser()->recoveryCodes())->first(function ($item) {
            return hash_equals($item->code, $this->recovery_code);
        });

        if (! $item) {
            return false;
        }

        if ($item && $item->used) {
            $this->merge(['is_used' => true]);
            return false;
        }

        $this->session()->forget('login.id');
        return true;
    }

    /**
     * Determine if there is a challenged user in the current session.
     *
     * @return bool
     */
    public function hasChallengedUser()
    {
        if ($this->challengedUser) {
            return true;
        }

        $model = app(StatefulGuard::class)->getProvider()->getModel();

        return $this->session()->has('login.id') &&
            $model::find($this->session()->get('login.id'));
    }

    /**
     * Get the user that is attempting the two factor challenge.
     *
     * @return mixed
     */
    public function challengedUser()
    {
        if ($this->challengedUser) {
            return $this->challengedUser;
        }

        $model = app(StatefulGuard::class)->getProvider()->getModel();

        if (! $this->session()->has('login.id') ||
            ! $user = $model::find($this->session()->get('login.id'))) {
            throw new HttpResponseException(
                app(FailedTwoFactorLoginResponse::class)->toResponse($this)
            );
        }

        return $this->challengedUser = $user;
    }

    /**
     * Determine if the user wanted to be remembered after login.
     *
     * @return bool
     */
    public function remember()
    {
        if (! $this->remember) {
            $this->remember = $this->session()->pull('login.remember', false);
        }

        return $this->remember;
    }
}
