<?php

declare(strict_types=1);

namespace App\Service\Commande;

use App\ApiResource\Commande\NouvelleCommande;
use App\ApiResource\Panier\LignePanierCalculee;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\MouvementStock;
use App\Entity\Panier;
use App\Entity\Utilisateur;
use App\Enum\TypeMouvementStock;
use App\EventListener\Workflow\EmailCommandeListener;
use App\Repository\CommandeRepository;
use App\Repository\ModeLivraisonRepository;
use App\Service\Livraison\CalculateurLivraison;
use App\Service\Panier\CalculateurPanier;
use App\Service\Panier\PanierCourant;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Transforme le panier courant en commande « en attente de paiement » et réserve le stock
 * (prepare/02-tunnel-commande.md § 4).
 *
 * Dans une seule transaction :
 *  1. les commandes non payées du même panier sont annulées (leur stock est libéré) ;
 *  2. les stocks du panier sont verrouillés puis relus ;
 *  3. le panier est recalculé : un instrument indisponible ou un total différent de celui
 *     affiché au client → refus « conflit » (409), le client revoit son panier ;
 *  4. la commande et ses lignes figées sont créées, le stock réservé et tracé.
 *
 * Le panier n'est pas vidé : il le sera au paiement confirmé (phase 3).
 */
