<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Facturation\Vendeur;
use App\Service\Paiement\Mollie\MolliePrestataire;
use App\Service\Paiement\Virement\CoordonneesVirement;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Contrôle de la configuration du paiement, lancé au déploiement (deploy/activer-release.sh) :
 *  - clé Mollie du mauvais type (test_… en production, live_… en préprod) → échec du déploiement ;
 *  - clé absente, virement ou identité du vendeur (factures) non configurés → avertissement seulement (le site reste utilisable,
 *    le paiement en ligne est indisponible tant que le compte Mollie n'est pas ouvert).
 */
#[AsCommand(name: 'app:paiement:verifier-configuration', description: 'Vérifie la clé Mollie et le compte du virement')]
final readonly class VerifierConfigurationPaiementCommand
{
    public function __construct(
        private MolliePrestataire $mollie,
        private CoordonneesVirement $coordonneesVirement,
        private Vendeur $vendeur,
        #[Autowire(env: 'MOLLIE_API_KEY')]
        private string $cleMollie,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Type de clé Mollie attendu : « live » (production) ou « test » (préprod)')]
        ?string $attendu = null,
    ): int {
        if (null !== $attendu && !\in_array($attendu, ['live', 'test'], true)) {
            $io->error('--attendu vaut « live » ou « test ».');

            return Command::INVALID;
        }

        $erreurs = [];
        if ('' === trim($this->cleMollie)) {
            $io->warning('MOLLIE_API_KEY est vide : le paiement en ligne est indisponible (simulation hors production seulement).');
        } elseif (!$this->mollie->isConfigure()) {
            $erreurs[] = 'MOLLIE_API_KEY n\'a pas le format d\'une clé Mollie (test_… ou live_…).';
        } elseif (null !== $attendu && !str_starts_with($this->cleMollie, $attendu.'_')) {
            $erreurs[] = \sprintf('Clé Mollie « %s_… » alors que cet environnement attend une clé « %s_… ».', strstr($this->cleMollie, '_', true), $attendu);
        } else {
            $io->writeln(\sprintf('Clé Mollie : %s_…', strstr($this->cleMollie, '_', true)));
        }

        if ($this->coordonneesVirement->isConfigure()) {
            $io->writeln(\sprintf('Virement : %s, %s (à partir de %d €)', $this->coordonneesVirement->getBeneficiaire(), $this->coordonneesVirement->getIbanLisible(), intdiv($this->coordonneesVirement->getSeuil(), 100)));
        } else {
            $io->warning('VIREMENT_IBAN ou VIREMENT_BENEFICIAIRE absent : le virement n\'est pas proposé.');
        }

        if (!$this->vendeur->isConfigure()) {
            $io->warning('VENDEUR_NOM, VENDEUR_ADRESSE ou VENDEUR_TVA absent : les factures seraient émises sans l\'identité du vendeur.');
        }

        if ([] !== $erreurs) {
            $io->error($erreurs);

            return Command::FAILURE;
        }
        $io->success('Configuration du paiement vérifiée.');

        return Command::SUCCESS;
    }
}
