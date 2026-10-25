<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Categorie;
use App\Entity\CategorieTraduction;
use App\Entity\Instrument;
use App\Entity\InstrumentTraduction;
use App\Entity\LignePanier;
use App\Entity\Panier;
use App\Entity\Utilisateur;
use App\Enum\EtatInstrument;
use App\Scheduler\Handler\PurgerPaniersVisiteursHandler;
use App\Scheduler\Message\PurgerPaniersVisiteurs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * API du panier : cookie visiteur, plafonds de quantité, isolement entre paniers,
 * prix jamais acceptés du client, fusion à la connexion et purge planifiée.
 */
final class PanierTest extends WebTestCase
{
    private const PREFIXE = 'TST-PAN-';
    private const EMAIL = 'client.panier@instruments.test';
    private const MOT_DE_PASSE = 'motdepasse-test';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    /** @var array<string, Instrument> */
    private array $instruments = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $categorie = (new Categorie())->addTraduction((new CategorieTraduction())->setLocale('fr')->setNom('Test panier')->setSlug('test-panier'));
        $this->entityManager->persist($categorie);

        foreach ([
            // clé => [prix HT, stock, pièce unique, publié]
            'oud' => [100000, 3, false, true],
            'vielle' => [400000, 1, true, true],
            'brouillon' => [5000, 2, false, false],
            'flute' => [2000, 20, false, true],
        ] as $cle => [$prixHt, $stock, $pieceUnique, $publie]) {
            $instrument = (new Instrument())
                ->setCategorie($categorie)
                ->setReference(self::PREFIXE.strtoupper($cle))
                ->setEtat(EtatInstrument::Neuf)
                ->setPieceUnique($pieceUnique)
                ->setPrixHt($prixHt)
                ->setPublished($publie)
                ->addTraduction((new InstrumentTraduction())->setLocale('fr')->setNom('Test '.$cle)->setSlug('test-panier-'.$cle));
            $instrument->getStock()->ajouter($stock);
            $this->entityManager->persist($instrument);
            $this->instruments[$cle] = $instrument;
        }

