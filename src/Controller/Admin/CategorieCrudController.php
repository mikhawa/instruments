<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Categorie;
use App\Form\Admin\CategorieTraductionType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Gestion des catégories du catalogue et de leurs traductions.
 *
 * @extends AbstractCrudController<Categorie>
 */
final class CategorieCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Categorie::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie')
            ->setEntityLabelInPlural('Catégories')
            ->setDefaultSort(['position' => 'ASC'])
            ->setSearchFields(['traductions.nom', 'traductions.slug']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Suppression proposée uniquement pour une catégorie vide (clés étrangères en RESTRICT)
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action) => $action->displayIf(self::estSupprimable(...)))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn (Action $action) => $action->displayIf(self::estSupprimable(...)))
            ->disable(Action::BATCH_DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('parent')
            ->add('active');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('nom', 'Nom')->hideOnForm()->setSortable(false);
        yield AssociationField::new('parent', 'Catégorie parente')
            ->setRequired(false)
            ->setQueryBuilder(static fn ($qb) => $qb->orderBy('entity.position', 'ASC'));
        yield IntegerField::new('position', 'Position');
        yield BooleanField::new('active', 'Active');
        yield AssociationField::new('instruments', 'Instruments')->onlyOnIndex();
        yield CollectionField::new('traductions', 'Traductions')
            ->onlyOnForms()
            ->setEntryType(CategorieTraductionType::class)
            ->setEntryIsComplex()
            ->allowAdd()
            ->allowDelete()
            ->renderExpanded()
            ->setFormTypeOption('by_reference', false)
            ->setHelp('Au moins une traduction est requise (le français sert de référence).');
    }

    /**
     * Garde-fou côté serveur : l'action masquée pourrait tout de même être appelée directement.
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!self::estSupprimable($entityInstance)) {
            $this->addFlash('danger', 'Impossible de supprimer une catégorie qui contient des instruments ou des sous-catégories.');

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    private static function estSupprimable(Categorie $categorie): bool
    {
        return $categorie->getInstruments()->isEmpty() && $categorie->getEnfants()->isEmpty();
    }
}
