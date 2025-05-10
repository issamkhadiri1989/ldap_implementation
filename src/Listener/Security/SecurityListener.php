<?php

declare(strict_types=1);

namespace App\Listener\Security;

use App\Security\Ldap\LdapEntryProviderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class, method: 'handleSuccessEvent')]
class SecurityListener
{
    public function __construct(
        private readonly LdapEntryProviderInterface $fetcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function handleSuccessEvent(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        $entry = $this->fetcher->getUserEntry($user->getUserIdentifier());

        $fullName = $entry->getAttribute('displayName')[0];

        $session = $this->requestStack->getSession();

        $session->set('fullName', $fullName);
    }
}
