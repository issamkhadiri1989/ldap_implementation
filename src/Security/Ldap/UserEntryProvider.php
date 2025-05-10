<?php

declare(strict_types=1);

namespace App\Security\Ldap;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class UserEntryProvider implements LdapEntryProviderInterface
{
    public function __construct(
        private readonly Ldap $ldap,
        #[Autowire(env: 'SEARCH_DN')] private readonly string $searchDn,
        #[Autowire(env: 'LDAP_ADMIN_PASSWORD')] private readonly string $searchPassword,
        #[Autowire(env: 'LDAP_BASE_DN')] private readonly string $baseDn,
    ) {
    }

    public function getUserEntry(string $identifier): Entry
    {
        $this->ldap->bind(dn: $this->searchDn, password: $this->searchPassword);

        $search = $this->ldap->query($this->baseDn, \sprintf('(cn=%s)', $identifier));

        $entries = $search->execute()->toArray();

        if (1 !== \count($entries)) {
            throw new UserNotFoundException('User not found.');
        }

        /* @var Entry $entry */
        return \reset($entries);
    }
}
