<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TarifLivraison;
use App\Enum\ClasseLivraison;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Grille tarifaire de livraison : un prix TTC par zone, mode et classe de colis.
 * Pas de tarif pour une combinaison = elle n'est pas proposée au client.
 *
 * @extends AbstractCrudController<TarifLivraison>
 */
final class TarifLivraisonCrudController extends AbstractCrudController
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return TarifLivraison::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tarif de livraison')
            ->setEntityLabelInPlural('Tarifs de livraison')
            ->setDefaultSort(['zone.nom' => 'ASC', 'mode.position' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('zone')
            ->add('mode')
            ->add(ChoiceFilter::new('classe', 'Classe')->setChoices($this->choixClasses()));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('zone', 'Zone');
        yield AssociationField::new('mode', 'Mode de livraison');
        yield ChoiceField::new('classe', 'Classe de colis')
            ->setChoices($this->choixClasses())
            ->setHelp('Seules les classes expédiables ont un tarif (« transport dédié » et « retrait uniquement » n\'en ont pas).');
        yield MoneyField::new('prixTtc', 'Prix TTC')
            ->setCurrency('EUR')
            ->setStoredAsCents()
            ->setFormTypeOption('input', 'integer')
            ->setHelp('Prix de l\'envoi entier, TVA incluse.');
        yield IntegerField::new('delaiJoursMin', 'Délai min. (jours)')->setRequired(false);
        yield IntegerField::new('delaiJoursMax', 'Délai max. (jours)')->setRequired(false);
    }

    /**
     * @return array<string, ClasseLivraison>
     */
    private function choixClasses(): array
    {
        $choix = [];
        foreach (ClasseLivraison::expediables() as $classe) {
            $choix[$classe->trans($this->translator)] = $classe;
        }

        return $choix;
    }
}
