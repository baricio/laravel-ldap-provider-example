<?php

namespace App\Auth;

use Adldap\Laravel\Facades\Adldap;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Exception;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class LdapAuthProvider extends EloquentUserProvider
{
    public function validateCredentials(UserContract $user, array $credentials)
    {
        return $this->validate($credentials);
    }

    /**
     * @inheritDoc
     */
    private function validate(array $credentials)
    {
        $ldapUser = null;

        try {
            // the user exists in the LDAP server?
            $ldapUser = Adldap::search()->where('samaccountname', '=', $credentials['username'])->first();
            $distinguishedName = $ldapUser->distinguishedName[0] ?? null;
            $hasBound = Adldap::auth()->attempt($distinguishedName, $credentials['password']);
        } catch (Exception $exception) {
            throw new Exception(
                'Fail to check credentials in LDAP.'
            );
        }

        if (!$hasBound) {
            throw new Exception(
                'Credentials not valid into Ldap'
            );
        }

        if (true === array_key_exists('model', $this->config)) {
            $model = $this->config['model'];

            if ($user = $model::whereEmail($ldapUser->proxyaddresses)->first()) {
                return true;
            }

            if (self::createUser($model, $ldapUser, $credentials['password'])) {
                return true;
            }
        }

        throw new Exception(
            'Fail to check data in model'
        );
    }
}
