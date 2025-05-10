# Content of this project

## URLs:

PHPLdapAdmin: `http://localhost:8082/`

## Configurations:

### Symfony

first you need to configure the LDAP adapter

```yaml
#config/services.yaml

services:
    Symfony\Component\Ldap\Ldap:
        arguments: [ '@Symfony\Component\Ldap\Adapter\ExtLdap\Adapter' ]
        tags:
            - ldap

    Symfony\Component\Ldap\Adapter\ExtLdap\Adapter:
        arguments:
            -   host: '%env(LDAP_HOST)%'
                port: '%env(LDAP_PORT)%'
                options:
                    protocol_version: 3
                    referrals: false

```

the `LDAP_PORT` and `LDAP_HOST` env vars must be defined in .env or OS env vars.

you need then use the `Symfony\Component\Ldap\Ldap` service in the `config/packages/security.yaml` :

```yaml
security:
    # ...

    providers:
        ldap_user_provider:
            ldap:
                service: Symfony\Component\Ldap\Ldap
                base_dn: '%env(LDAP_BASE_DN)%'
                search_dn: '%env(SEARCH_DN)%'
                search_password: '%env(LDAP_ADMIN_PASSWORD)%'
                default_roles: [ 'ROLE_COLLAB' ]
                uid_key: cn
                extra_fields: [ ]
                password_attribute: userPassword
    firewalls:
        # ...
        main:
            provider: ldap_user_provider
            form_login_ldap:
                service: Symfony\Component\Ldap\Ldap
                login_path: app.security.login
                check_path: app.security.login
                enable_csrf: true
                dn_string: '%env(DN_STRING)%'
            logout:
                path: /logout
                target: /
    # ...
```

the `form_login_ldap` decorates the `form_login` authenticator. this is why we need to configure the `login_path` and
the `check_path` options. also we need to add service = `Symfony\Component\Ldap\Ldap` configured earlier.

### Ldap Server

we need to have users and groups. 1 user can be in more than 1 group and 1 group can contain many users. because it is
hard to configure this using PHPLdapAdmin, we are going to add configuration inside the `openldap` container directly.

in `infra/ldap/slapd/config` we have 2 files:

- roles.ldif : this file will contain configuration to add groups
```ldif

# Base organizational unit for groups

dn: ou=groups,dc=ldaplocal,dc=com
objectClass: organizationalUnit
ou: groups

# Role: ROLE_COLLAB
dn: cn=COLLAB,ou=groups,dc=ldaplocal,dc=com
objectClass: groupOfNames
cn: USER
member: cn=ikhadiri,ou=users,dc=ldaplocal,dc=com

# Role: ROLE_ADMIN
dn: cn=ADMIN,ou=groups,dc=ldaplocal,dc=com
objectClass: groupOfNames
cn: ADMIN
member: cn=iboukhari,ou=users,dc=ldaplocal,dc=com

# Role: ROLE_DOCTOR
dn: cn=DOCTOR,ou=groups,dc=ldaplocal,dc=com
objectClass: groupOfNames
cn: DOCTOR
member: cn=user001,ou=users,dc=ldaplocal,dc=com
member: cn=user002,ou=users,dc=ldaplocal,dc=com
```

- users.ldif: this file will contain configuration to add users

```ldif
# Base organizational unit for users
dn: ou=users,dc=ldaplocal,dc=com
objectClass: organizationalUnit
ou: users

# User: User 1
dn: cn=user001,ou=users,dc=ldaplocal,dc=com
objectClass: inetOrgPerson
cn: user001
sn: user001
userPassword: 123456
mail: user001@example.com

# User: User 2
dn: cn=user002,ou=users,dc=ldaplocal,dc=com
objectClass: inetOrgPerson
cn: user002
sn: user002
userPassword: 123456
mail: user002@example.com
```

to setup these configurations run the following commands: 

```
    docker compose exec openldap bash
    
    ldapadd -x -D "cn=admin,dc=ldaplocal,dc=com" -w admin_pass -f /path/to/roles.ldif
    ldapadd -x -D "cn=admin,dc=ldaplocal,dc=com" -w admin_pass -f /path/to/users.ldif
```

## Custom provider

this configuration does not manage roles in Symfony app. to manage roles, we can use a custom User Provider.

### the LdapUserProvider

```php
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

```

