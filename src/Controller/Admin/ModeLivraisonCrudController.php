<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ModeLivraison;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Modes de livraison proposés aux clients (expédition, retrait à l'atelier…).
 *
 * @extends AbstractCrudController<ModeLivraison>
 */
final class ModeLivraisonCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ModeLivraison::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Mode de livraison')
            ->setEntityLabelInPlural('Modes de livraison')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['code', 'nom', 'description']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('code', 'Code')
            ->setHelp('Identifiant stable utilisé par le site : minuscules, chiffres et « _ » (ex. domicile, retrait_atelier).');
        yield TextField::new('nom', 'Nom');
        yield TextField::new('description', 'Description')
            ->setRequired(false)
            ->setHelp('Affichée au client sous le nom (transporteur, signature, assurance…).');
        yield BooleanField::new('retrait', 'Retrait à l\'atelier')
            ->setHelp('Un mode « retrait » est gratuit, sans adresse de livraison ni grille tarifaire.');
        yield BooleanField::new('active', 'Actif');
        yield IntegerField::new('position', 'Position')->setHelp('Ordre d\'affichage (croissant).');
    }
}