final readonly class CreationCommande
{
    /** Durée de la réservation pour un paiement en ligne (D4) */
    public const DUREE_RESERVATION = '+30 minutes';

    public function __construct(
        private PanierCourant $panierCourant,
        private CalculateurPanier $calculateurPanier,
        private CalculateurLivraison $calculateurLivraison,
        private VerrouStock $verrouStock,
        private CommandeRepository $commandeRepository,
        private ModeLivraisonRepository $modeLivraisonRepository,
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $registry,
        private Security $security,
        private NotificationCommande $notification,
        private LoggerInterface $logger,
        #[Target('commande')]
        private WorkflowInterface $workflow,
        #[Autowire(param: 'app.cgv_version')]
        private string $cgvVersion,
    ) {
    }

    /**
     * @throws CommandeRefuseeException
     */
    public function creer(NouvelleCommande $demande, ?string $cleIdempotence): Commande
    {
        $panier = $this->panierCourant->trouver();
        if (null === $panier || $panier->isVide()) {
            throw new CommandeRefuseeException('Votre panier est vide.', conflit: true);
        }

        $existante = null !== $cleIdempotence ? $this->commandeRepository->findOneBy(['cleIdempotence' => $cleIdempotence]) : null;
        if (null !== $existante) {
            return $this->commandeDejaCreee($existante, $panier);
        }

        $utilisateur = $this->security->getUser();
        $utilisateur = $utilisateur instanceof Utilisateur ? $utilisateur : null;
        $email = $utilisateur?->getEmail() ?? $demande->email;
        if (null === $email || '' === trim($email)) {
            throw new CommandeRefuseeException('Indiquez votre adresse e-mail.');
        }

        $facturation = $demande->adresseFacturation ?? $demande->adresseLivraison;
        if (null === $facturation) {
            throw new CommandeRefuseeException('Indiquez votre adresse.');
        }
        $option = $this->calculateurLivraison->option($panier, $demande->adresseLivraison?->pays, $demande->modeLivraison);
        if (null === $option) {
            throw new CommandeRefuseeException('Ce mode de livraison n\'est pas disponible pour votre panier et ce pays.', conflit: true);
        }
        if (!$option->retrait && null === $demande->adresseLivraison) {
            throw new CommandeRefuseeException('Indiquez l\'adresse de livraison.');
        }
        $mode = $this->modeLivraisonRepository->findOneBy(['code' => $option->code]);
        \assert(null !== $mode);
        $livraison = $option->retrait ? $facturation : $demande->adresseLivraison;
        \assert(null !== $livraison);

        $connexion = $this->entityManager->getConnection();
        $connexion->beginTransaction();
        try {
            $precedentes = $this->commandeRepository->commandesEnAttenteDuPanier($panier);
            $this->verrouStock->verrouiller([
                ...array_map(static fn ($ligne) => $ligne->getInstrument(), $panier->getLignes()->toArray()),
                ...array_merge([], ...array_map(static fn (Commande $c): array => array_map(static fn ($l) => $l->getInstrument(), $c->getLignes()->toArray()), $precedentes)),
            ]);

            // Le client recommande depuis le même panier : la commande précédente non payée est remplacée
            foreach ($precedentes as $precedente) {
                $this->workflow->apply($precedente, 'annuler', [EmailCommandeListener::CONTEXTE_REMPLACEE => true]);
            }

            $calcul = $this->calculateurPanier->calculer($panier);
            foreach ($calcul->lignes as $ligne) {
                if (!$ligne->disponible || \in_array(LignePanierCalculee::ALERTE_QUANTITE_REDUITE, $ligne->alertes, true)) {
                    throw new CommandeRefuseeException('La disponibilité d\'un instrument a changé. Vérifiez votre panier.', conflit: true);
                }
            }
            if ($calcul->totalTtc + $option->prixTtc !== $demande->totalTtcAttendu) {
                throw new CommandeRefuseeException('Les montants ont changé depuis l\'affichage du panier. Vérifiez le nouveau total.', conflit: true);
            }

            $commande = (new Commande())
                ->setNumero('TMP-'.bin2hex(random_bytes(8)))
                ->setUtilisateur($utilisateur)
                ->setTelephone($this->vide($demande->telephone))
                ->setLocale($panier->getLocale())
                ->setAdresseLivraison($livraison->versAdresse())
                ->setAdresseFacturation($facturation->versAdresse())
                ->setCommentaireClient($this->vide($demande->commentaire))
                ->setPanier($panier)
                ->setCleIdempotence($cleIdempotence)
                ->setExpireAt(new \DateTimeImmutable(self::DUREE_RESERVATION, new \DateTimeZone('UTC')))
                ->accepterCgv($this->cgvVersion);
            if (null === $utilisateur) {
                $commande->setEmail($email);
            }

            foreach ($panier->getLignes() as $lignePanier) {
                $instrument = $lignePanier->getInstrument();
                $quantite = $lignePanier->getQuantite();
                $commande->addLigne(new LigneCommande($instrument, $quantite, $panier->getLocale()));
                $instrument->getStock()?->reserver($quantite);
                $this->entityManager->persist(new MouvementStock($instrument, TypeMouvementStock::Reservation, -$quantite, $commande));
            }
            $commande->choisirLivraison($mode, $option->prixTtc);

            if ($commande->getTotalTtc() !== $demande->totalTtcAttendu) {
                // Garde-fou : le panier et la commande doivent appliquer exactement la même règle de calcul
                throw new \LogicException(\sprintf('Total de la commande (%d) différent du panier (%d).', $commande->getTotalTtc(), $demande->totalTtcAttendu));
            }

            $this->entityManager->persist($commande);
            $this->entityManager->flush();
            $commande->setNumero(\sprintf('CMD-%s-%06d', $commande->getCreatedAt()->format('Y'), $commande->getId()));
            $this->entityManager->flush();
            $connexion->commit();
        } catch (UniqueConstraintViolationException $e) {
            $connexion->rollBack();
            // Double envoi simultané avec la même clé : l'autre requête a créé la commande
            if (null === $cleIdempotence) {
                throw $e;
            }
            $this->registry->resetManager();
            $existante = $this->commandeRepository->findOneBy(['cleIdempotence' => $cleIdempotence]) ?? throw $e;

            return $this->commandeDejaCreee($existante, $panier);
        } catch (\Throwable $e) {
            $connexion->rollBack();
            throw $e;
        }

        try {
            $this->notification->commandeEnregistree($commande);
        } catch (\Throwable $e) {
            // La commande est enregistrée : un e-mail en échec ne doit pas la faire paraître refusée
            $this->logger->error('E-mail de commande non envoyé : {message}', ['message' => $e->getMessage(), 'numero' => $commande->getNumero()]);
        }

        return $commande;
    }

    /**
     * Même clé d'idempotence : la commande déjà créée, si elle vient bien de ce panier.
     */
    private function commandeDejaCreee(Commande $commande, Panier $panier): Commande
    {
        if ($commande->getPanier()?->getId() !== $panier->getId()) {
            throw new CommandeRefuseeException('Cette clé d\'idempotence a déjà servi.', conflit: true);
        }

        return $commande;
    }

    private function vide(?string $valeur): ?string
    {
        $valeur = null !== $valeur ? trim($valeur) : null;

        return '' === $valeur ? null : $valeur;
    }
}
