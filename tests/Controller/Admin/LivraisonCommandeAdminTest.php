<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Commande;
use App\Entity\ModeLivraison;
use App\Entity\TarifLivraison;
use App\Entity\Utilisateur;
use App\Entity\ZoneLivraison;
use App\Enum\ClasseLivraison;
use App\Enum\StatutCommande;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Back-office : CRUD de la livraison et actions de workflow du détail d'une commande.
 */
final class LivraisonCommandeAdminTest extends WebTestCase
{
    private const EMAIL_ADMIN = 'admin.livraison@instruments.test';
    private const NUMERO = 'TEST-ADM-0001';
    private const CODE_ZONE = 'ZTST';
    private const CODE_MODE = 'mode_test';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private int $idCommande;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->nettoyer();

        $admin = (new Utilisateur())
            ->setEmail(self::EMAIL_ADMIN)
            ->setPrenom('Admin')
            ->setNom('Livraison')
            ->setLocale('fr')
            ->setRoles(['ROLE_ADMIN'])
            ->setVerified(true);
        $admin->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($admin, 'motdepasse-test'));
        $this->entityManager->persist($admin);

        $zone = (new ZoneLivraison())->setCode(self::CODE_ZONE)->setNom('Zone de test')->setPays(['BE', 'FR']);
        $mode = (new ModeLivraison())->setCode(self::CODE_MODE)->setNom('Mode de test');
        $this->entityManager->persist($zone);
        $this->entityManager->persist($mode);
        $this->entityManager->persist((new TarifLivraison())
            ->setZone($zone)->setMode($mode)->setClasse(ClasseLivraison::Colis)->setPrixTtc(1290)
            ->setDelaiJoursMin(2)->setDelaiJoursMax(4));

        // Commande invitée, sans client ni lignes
        $commande = (new Commande())->setNumero(self::NUMERO)->setEmail('invite@instruments.test')->setStatut(StatutCommande::Payee);
        $commande->setPaidAt(new \DateTimeImmutable());
        $propriete = new \ReflectionProperty(Commande::class, 'adresseLivraison');
        $propriete->setValue($commande, ['rue' => '1 rue Test', 'ville' => 'Mons', 'pays' => 'BE']);
        $propriete = new \ReflectionProperty(Commande::class, 'adresseFacturation');
        $propriete->setValue($commande, ['rue' => '1 rue Test', 'ville' => 'Mons', 'pays' => 'BE']);
        $this->entityManager->persist($commande);
        $this->entityManager->flush();
        $this->idCommande = $commande->getId();

        $this->client->request('GET', '/admin/login');
        $this->client->submitForm('Se connecter', ['email' => self::EMAIL_ADMIN, 'password' => 'motdepasse-test']);
    }

    protected function tearDown(): void
    {
        $this->nettoyer();
        parent::tearDown();
    }

    private function nettoyer(): void
    {
        $this->entityManager->clear();
        $this->entityManager->createQuery('DELETE FROM '.Commande::class.' c WHERE c.numero = :n')->setParameter('n', self::NUMERO)->execute();
        $this->entityManager->createQuery('DELETE FROM '.ZoneLivraison::class.' z WHERE z.code = :c')->setParameter('c', self::CODE_ZONE)->execute();
        $this->entityManager->createQuery('DELETE FROM '.ModeLivraison::class.' m WHERE m.code = :c')->setParameter('c', self::CODE_MODE)->execute();
        $this->entityManager->createQuery('DELETE FROM '.Utilisateur::class.' u WHERE u.email = :e')->setParameter('e', self::EMAIL_ADMIN)->execute();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fournirCrudLivraison(): iterable
    {
        yield 'zones' => ['zone-livraison'];
        yield 'modes' => ['mode-livraison'];
        yield 'tarifs' => ['tarif-livraison'];
    }

    #[DataProvider('fournirCrudLivraison')]
    public function testPagesIndexEtNouveau(string $chemin): void
    {
        $this->client->request('GET', '/admin/'.$chemin);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/'.$chemin.'/new');
        self::assertResponseIsSuccessful();
    }

    public function testIndexZonesAfficheLesPays(): void
    {
        $this->client->request('GET', '/admin/zone-livraison');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Zone de test');
        self::assertSelectorTextContains('body', 'Belgique');
    }

    public function testIndexTarifsAvecFiltres(): void
    {
        $this->client->request('GET', '/admin/tarif-livraison');
        self::assertSelectorTextContains('body', 'Zone de test');
        self::assertSelectorTextContains('body', 'Colis');
        $this->client->request('GET', '/admin/tarif-livraison?filters[classe][comparison]==&filters[classe][value]=colis');
        self::assertResponseIsSuccessful();
    }

    public function testEditionInstrumentEtIndexCommandes(): void
    {
        $this->client->request('GET', '/admin/commande?query=invite@instruments');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', self::NUMERO);
        self::assertSelectorTextContains('body', 'Invité');
        $this->client->request('GET', '/admin/instrument/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name$="[classeLivraison]"]');
    }

    public function testDetailEtTransitions(): void
    {
        $crawler = $this->client->request('GET', '/admin/commande/'.$this->idCommande);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Invité');
        // Payée : « préparer » et « annuler » seulement
        $texte = $crawler->filter('body')->text();
        self::assertStringContainsString('Préparer la commande', $texte);
        self::assertStringContainsString('Annuler la commande', $texte);
        self::assertStringNotContainsString('Marquer comme expédiée', $texte);

        // Préparer
        $this->soumettreAction($crawler, 'preparer');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'effectué');
        $this->entityManager->clear();
        self::assertSame(StatutCommande::EnPreparation, $this->entityManager->find(Commande::class, $this->idCommande)->getStatut());

        // Expédier sans numéro de suivi : refusé
        $this->soumettreAction($crawler, 'expedier');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'numéro de suivi');
        $this->entityManager->clear();
        self::assertSame(StatutCommande::EnPreparation, $this->entityManager->find(Commande::class, $this->idCommande)->getStatut());

        // Jeton invalide : refusé
        $this->client->request('POST', '/admin/commande/'.$this->idCommande.'/transition?transition=annuler&_token=faux');
        self::assertResponseStatusCodeSame(403);
    }

    private function soumettreAction(Crawler $crawler, string $transition): void
    {
        $formulaire = $crawler->filter(sprintf('button[data-action-name="transition_%s"]', $transition))->closest('form');
        self::assertNotNull($formulaire, $transition);
        $this->client->request('POST', $formulaire->attr('action'));
    }
}
