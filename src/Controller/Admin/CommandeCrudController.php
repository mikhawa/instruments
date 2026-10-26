<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Entity\Paiement;
use App\Enum\PrestatairePaiement;
use App\Service\Commande\VerrouStock;
use App\Service\Paiement\EncaissementPaiement;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Consultation des commandes.
 * Création et suppression interdites ; seul le numéro de suivi est modifiable.
 * Les changements de statut passent par le workflow « commande » (boutons du détail) ; le stock,
 * les dates et les e-mails sont gérés par les écouteurs du workflow, pas ici.
 * Les transitions « payer » et « rembourser » relèvent du paiement et ne sont pas exposées.
 *
 * @extends AbstractCrudController<Commande>
 */
final class CommandeCrudController extends AbstractCrudController
{
    /** Couleur de badge par statut (clé : nom du cas de l'énumération) */
    private const COULEURS_STATUTS = [
        'EnAttentePaiement' => 'secondary',
        'Payee' => 'warning',
        'EnPreparation' => 'info',
        'Expediee' => 'primary',
        'Livree' => 'success',
        'Annulee' => 'danger',
        'Retournee' => 'dark',
        'Remboursee' => 'secondary',
    ];

    /** Transitions proposées à l'administrateur : nom => [libellé, icône, style du bouton] */
    private const TRANSITIONS = [
        'preparer' => ['Préparer la commande', 'fa fa-box-open', 'btn btn-info'],
        'expedier' => ['Marquer comme expédiée', 'fa fa-truck', 'btn btn-primary'],
        'livrer' => ['Marquer comme livrée', 'fa fa-circle-check', 'btn btn-success'],
        'retourner' => ['Enregistrer un retour', 'fa fa-rotate-left', 'btn btn-secondary'],
        'annuler' => ['Annuler la commande', 'fa fa-ban', 'btn btn-danger'],
    ];

    public function __construct(
        #[Target('commande')]
        private readonly WorkflowInterface $workflow,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly VerrouStock $verrouStock,
        private readonly EncaissementPaiement $encaissement,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Commande::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['numero', 'numeroSuivi', 'email', 'utilisateur.email', 'utilisateur.nom']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE);

        foreach (self::TRANSITIONS as $transition => [$libelle, $icone, $classe]) {
            $action = Action::new('transition_'.$transition, $libelle, $icone)
                ->linkToUrl(fn (Commande $commande): string => $this->urlTransition($commande, $transition))
                ->renderAsForm()
                ->setCssClass($classe)
                ->displayIf(fn (Commande $commande): bool => $this->workflow->can($commande, $transition));
            if ('annuler' === $transition) {
                $action->askConfirmation('Annuler cette commande ? Le stock réservé sera libéré.', 'Annuler la commande');
            }
            $actions->add(Crud::PAGE_DETAIL, $action);
        }

        $actions->add(Crud::PAGE_DETAIL, Action::new('virement_recu', 'Virement reçu', 'fa fa-building-columns')
            ->linkToUrl(fn (Commande $commande): string => $this->urlVirementRecu($commande))
            ->renderAsForm()
            ->setCssClass('btn btn-success')
            ->displayIf(fn (Commande $commande): bool => null !== $this->virementAttendu($commande))
            ->askConfirmation('Le virement a-t-il bien été reçu sur le compte, avec le bon montant et la bonne communication structurée ?', 'Confirmer la réception'));

