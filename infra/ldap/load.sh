#!/bin/bash
set -x # Enable debug mode to show commands
echo "Starting LDIF import..."

ldapadd -x -D "cn=admin,dc=ldaplocal,dc=com" -w admin_pass -f roles.ldif
ldapadd -x -D "cn=admin,dc=ldaplocal,dc=com" -w admin_pass -f users.ldif