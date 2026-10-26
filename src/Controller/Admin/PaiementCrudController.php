<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Paiement;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutPaiement;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Paiements en lecture seule : leur statut ne change que par le prestataire (ou l'action
 * « Virement reçu » de la commande), jamais par une saisie.
 */
final class PaiementCrudController extends AbstractCrudController
{
    private const COULEURS_STATUTS = [
        'Ouvert' => 'secondary',
        'EnAttente' => 'warning',
        'Autorise' => 'info',
        'Paye' => 'success',
        'Echoue' => 'danger',
        'Annule' => 'dark',
        'Expire' => 'dark',
        'Rembourse' => 'secondary',
        'PartiellementRembourse' => 'secondary',
    ];

    public static function getEntityFqcn(): string
    {
        return Paiement::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Paiement')
            ->setEntityLabelInPlural('Paiements')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['idExterne', 'referenceVirement', 'commande.numero', 'commande.email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('statut')
            ->add('prestataire')
            ->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('commande', 'Commande');
        yield ChoiceField::new('prestataire', 'Prestataire')->setChoices(PrestatairePaiement::cases());
        yield TextField::new('moyen', 'Moyen');
        yield MoneyField::new('montant', 'Montant')->setCurrency('EUR')->setStoredAsCents();
        yield ChoiceField::new('statut', 'Statut')
            ->setChoices(StatutPaiement::cases())
            ->renderAsBadges(self::COULEURS_STATUTS);
        yield TextField::new('idExterne', 'Référence prestataire');
        yield TextField::new('referenceVirement', 'Communication structurée')->onlyOnDetail();
        yield MoneyField::new('montantRembourse', 'Remboursé')->setCurrency('EUR')->setStoredAsCents()->onlyOnDetail();
        yield CollectionField::new('remboursements', 'Remboursements')->onlyOnDetail();
        yield Field::new('donnees', 'Informations du prestataire')
            ->onlyOnDetail()
            ->formatValue(static fn (?array $donnees): string => json_encode($donnees ?? [], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT) ?: '');
        yield DateTimeField::new('createdAt', 'Créé le');
        yield DateTimeField::new('payeAt', 'Payé le');
        yield DateTimeField::new('updatedAt', 'Mis à jour le')->onlyOnDetail();
    }
}
