<?php

declare(strict_types=1);

namespace App\Security\UserProvider;

use App\Security\Ldap\LdapEntryProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class LdapUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly Ldap $ldap,
        #[Autowire(env: 'LDAP_BASE_DN')] private readonly string $baseDn,
        private readonly LdapEntryProviderInterface $ldapEntryFetcher,
    ) {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof LdapUser) {
            throw new UnsupportedUserException(\sprintf('instances of "%s" are not supported.', \get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return LdapUser::class === $class || is_subclass_of($class, LdapUser::class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $entry = $this->ldapEntryFetcher->getUserEntry($identifier);

        $username = $entry->getAttribute('cn')[0];
        $password = $entry->getAttribute('userPassword')[0];

        $roles = $this->fetchRoles($username);

        return new LdapUser(
            entry: $entry,
            identifier: $username,
            password: $password,
            roles: $roles,
        );
    }

    private function fetchRoles(string $identifier): array
    {
        $query = \sprintf('(member=cn=%s,ou=users,%s)', $identifier, $this->baseDn);
        $search = $this->ldap->query(\sprintf('ou=groups,%s', $this->baseDn), $query);

        $entries = $search->execute()->toArray();

        $roles = [];

        foreach ($entries as $entry) {
            $groupCommonName = $entry->getAttribute('cn')[0];
            $roles[] = 'ROLE_'.\strtoupper($groupCommonName);
        }

        return $roles;
    }
}
