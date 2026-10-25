<?php

declare(strict_types=1);

namespace App\ApiResource\Panier;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Panier\AjoutLigneProcessor;
use App\State\Panier\ModificationLigneProcessor;
use App\State\Panier\PanierProvider;
use App\State\Panier\SuppressionLigneProcessor;
use App\State\Panier\ViderPanierProcessor;

/**
 * Panier courant (cookie « panier » du visiteur ou session du client), entièrement
 * recalculé par le serveur : chaque opération renvoie le panier complet.
 * Le navigateur n'envoie que des instruments et des quantités, jamais de prix.
 * Montants en centimes TTC.
 */
#[ApiResource(
    shortName: 'Panier',
    operations: [
        new Get(
            uriTemplate: '/panier',
            provider: PanierProvider::class,
        ),
        new Delete(
            uriTemplate: '/panier',
            provider: PanierProvider::class,
            processor: ViderPanierProcessor::class,
        ),
        new Post(
            uriTemplate: '/panier/lignes',
            status: 200,
            input: AjoutLigne::class,
            read: false,
            processor: AjoutLigneProcessor::class,
        ),
        new Patch(
            uriTemplate: '/panier/lignes/{id}',
            requirements: ['id' => '\d+'],
            input: ModificationLigne::class,
            read: false,
            processor: ModificationLigneProcessor::class,
        ),
        new Delete(
            uriTemplate: '/panier/lignes/{id}',
            requirements: ['id' => '\d+'],
            status: 200,
            read: false,
            processor: SuppressionLigneProcessor::class,
        ),
    ],
    paginationEnabled: false,
)]
final readonly class PanierCalcule
{
    /**
     * @param list<LignePanierCalculee> $lignes
     */
    public function __construct(
        public array $lignes = [],
        /** Articles commandables (quantités cumulées), pour le compteur de l'en-tête */
        public int $nombreArticles = 0,
        public int $sousTotalTtc = 0,
        public int $totalHt = 0,
        public int $totalTva = 0,
        /** Calculés à l'étape livraison du tunnel de commande */
        public ?int $fraisPortTtc = null,
        public int $totalTtc = 0,
        /** Faux si le panier est vide ou contient un instrument indisponible */
        public bool $commandable = false,
    ) {
    }
}
