<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * URL absolue d'une page du site React, pour les e-mails.
 * FRONT_URL si elle est absolue (dev : serveur Vite), sinon DEFAULT_URI (préprod, prod).
 */
final readonly class UrlSite
{
    public function __construct(
        #[Autowire(env: 'FRONT_URL')]
        private string $urlFront,
        #[Autowire(env: 'DEFAULT_URI')]
        private string $urlParDefaut,
    ) {
    }

    /**
     * @param array<string, string> $parametres
     */
    public function absolue(string $chemin, array $parametres = []): string
    {
        $base = str_contains($this->urlFront, '://') ? $this->urlFront : $this->urlParDefaut;
        $requete = [] !== $parametres ? '?'.http_build_query($parametres) : '';

        return rtrim($base, '/').'/'.ltrim($chemin, '/').$requete;
    }
}
