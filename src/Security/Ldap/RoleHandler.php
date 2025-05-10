<?php

declare(strict_types=1);

namespace App\Security\Ldap;

class RoleHandler
{
    /**
     * Retrieves user's roles from the groups he belongs to.
     *
     * @return array
     */
    public function findRoles(): array
    {
        return [];
    }
}
