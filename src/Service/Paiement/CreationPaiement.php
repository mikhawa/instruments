<?php

declare(strict_types=1);

namespace App\Service\Paiement;

use App\Entity\Commande;
use App\Entity\Paiement;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutCommande;
use App\Enum\StatutPaiement;
use App\Repository\PaiementRepository;
use App\Service\Commande\NotificationCommande;
use App\Service\Paiement\Virement\CommunicationStructuree;
use App\Service\Paiement\Virement\CoordonneesVirement;
use App\Service\UrlSite;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Démarre le paiement d'une commande en attente (prepare/03-paiement.md § 3 et § 6) :
 *  - en ligne : paiement créé chez le prestataire, le client est envoyé sur sa page ;
 *  - par virement : communication structurée attribuée, réservation prolongée à 5 jours ouvrables (D4).
 *
 * Le montant vient toujours de la commande, jamais du client. La commande est verrouillée pendant
 * l'enregistrement (même ordre de verrous qu'EncaissementPaiement) ; l'appel au prestataire a lieu
 * après la validation, pour ne pas tenir le verrou pendant l'attente réseau.
 */
#[WithMonologChannel('paiement')]
final readonly class CreationPaiement
{
    /** Une page de paiement ouverte depuis moins longtemps est réutilisée plutôt que d'en créer une autre */
    private const REUTILISATION = '-10 minutes';
    private const JOURS_OUVRABLES_VIREMENT = 5;

    public function __construct(
        private Prestataires $prestataires,
        private CoordonneesVirement $coordonneesVirement,
        private PaiementRepository $paiementRepository,
        private EntityManagerInterface $entityManager,
        private NotificationCommande $notification,
        private UrlSite $urlSite,
        private LoggerInterface $logger,
        #[Autowire(env: 'DEFAULT_URI')]
        private string $urlServeur,
    ) {
    }

    /**
     * Moyens proposés pour cette commande, dans l'ordre d'affichage : « en_ligne », « virement ».
     *
     * @return list<string>
     */
    public function moyensDisponibles(Commande $commande): array
    {
        if (!$this->isPayable($commande)) {
            return [];
        }

        return array_values(array_filter([
            null !== $this->prestataires->enLigne() ? 'en_ligne' : null,
            $this->coordonneesVirement->isDisponiblePour($commande->getTotalTtc()) ? 'virement' : null,
        ]));
    }

    public function isPayable(Commande $commande): bool
    {
        return StatutCommande::EnAttentePaiement === $commande->getStatut()
            && null !== $commande->getExpireAt()
            && $commande->getExpireAt() > new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * @throws PaiementRefuseException
     * @throws PrestataireIndisponibleException
     */
    public function enLigne(Commande $commande): Paiement
    {
        $prestataire = $this->prestataires->enLigne() ?? throw new PaiementRefuseException('Le paiement en ligne est momentanément indisponible. Choisissez le virement ou réessayez plus tard.');

        $paiement = $this->dansUneTransaction($commande, function () use ($commande, $prestataire): Paiement {
            $ouvert = $this->paiementRepository->enLigneOuvert($commande, $prestataire->code());
            if (null !== $ouvert && $ouvert->getCreatedAt() > new \DateTimeImmutable(self::REUTILISATION, new \DateTimeZone('UTC'))) {
                return $ouvert;
            }

            $paiement = new Paiement($commande, $prestataire->code(), $commande->getTotalTtc());
            $this->entityManager->persist($paiement);

            return $paiement;
        });
        if (null !== $paiement->getUrlPaiement()) {
            return $paiement;
        }

        try {
            $cree = $prestataire->creer($paiement, $this->urlRetour($commande), $this->urlNotification($prestataire->code()));
        } catch (PrestataireIndisponibleException $e) {
            $paiement->setStatut(StatutPaiement::Echoue)->ajouterDonnees(['erreur' => mb_substr($e->getMessage(), 0, 255)]);
            $this->entityManager->flush();
            $this->logger->error('Paiement de la commande {numero} non créé : {message}', ['numero' => $commande->getNumero(), 'message' => $e->getMessage()]);

            throw $e;
        }

        $paiement->ouvrir($cree->idExterne, $cree->urlPaiement);
        $this->entityManager->flush();

        return $paiement;
    }

    /**
     * @throws PaiementRefuseException
     */
    public function parVirement(Commande $commande): Paiement
    {
        if (!$this->coordonneesVirement->isDisponiblePour($commande->getTotalTtc())) {
            throw new PaiementRefuseException('Le virement n\'est pas proposé pour cette commande.');
        }

        $nouveau = false;
        $paiement = $this->dansUneTransaction($commande, function () use ($commande, &$nouveau): Paiement {
            foreach ($commande->getPaiements() as $existant) {
                if (PrestatairePaiement::Virement === $existant->getPrestataire() && $existant->getStatut()->isEnCours()) {
                    return $existant;
                }
            }

            $paiement = (new Paiement($commande, PrestatairePaiement::Virement, $commande->getTotalTtc()))
                ->setStatut(StatutPaiement::EnAttente)
                ->setMoyen('virement');
            $this->entityManager->persist($paiement);
            $this->entityManager->flush();
            // La référence suit l'identifiant du paiement : unique, et contrôlable par la banque (modulo 97)
            $paiement->setReferenceVirement(CommunicationStructuree::depuis((int) $paiement->getId()));

            $limite = self::ajouterJoursOuvrables(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Brussels')), self::JOURS_OUVRABLES_VIREMENT)
                ->setTimezone(new \DateTimeZone('UTC'));
            if ($limite > $commande->getExpireAt()) {
                $commande->setExpireAt($limite);
            }
            $nouveau = true;

            return $paiement;
        });

        if ($nouveau) {
            try {
                $this->notification->instructionsVirement($paiement);
            } catch (\Throwable $e) {
                $this->logger->error('E-mail de virement non envoyé : {message}', ['message' => $e->getMessage(), 'numero' => $commande->getNumero()]);
            }
        }

        return $paiement;
    }

    /**
     * Fin de journée (23 h 59, heure de Bruxelles) après n jours ouvrables (samedis et dimanches exclus).
     */
    public static function ajouterJoursOuvrables(\DateTimeImmutable $depart, int $jours): \DateTimeImmutable
    {
        $date = $depart;
        while ($jours > 0) {
            $date = $date->modify('+1 day');
            if ((int) $date->format('N') <= 5) {
                --$jours;
            }
        }

        return $date->setTime(23, 59);
    }

    /**
     * Exécute $operation sous verrou de la commande, si elle peut encore être payée.
     *
     * @param callable(): Paiement $operation
     *
     * @throws PaiementRefuseException
     */
    private function dansUneTransaction(Commande $commande, callable $operation): Paiement
    {
        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();
        try {
            $this->entityManager->refresh($commande, LockMode::PESSIMISTIC_WRITE);
            if (!$this->isPayable($commande)) {
                throw new PaiementRefuseException(StatutCommande::EnAttentePaiement === $commande->getStatut() ? 'La réservation de cette commande a expiré. Repassez commande depuis votre panier.' : 'Cette commande n\'attend plus de paiement.');
            }
            $paiement = $operation();
            $this->entityManager->flush();
            $connexion->commit();
        } catch (\Throwable $e) {
            $connexion->rollBack();
            throw $e;
        }

        return $paiement;
    }

    /**
     * Retour du client après le paiement : page de confirmation, avec le jeton (session perdue, autre appareil).
     */
    private function urlRetour(Commande $commande): string
    {
        return $this->urlSite->absolue('/commande/confirmation/'.$commande->getNumero(), ['jeton' => $commande->getJetonSuivi()->toRfc4122(), 'retour' => '1']);
    }

    /**
     * Adresse de notification, si le prestataire peut la joindre : pas en développement local
     * (le retour du client et la synchronisation planifiée prennent alors le relais).
     */
    private function urlNotification(PrestatairePaiement $prestataire): ?string
    {
        if (PrestatairePaiement::Mollie !== $prestataire) {
            return null;
        }
        $hote = (string) parse_url($this->urlServeur, \PHP_URL_HOST);
        $local = '' === $hote || 'localhost' === $hote || preg_match('/\.(localhost|local|test|internal)$/', $hote)
            || (false !== filter_var($hote, \FILTER_VALIDATE_IP) && false === filter_var($hote, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE));

        return $local ? null : rtrim($this->urlServeur, '/').'/api/webhooks/mollie';
    }
}
