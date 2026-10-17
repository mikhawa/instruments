<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\StatutCommande;
use App\Repository\CategorieRepository;
use App\Repository\CommandeRepository;
use App\Repository\InstrumentRepository;
use App\Repository\StockRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Back-office : accès réservé aux administrateurs (voir aussi access_control).
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly InstrumentRepository $instrumentRepository,
        private readonly CategorieRepository $categorieRepository,
        private readonly StockRepository $stockRepository,
        private readonly CommandeRepository $commandeRepository,
        #[Autowire(env: 'FRONT_URL')]
        private readonly string $urlSite,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'nbInstruments' => $this->instrumentRepository->count(),
            'nbInstrumentsPublies' => $this->instrumentRepository->count(['published' => true]),
            'nbCategories' => $this->categorieRepository->count(),
            'nbStocksSousSeuil' => $this->stockRepository->compterSousSeuilAlerte(),
            'nbCommandesATraiter' => $this->commandeRepository->compterParStatuts([
                StatutCommande::Payee,
                StatutCommande::EnPreparation,
            ]),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Instruments — Administration')
            ->setLocales(['fr']);
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDateTimeFormat('dd/MM/yyyy HH:mm')
            ->setTimezone('Europe/Brussels')
            ->setPaginatorPageSize(25);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        // Session partagée avec le site (context: session_utilisateur) : on y reste connecté
        yield MenuItem::linkToUrl('Retour au site', 'fa fa-arrow-left', $this->urlSite);

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkTo(InstrumentCrudController::class, 'Instruments', 'fa fa-guitar');
        yield MenuItem::linkTo(CategorieCrudController::class, 'Catégories', 'fa fa-folder-tree');

        yield MenuItem::section('Ventes');
        yield MenuItem::linkTo(CommandeCrudController::class, 'Commandes', 'fa fa-receipt');

        yield MenuItem::section();
        yield MenuItem::linkToLogout('Déconnexion', 'fa fa-sign-out');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->addMenuItems([
                MenuItem::linkToUrl('Retour au site', 'fa fa-arrow-left', $this->urlSite),
            ]);
    }
}
