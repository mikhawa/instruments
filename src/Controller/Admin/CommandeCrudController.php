<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Commande;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Consultation des commandes.
 * Création et suppression interdites ; seul le numéro de suivi est modifiable.
 * Les changements de statut passeront par Symfony Workflow (actions dédiées à venir).
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
    ];

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
            ->setSearchFields(['numero', 'numeroSuivi', 'utilisateur.email', 'utilisateur.nom']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE);
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
        yield AssociationField::new('utilisateur', 'Client')->hideOnForm();
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
        yield MoneyField::new('fraisPortTtc', 'Frais de port TTC')->setCurrency('EUR')->setStoredAsCents()->onlyOnDetail();
        yield TextField::new('locale', 'Langue')->onlyOnDetail();
        yield TextareaField::new('commentaireClient', 'Commentaire du client')->onlyOnDetail();

        yield FormField::addFieldset('Adresses')->hideOnForm();
        yield Field::new('adresseLivraison', 'Livraison')
            ->onlyOnDetail()
            ->formatValue(static fn (array $adresse): string => $formatterAdresse($adresse));
        yield Field::new('adresseFacturation', 'Facturation')
            ->onlyOnDetail()
            ->formatValue(static fn (array $adresse): string => $formatterAdresse($adresse));

        yield FormField::addFieldset('Suivi')->hideOnForm();
        yield DateTimeField::new('paidAt', 'Payée le')->onlyOnDetail();
        yield DateTimeField::new('shippedAt', 'Expédiée le')->onlyOnDetail();
        yield DateTimeField::new('deliveredAt', 'Livrée le')->onlyOnDetail();
        yield DateTimeField::new('cancelledAt', 'Annulée le')->onlyOnDetail();
    }
}
