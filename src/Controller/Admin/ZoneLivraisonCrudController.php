<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ZoneLivraison;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Zones tarifaires de livraison (ensembles de pays). Un pays n'appartient qu'à une seule zone.
 *
 * @extends AbstractCrudController<ZoneLivraison>
 */
final class ZoneLivraisonCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ZoneLivraison::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Zone de livraison')
            ->setEntityLabelInPlural('Zones de livraison')
            ->setDefaultSort(['code' => 'ASC'])
            ->setSearchFields(['code', 'nom']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Supprimer une zone supprime aussi ses tarifs (cascade en base) : action conservée, avec confirmation
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('code', 'Code')->setHelp('Ex. BE, UE1, UE2 (converti en majuscules).');
        yield TextField::new('nom', 'Nom');
        yield CountryField::new('pays', 'Pays')
            ->allowMultipleChoices()
            ->setHelp('Un pays ne peut appartenir qu\'à une seule zone.');
    }
}
