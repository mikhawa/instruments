<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Facture;
use App\Enum\TypeFacture;
use App\Service\Facturation\GenerateurPdfFacture;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Factures et notes de crédit en lecture seule : une facture émise ne se modifie ni ne se supprime.
 * Les notes de crédit naissent du formulaire « Rembourser » de la commande.
 *
 * @extends AbstractCrudController<Facture>
 */
final class FactureCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly GenerateurPdfFacture $generateur,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Facture::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Facture')
            ->setEntityLabelInPlural('Factures')
            ->setDefaultSort(['dateEmission' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields(['numero', 'commande.numero', 'commande.email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $pdf = Action::new('pdf', 'PDF', 'fa fa-file-pdf')
            ->linkToUrl(fn (Facture $facture): string => $this->adminUrlGenerator
                ->unsetAll()
                ->setController(self::class)
                ->setRoute('admin_facture_pdf', ['entityId' => $facture->getId()])
                ->generateUrl())
            ->setHtmlAttributes(['target' => '_blank']);

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $pdf)
            ->add(Crud::PAGE_DETAIL, $pdf)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    #[AdminRoute(path: '/{entityId}/pdf', name: 'pdf', options: ['methods' => ['GET']])]
    public function pdf(Request $request): Response
    {
        $facture = $this->entityManager->find(Facture::class, (int) $request->attributes->get('entityId', $request->query->get('entityId')));
        if (!$facture instanceof Facture) {
            throw $this->createNotFoundException();
        }

        return new Response($this->generateur->pdf($facture), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $this->generateur->nomFichier($facture)),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('type')
            ->add('dateEmission');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('numero', 'Numéro');
        yield ChoiceField::new('type', 'Type')
            ->setChoices(TypeFacture::cases())
            ->renderAsBadges(['Facture' => 'success', 'NoteCredit' => 'warning']);
        yield AssociationField::new('commande', 'Commande');
        yield AssociationField::new('factureOrigine', 'Facture corrigée')->onlyOnDetail();
        yield DateTimeField::new('dateEmission', 'Émise le');
        yield MoneyField::new('totalHt', 'Total HTVA')->setCurrency('EUR')->setStoredAsCents()->onlyOnDetail();
        yield MoneyField::new('totalTva', 'TVA')->setCurrency('EUR')->setStoredAsCents()->onlyOnDetail();
        yield MoneyField::new('totalTtc', 'Total TVAC')->setCurrency('EUR')->setStoredAsCents();
        yield TextField::new('motif', 'Motif')->onlyOnDetail();
    }
}
