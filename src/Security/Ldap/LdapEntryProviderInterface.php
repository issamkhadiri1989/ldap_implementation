<?php

declare(strict_types=1);

namespace App\Security\Ldap;

use Symfony\Component\Ldap\Entry;

interface LdapEntryProviderInterface
{
    public function getUserEntry(string $identifier): Entry;
}