        $client = (new Utilisateur())
            ->setEmail(self::EMAIL)
            ->setPrenom('Client')
            ->setNom('Test')
            ->setLocale('fr')
            ->setVerified(true);
        $client->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($client, self::MOT_DE_PASSE));
        $this->entityManager->persist($client);

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $connexion = $this->entityManager->getConnection();
        // Lignes, stocks et traductions suivent par ON DELETE CASCADE
        $connexion->executeStatement('DELETE p FROM panier p JOIN ligne_panier l ON l.panier_id = p.id JOIN instrument i ON i.id = l.instrument_id WHERE i.reference LIKE ?', [self::PREFIXE.'%']);
        $connexion->executeStatement('DELETE FROM panier WHERE utilisateur_id IN (SELECT id FROM utilisateur WHERE email = ?)', [self::EMAIL]);
        $connexion->executeStatement('DELETE FROM instrument WHERE reference LIKE ?', [self::PREFIXE.'%']);
        $connexion->executeStatement('DELETE c FROM categorie c JOIN categorie_traduction t ON t.categorie_id = c.id WHERE t.slug = ?', ['test-panier']);
        $connexion->executeStatement('DELETE FROM utilisateur WHERE email = ?', [self::EMAIL]);

        parent::tearDown();
    }

    public function testPanierVideSansCookieNiEcriture(): void
    {
        $panier = $this->requete('GET', '/api/panier');

        self::assertSame([], $panier['lignes']);
        self::assertFalse($panier['commandable']);
        self::assertNull($this->client->getCookieJar()->get('panier'));
    }

    public function testAjoutParUnVisiteurPoseLeCookieEtRecalculeLesPrix(): void
    {
        // Un prix envoyé par le navigateur est ignoré
        $panier = $this->ajouter('oud', 2, ['prixUnitaireTtc' => 1]);

        self::assertResponseStatusCodeSame(200);
        $cookie = $this->client->getCookieJar()->get('panier');
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('lax', strtolower((string) $cookie->getSameSite()));

        self::assertCount(1, $panier['lignes']);
        self::assertSame(121000, $panier['lignes'][0]['prixUnitaireTtc']);
        self::assertSame(242000, $panier['totalTtc']);
        self::assertSame(2, $panier['nombreArticles']);

        // Le même cookie retrouve le panier ; un nouvel ajout cumule, plafonné au stock (3)
        $panier = $this->ajouter('oud', 5);
        self::assertSame(3, $panier['lignes'][0]['quantite']);
        self::assertSame(3, $this->requete('GET', '/api/panier')['nombreArticles']);
    }

    public function testPieceUniquePlafonneeAUn(): void
    {
        $panier = $this->ajouter('vielle', 3);

        self::assertSame(1, $panier['lignes'][0]['quantite']);
        self::assertSame(1, $panier['lignes'][0]['quantiteMax']);
    }

    public function testInstrumentNonPublieRefuse(): void
    {
        $this->ajouter('brouillon');
        self::assertResponseStatusCodeSame(400);

        $this->ajouter('oud', 0);
        self::assertResponseStatusCodeSame(422);
    }

    public function testInstrumentEpuiseRefuse(): void
    {
        $this->instruments['vielle']->getStock()->reserver(1);
        $this->entityManager->flush();

        $this->ajouter('vielle');
        self::assertResponseStatusCodeSame(422);
    }

    public function testModificationEtSuppressionDeLigne(): void
    {
        $panier = $this->ajouter('oud');
        $this->ajouter('flute');
        $id = $panier['lignes'][0]['id'];

        $panier = $this->requete('PATCH', '/api/panier/lignes/'.$id, ['quantite' => 9]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(3, $panier['lignes'][0]['quantite']);

        $panier = $this->requete('DELETE', '/api/panier/lignes/'.$id);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $panier['lignes']);

        $this->client->request('DELETE', '/api/panier');
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->requete('GET', '/api/panier')['lignes']);
    }

    public function testLigneDUnAutrePanierIntrouvable(): void
    {
        $id = $this->ajouter('oud')['lignes'][0]['id'];

        // Autre visiteur : autre cookie
        $this->client->getCookieJar()->clear();
        $this->ajouter('flute');

        $this->requete('PATCH', '/api/panier/lignes/'.$id, ['quantite' => 2]);
        self::assertResponseStatusCodeSame(404);
        $this->requete('DELETE', '/api/panier/lignes/'.$id);
        self::assertResponseStatusCodeSame(404);

        // Sans cookie du tout
        $this->client->getCookieJar()->clear();
        $this->requete('DELETE', '/api/panier/lignes/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testConnexionClientSansPanierRecupereLePanierVisiteur(): void
    {
        $this->ajouter('oud', 2);

        $this->connecter();

        self::assertNull($this->client->getCookieJar()->get('panier'), 'Le cookie visiteur est effacé après la fusion.');
        $panier = $this->requete('GET', '/api/panier');
        self::assertCount(1, $panier['lignes']);
        self::assertSame(2, $panier['lignes'][0]['quantite']);
    }

    public function testFusionGardeLaQuantiteLaPlusGrande(): void
    {
        // Panier existant du client : 1 oud, 1 vielle
        $panierClient = (new Panier())->setUtilisateur($this->client());
        new LignePanier($panierClient, $this->instruments['oud'], 1);
        new LignePanier($panierClient, $this->instruments['vielle'], 1);
        $this->entityManager->persist($panierClient);
        $this->entityManager->flush();

        // Visiteur : 2 ouds, 1 vielle (pièce unique), 4 flûtes
        $this->ajouter('oud', 2);
        $this->ajouter('vielle');
        $this->ajouter('flute', 4);

        $this->connecter();

        $quantites = [];
        foreach ($this->requete('GET', '/api/panier')['lignes'] as $ligne) {
            $quantites[$ligne['instrument']['reference']] = $ligne['quantite'];
        }
        self::assertSame([
            self::PREFIXE.'OUD' => 2,
            self::PREFIXE.'VIELLE' => 1,
            self::PREFIXE.'FLUTE' => 4,
        ], $quantites);
        self::assertSame(1, $this->entityManager->getRepository(Panier::class)->count(['utilisateur' => $this->client()]));
    }

    public function testPurgeDesPaniersVisiteursInactifs(): void
    {
        $this->ajouter('oud');
        $panierClient = (new Panier())->setUtilisateur($this->client());
        new LignePanier($panierClient, $this->instruments['flute'], 1);
        $this->entityManager->persist($panierClient);
        $this->entityManager->flush();

        $connexion = $this->entityManager->getConnection();
        $connexion->executeStatement('UPDATE panier SET updated_at = ? WHERE id IN (SELECT panier_id FROM ligne_panier l JOIN instrument i ON i.id = l.instrument_id WHERE i.reference LIKE ?)', [
            (new \DateTimeImmutable('-31 days'))->format('Y-m-d H:i:s'),
            self::PREFIXE.'%',
        ]);

        $handler = static::getContainer()->get(PurgerPaniersVisiteursHandler::class);
        self::assertGreaterThanOrEqual(1, $handler(new PurgerPaniersVisiteurs()));

        // Panier visiteur supprimé, panier client conservé malgré l'inactivité
        $this->entityManager->clear();
        self::assertSame([], $this->requete('GET', '/api/panier')['lignes']);
        self::assertSame(1, $this->entityManager->getRepository(Panier::class)->count(['utilisateur' => $this->client()]));
    }

    /**
     * @param array<string, mixed> $enPlus champs supplémentaires du corps
     *
     * @return array<string, mixed>
     */
    private function ajouter(string $cle, int $quantite = 1, array $enPlus = []): array
    {
        return $this->requete('POST', '/api/panier/lignes', [
            'instrument' => '/api/instruments/'.$this->instruments[$cle]->getId(),
            'quantite' => $quantite,
        ] + $enPlus);
    }

    /**
     * @param array<string, mixed>|null $corps
     *
     * @return array<string, mixed>
     */
    private function requete(string $methode, string $url, ?array $corps = null): array
    {
        $this->client->request($methode, $url, server: array_filter([
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => null === $corps ? null : ('PATCH' === $methode ? 'application/merge-patch+json' : 'application/json'),
        ]), content: null === $corps ? null : json_encode($corps, \JSON_THROW_ON_ERROR));

        return json_decode((string) $this->client->getResponse()->getContent() ?: '[]', true, flags: \JSON_THROW_ON_ERROR);
    }

    private function connecter(): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => self::EMAIL, 'password' => self::MOT_DE_PASSE]);
        self::assertResponseIsSuccessful();
    }

    private function client(): Utilisateur
    {
        return $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => self::EMAIL]);
    }
}