        return $actions;
    }

    /**
     * Applique une transition du workflow (POST + jeton CSRF propre à la commande et à la transition).
     * Le stock, les dates et les e-mails sont pris en charge par les écouteurs du workflow.
     */
    #[AdminRoute(path: '/{entityId}/transition', name: 'transition', options: ['methods' => ['POST']])]
    public function appliquerTransition(Request $request): RedirectResponse
    {
        $commande = $this->entityManager->find(Commande::class, (int) $request->attributes->get('entityId', $request->query->get('entityId')));
        $transition = (string) $request->query->get('transition');
        if (!$commande instanceof Commande || !isset(self::TRANSITIONS[$transition])) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid($this->idJeton($commande, $transition), (string) $request->query->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $libelle = self::TRANSITIONS[$transition][0];
        if ('expedier' === $transition && '' === trim((string) $commande->getNumeroSuivi())) {
            $this->addFlash('danger', 'Saisissez le numéro de suivi (bouton « Modifier ») avant de marquer la commande comme expédiée.');
        } elseif (!$this->workflow->can($commande, $transition)) {
            $this->addFlash('danger', sprintf('« %s » n\'est pas possible pour la commande %s dans son état actuel.', $libelle, $commande->getNumero()));
        } else {
            // Stocks verrouillés et relus : une commande créée au même instant ne peut pas fausser les quantités
            $connexion = $this->entityManager->getConnection();
            $connexion->beginTransaction();
            try {
                $this->verrouStock->verrouiller(array_map(static fn ($ligne) => $ligne->getInstrument(), $commande->getLignes()->toArray()));
                $this->workflow->apply($commande, $transition);
                $this->entityManager->flush();
                $connexion->commit();
                $this->addFlash('success', sprintf('Commande %s : « %s » effectué.', $commande->getNumero(), $libelle));
            } catch (LogicException|\DomainException $exception) {
                $connexion->rollBack();
                $this->addFlash('danger', sprintf('« %s » impossible pour la commande %s : %s', $libelle, $commande->getNumero(), $exception->getMessage()));
            } catch (\Throwable $exception) {
                $connexion->rollBack();
                throw $exception;
            }
        }

        return $this->redirect($this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($commande->getId())
            ->generateUrl());
    }

    /**
     * Virement constaté sur le compte : la commande est payée (ou reprise si sa réservation a expiré
     * entre-temps et que le stock le permet, sinon le remboursement est enregistré).
     */
    #[AdminRoute(path: '/{entityId}/virement-recu', name: 'virement_recu', options: ['methods' => ['POST']])]
    public function virementRecu(Request $request): RedirectResponse
    {
        $commande = $this->entityManager->find(Commande::class, (int) $request->attributes->get('entityId', $request->query->get('entityId')));
        if (!$commande instanceof Commande) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid($this->idJeton($commande, 'virement_recu'), (string) $request->query->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $paiement = $this->virementAttendu($commande);
        if (null === $paiement) {
            $this->addFlash('danger', sprintf('Aucun virement n\'est attendu pour la commande %s.', $commande->getNumero()));
        } else {
            $resultat = $this->encaissement->confirmerVirement($paiement);
            match ($resultat) {
                EncaissementPaiement::PAYEE, EncaissementPaiement::REPRISE => $this->addFlash('success', sprintf('Commande %s payée par virement.', $commande->getNumero())),
                EncaissementPaiement::REMBOURSE, EncaissementPaiement::DOUBLON => $this->addFlash('warning', sprintf('Commande %s : le virement ne peut pas être affecté (commande annulée ou déjà payée). Un remboursement est à effectuer à la main par virement.', $commande->getNumero())),
                default => $this->addFlash('danger', sprintf('Commande %s : virement non appliqué (%s), voir les journaux.', $commande->getNumero(), $resultat)),
            };
        }

        return $this->redirect($this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($commande->getId())
            ->generateUrl());
    }

    private function virementAttendu(Commande $commande): ?Paiement
    {
        foreach ($commande->getPaiements() as $paiement) {
            if (PrestatairePaiement::Virement === $paiement->getPrestataire() && $paiement->getStatut()->isEnCours()) {
                return $paiement;
            }
        }

        return null;
    }

    private function urlVirementRecu(Commande $commande): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setRoute('admin_commande_virement_recu', [
                'entityId' => $commande->getId(),
                '_token' => $this->csrfTokenManager->getToken($this->idJeton($commande, 'virement_recu'))->getValue(),
            ])
            ->generateUrl();
    }

    private function urlTransition(Commande $commande, string $transition): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setRoute('admin_commande_transition', [
                'entityId' => $commande->getId(),
                'transition' => $transition,
                '_token' => $this->csrfTokenManager->getToken($this->idJeton($commande, $transition))->getValue(),
            ])
            ->generateUrl();
    }

    private function idJeton(Commande $commande, string $transition): string
    {
        return 'commande_'.$commande->getId().'_'.$transition;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('statut')
            ->add('utilisateur')
            ->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        $formatterAdresse = static fn (array $adresse): string => implode(', ', array_filter([
            trim(($adresse['prenom'] ?? '').' '.($adresse['nom'] ?? '')),
            $adresse['societe'] ?? null,
            $adresse['rue'] ?? null,
            $adresse['complement'] ?? null,
            trim(($adresse['codePostal'] ?? '').' '.($adresse['ville'] ?? '')),
            $adresse['pays'] ?? null,
        ]));

        yield FormField::addFieldset('Commande');
        yield TextField::new('numero', 'Numéro')->setDisabled();
        yield TextField::new('nomClient', 'Client')
            ->formatValue(static fn (string $nom, Commande $commande): string => $commande->isInvite()
                ? 'Invité' : $nom)
            ->hideOnForm()
            ->setSortable(false);
        yield TextField::new('email', 'E-mail')->hideOnForm();
        yield TextField::new('telephone', 'Téléphone')->hideOnForm()->hideOnIndex();
        yield ChoiceField::new('statut', 'Statut')
            ->renderAsBadges(self::COULEURS_STATUTS)
            ->hideOnForm();
        yield MoneyField::new('totalTtc', 'Total TTC')->setCurrency('EUR')->setStoredAsCents()->hideOnForm();
        yield DateTimeField::new('createdAt', 'Passée le')->hideOnForm();
        yield TextField::new('numeroSuivi', 'N° de suivi')->setRequired(false);

        yield FormField::addFieldset('Détail')->hideOnForm();
        yield CollectionField::new('lignes', 'Articles')->onlyOnDetail();
        yield MoneyField::new('totalHt', 'Total HT')->setCurrency('EUR')->setStoredAsCents()->onlyOnDetail();
        yield MoneyField::new('totalTva', 'TVA')->setCurrency('EUR')->setStoredAsCents()->onlyOnDetail();
        yield TextField::new('libelleLivraison', 'Mode de livraison')->onlyOnDetail();
        yield MoneyField::new('fraisPortTtc', 'Frais de port TTC')->setCurrency('EUR')->setStoredAsCents()->onlyOnDetail();
        yield TextField::new('locale', 'Langue')->onlyOnDetail();
        yield TextField::new('canal', 'Canal')->onlyOnDetail();
        yield TextField::new('cgvVersion', 'Version des CGV acceptées')->onlyOnDetail();
        yield DateTimeField::new('cgvAccepteesAt', 'CGV acceptées le')->onlyOnDetail();
        yield TextareaField::new('commentaireClient', 'Commentaire du client')->onlyOnDetail();

        yield FormField::addFieldset('Adresses')->hideOnForm();
        yield Field::new('adresseLivraison', 'Livraison')
            ->onlyOnDetail()
            ->formatValue(static fn (array $adresse): string => $formatterAdresse($adresse));
        yield Field::new('adresseFacturation', 'Facturation')
            ->onlyOnDetail()
            ->formatValue(static fn (array $adresse): string => $formatterAdresse($adresse));

        yield FormField::addFieldset('Paiement')->hideOnForm();
        yield CollectionField::new('paiements', 'Paiements')->onlyOnDetail();
        yield Field::new('motifAnnulation', 'Motif d\'annulation')
            ->onlyOnDetail()
            ->formatValue(static fn ($motif): ?string => $motif?->value);

        yield FormField::addFieldset('Suivi')->hideOnForm();
        yield DateTimeField::new('expireAt', 'Réservation valable jusqu\'au')->onlyOnDetail();
        yield DateTimeField::new('paidAt', 'Payée le')->onlyOnDetail();
        yield DateTimeField::new('shippedAt', 'Expédiée le')->onlyOnDetail();
        yield DateTimeField::new('deliveredAt', 'Livrée le')->onlyOnDetail();
        yield DateTimeField::new('cancelledAt', 'Annulée le')->onlyOnDetail();
    }
}
