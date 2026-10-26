<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\StatutCommande;
use App\Repository\CategorieRepository;
use App\Repository\CommandeRepository;
use App\Repository\InstrumentRepository;
use App\Repository\StockRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

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
        #[Autowire(service: 'security.logout_url_generator')]
        private readonly LogoutUrlGenerator $logoutUrlGenerator,
        #[Autowire(env: 'MATOMO_URL')]
        private readonly string $matomoUrl,
        #[Autowire(env: 'MATOMO_SITE_ID')]
        private readonly string $matomoSiteId,
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

    /**
     * Suivi Matomo de toutes les pages admin, uniquement si MATOMO_URL est renseigné
     * (.env.prod : préprod et prod ; vide en dev et en test).
     */
    public function configureAssets(): Assets
    {
        $assets = parent::configureAssets();
        if ('' === $this->matomoUrl || '' === $this->matomoSiteId) {
            return $assets;
        }

        return $assets->addHtmlContentToHead(\sprintf(
            <<<'HTML'
                <script>
                  var _paq = window._paq = window._paq || [];
                  _paq.push(['trackPageView']);
                  _paq.push(['enableLinkTracking']);
                  (function() {
                    var u = %s;
                    _paq.push(['setTrackerUrl', u + 'matomo.php']);
                    _paq.push(['setSiteId', %s]);
                    var g = document.createElement('script'); g.async = true; g.src = u + 'matomo.js';
                    document.head.appendChild(g);
                  })();
                </script>
                HTML,
            json_encode($this->matomoUrl, \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG),
            json_encode($this->matomoSiteId, \JSON_HEX_TAG),
        ));
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
        yield MenuItem::linkTo(PaiementCrudController::class, 'Paiements', 'fa fa-credit-card');

        yield MenuItem::section('Livraison');
        yield MenuItem::linkTo(ZoneLivraisonCrudController::class, 'Zones', 'fa fa-earth-europe');
        yield MenuItem::linkTo(ModeLivraisonCrudController::class, 'Modes de livraison', 'fa fa-truck');
        yield MenuItem::linkTo(TarifLivraisonCrudController::class, 'Tarifs', 'fa fa-tags');

        yield MenuItem::section();
        yield MenuItem::linkToUrl('Déconnexion', 'fa fa-sign-out', $this->urlDeconnexion());
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        // Remplace le lien de déconnexion par défaut d'EasyAdmin (voir urlDeconnexion())
        return parent::configureUserMenu($user)
            ->setMenuItems([
                MenuItem::linkToUrl('Retour au site', 'fa fa-arrow-left', $this->urlSite),
                MenuItem::section(),
                MenuItem::linkToUrl('Déconnexion', 'fa fa-sign-out', $this->urlDeconnexion()),
            ]);
    }

    /**
     * Pare-feu « admin » imposé : avec la session partagée, un admin connecté depuis React
     * porte un jeton du pare-feu « main », et linkToLogout() pointerait vers /api/logout
     * (POST uniquement). La session étant commune, /admin/logout déconnecte des deux côtés.
     */
    private function urlDeconnexion(): string
    {
        return $this->logoutUrlGenerator->getLogoutPath('admin');
    }
}
