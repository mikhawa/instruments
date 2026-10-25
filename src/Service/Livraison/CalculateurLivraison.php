<?php

declare(strict_types=1);

namespace App\Service\Livraison;

use App\ApiResource\Livraison\OptionLivraison;
use App\ApiResource\Livraison\OptionsLivraison;
use App\Entity\Panier;
use App\Enum\ClasseLivraison;
use App\Repository\ModeLivraisonRepository;
use App\Repository\TarifLivraisonRepository;
use App\Repository\ZoneLivraisonRepository;

/**
 * Frais de port d'un panier (prepare/05-livraison.md, D9) : un seul envoi par commande,
 * tarifé selon la zone du pays et la plus grande classe de colis du panier.
 *
 *  - un instrument « retrait uniquement » : seuls les modes de retrait sont proposés ;
 *  - un instrument « transport dédié » : livraison sur devis, retrait seulement en ligne ;
 *  - les modes de retrait sont gratuits et ne dépendent ni du pays ni de la classe.
 */
final readonly class CalculateurLivraison
{
    public function __construct(
        private ZoneLivraisonRepository $zoneLivraisonRepository,
        private ModeLivraisonRepository $modeLivraisonRepository,
        private TarifLivraisonRepository $tarifLivraisonRepository,
    ) {
    }

    public function options(?Panier $panier, ?string $pays): OptionsLivraison
    {
        $pays = null !== $pays && '' !== $pays ? strtoupper($pays) : null;
        $paysLivrables = $this->zoneLivraisonRepository->paysLivrables();

        if (null === $panier || $panier->isVide()) {
            return new OptionsLivraison($paysLivrables, $pays);
        }

        $options = array_map(
            static fn ($mode): OptionLivraison => new OptionLivraison((string) $mode->getCode(), (string) $mode->getNom(), $mode->getDescription(), 0, null, null, true),
            $this->modeLivraisonRepository->modesRetraitActifs(),
        );
        $messages = [];
        $classe = $this->classeDuPanier($panier);

        if (ClasseLivraison::RetraitUniquement === $classe) {
            $messages[] = OptionsLivraison::MESSAGE_RETRAIT_UNIQUEMENT;
        } elseif (ClasseLivraison::TransportDedie === $classe) {
            $messages[] = OptionsLivraison::MESSAGE_SUR_DEVIS;
        } elseif (null !== $pays) {
            $zone = $this->zoneLivraisonRepository->trouverPourPays($pays);
            if (null === $zone) {
                $messages[] = OptionsLivraison::MESSAGE_PAYS_NON_LIVRABLE;
            } else {
                $expeditions = [];
                foreach ($this->tarifLivraisonRepository->tarifsPour($zone, $classe) as $tarif) {
                    $mode = $tarif->getMode();
                    $expeditions[] = new OptionLivraison((string) $mode?->getCode(), (string) $mode?->getNom(), $mode?->getDescription(), (int) $tarif->getPrixTtc(), $tarif->getDelaiJoursMin(), $tarif->getDelaiJoursMax(), false);
                }
                // Expédition d'abord, retrait ensuite
                $options = [...$expeditions, ...$options];
            }
        }

        return new OptionsLivraison($paysLivrables, $pays, $options, $messages);
    }

    /**
     * Option choisie par le client, si elle est proposée pour ce panier et ce pays.
     */
    public function option(?Panier $panier, ?string $pays, string $code): ?OptionLivraison
    {
        foreach ($this->options($panier, $pays)->options as $option) {
            if ($option->code === $code) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Classe la plus contraignante du panier : retrait uniquement, puis transport dédié,
     * puis la plus grande classe expédiable.
     */
    public function classeDuPanier(Panier $panier): ClasseLivraison
    {
        $classes = array_map(static fn ($ligne): ClasseLivraison => $ligne->getInstrument()->getClasseLivraison(), $panier->getLignes()->toArray());

        foreach ([ClasseLivraison::RetraitUniquement, ClasseLivraison::TransportDedie] as $contrainte) {
            if (\in_array($contrainte, $classes, true)) {
                return $contrainte;
            }
        }
        usort($classes, static fn (ClasseLivraison $a, ClasseLivraison $b): int => $b->rang() <=> $a->rang());

        return $classes[0] ?? ClasseLivraison::Colis;
    }
}
