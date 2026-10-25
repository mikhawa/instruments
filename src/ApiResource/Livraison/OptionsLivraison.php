<?php

declare(strict_types=1);

namespace App\ApiResource\Livraison;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Livraison\OptionsLivraisonProvider;

/**
 * GET /api/panier/livraison?pays=FR : modes de livraison possibles pour le panier courant.
 * Sans pays (ou pays non livrable), seuls les modes de retrait sont proposés.
 */
#[ApiResource(
    shortName: 'OptionsLivraison',
    operations: [
        new Get(
            uriTemplate: '/panier/livraison',
            provider: OptionsLivraisonProvider::class,
        ),
    ],
    paginationEnabled: false,
)]
final readonly class OptionsLivraison
{
    public const MESSAGE_PAYS_NON_LIVRABLE = 'pays_non_livrable';
    public const MESSAGE_RETRAIT_UNIQUEMENT = 'retrait_uniquement';
    public const MESSAGE_SUR_DEVIS = 'transport_sur_devis';

    /**
     * @param list<string>          $paysLivrables codes ISO, pour le sélecteur de pays
     * @param list<OptionLivraison> $options
     * @param list<string>          $messages      self::MESSAGE_*
     */
    public function __construct(
        public array $paysLivrables = [],
        public ?string $pays = null,
        public array $options = [],
        public array $messages = [],
    ) {
    }
}
