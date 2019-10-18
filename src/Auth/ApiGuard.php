<?php
namespace Xiaohuilam\LaravelUserKeypair\Auth;

use Illuminate\Http\Request;
use Illuminate\Auth\TokenGuard;
use Illuminate\Contracts\Auth\UserProvider;

class ApiGuard extends TokenGuard
{
    /**
     * {@inheritDoc}
     */
    public function __construct(
        UserProvider $provider,
        Request $request,
        $inputKey = 'accessKeyId',
        $storageKey = 'access_key_id',
        $hash = false
    ) {
        parent::__construct($provider, $request, $inputKey, $storageKey, $hash);
    }

    /**
     * {@inheritDoc}
     */
    public function user()
    {
        return data_get(parent::user(), 'user');
    }
}
