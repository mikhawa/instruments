<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Instrument;
use App\Entity\InstrumentTraduction;
use App\Form\Admin\InstrumentTraductionType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Gestion des instruments du catalogue.
 * Les quantités en stock sont en lecture seule : elles évoluent via les mouvements de stock.
 *
 * @extends AbstractCrudController<Instrument>
 */
final class InstrumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Instrument::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Instrument')
            ->setEntityLabelInPlural('Instruments')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['reference', 'facteur', 'regionOrigine', 'traductions.nom']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Pas de suppression : l'historique (commandes, mouvements de stock) référence l'instrument.
        // Pour le retirer de la vente, le dépublier.
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('categorie')
            ->add('etat')
            ->add('published')
            ->add('pieceUnique')
            ->add('paysOrigine');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Général', 'fa fa-guitar');
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('reference', 'Référence')->setHelp('SKU interne, ex. CRD-OUD-0042 (converti en majuscules).');
        yield TextField::new('nom', 'Nom')->hideOnForm()->setSortable(false);
        yield AssociationField::new('categorie', 'Catégorie');
        yield ChoiceField::new('etat', 'État');
        yield BooleanField::new('pieceUnique', 'Pièce unique')->hideOnIndex();
        yield BooleanField::new('published', 'Publié');

        yield FormField::addTab('Prix', 'fa fa-euro-sign');
        yield MoneyField::new('prixHt', 'Prix HT')
            ->setCurrency('EUR')
            ->setStoredAsCents()
            ->setFormTypeOption('input', 'integer')
            ->hideOnIndex();
        yield IntegerField::new('tauxTva', 'Taux de TVA')
            ->setHelp('En points de base : 2100 = 21 %, 600 = 6 %.')
            ->formatValue(static fn (int $taux): string => sprintf('%s %%', rtrim(rtrim(number_format($taux / 100, 2, ',', ''), '0'), ',')))
            ->hideOnIndex();
        yield MoneyField::new('prixTtc', 'Prix TTC')
            ->setCurrency('EUR')
            ->setStoredAsCents()
            ->hideOnForm();

        yield FormField::addTab('Origine et fabrication', 'fa fa-earth-africa');
        yield CountryField::new('paysOrigine', 'Pays d\'origine')->hideOnIndex();
        yield TextField::new('regionOrigine', 'Région d\'origine')->hideOnIndex();
        yield TextField::new('facteur', 'Facteur / atelier')->hideOnIndex();
        yield IntegerField::new('anneeFabrication', 'Année de fabrication')->hideOnIndex();
        yield IntegerField::new('poidsGrammes', 'Poids (g)')->hideOnIndex();
        yield TextField::new('dimensions', 'Dimensions')->setHelp('Ex. « 78 × 36 × 18 cm »')->hideOnIndex();

        yield FormField::addTab('Traductions', 'fa fa-language');
        yield CollectionField::new('traductions', 'Traductions')
            ->onlyOnForms()
            ->setEntryType(InstrumentTraductionType::class)
            ->setEntryIsComplex()
            ->allowAdd()
            ->allowDelete()
            ->renderExpanded()
            ->setFormTypeOption('by_reference', false)
            ->setHelp('Au moins une traduction est requise (le français sert de référence).');
        yield CollectionField::new('traductions', 'Traductions')
            ->onlyOnDetail()
            ->formatValue(static fn ($traductions, Instrument $instrument): string => implode(', ', array_map(
                static fn (InstrumentTraduction $traduction): string => sprintf('%s : %s', strtoupper((string) $traduction->getLocale()), $traduction->getNom()),
                $instrument->getTraductions()->getValues(),
            )));

        yield FormField::addTab('Stock', 'fa fa-boxes-stacked')->hideOnForm();
        yield IntegerField::new('quantiteDisponible', 'Disponible')->hideOnForm()->setSortable(false);
        yield IntegerField::new('stock.quantite', 'Quantité en stock')->onlyOnDetail();
        yield IntegerField::new('stock.quantiteReservee', 'Quantité réservée')->onlyOnDetail();
        yield IntegerField::new('stock.seuilAlerte', 'Seuil d\'alerte')->onlyOnDetail();
        yield TextField::new('stock.emplacement', 'Emplacement')->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'Créé le')->onlyOnDetail();
        yield DateTimeField::new('updatedAt', 'Modifié le')->onlyOnDetail();
    }
}
