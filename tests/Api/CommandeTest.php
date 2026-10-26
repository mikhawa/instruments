<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Categorie;
use App\Entity\CategorieTraduction;
use App\Entity\Commande;
use App\Entity\Instrument;
use App\Entity\InstrumentTraduction;
use App\Entity\MouvementStock;
use App\Entity\Utilisateur;
use App\Enum\ClasseLivraison;
use App\Enum\EtatInstrument;
use App\Enum\StatutCommande;
use App\Enum\TypeMouvementStock;
use App\Scheduler\Handler\ExpirerCommandesHandler;
use App\Scheduler\Message\ExpirerCommandes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Tunnel de commande : options de livraison, création (invité ou client), réservation du stock,
 * conflits (409), idempotence, remplacement, suivi par jeton, expiration et expédition.
 *
 * S'appuie sur la grille de livraison de départ insérée par la migration
 * (mode « domicile » : colis 22,90 € vers la France ; mode « retrait_atelier » gratuit).
 */
final class CommandeTest extends WebTestCase
{
    private const PREFIXE = 'TST-CMD-';
    private const EMAIL_CLIENT = 'client.commande@instruments.test';
    private const EMAIL_AUTRE = 'autre.commande@instruments.test';
    private const EMAIL_INVITE = 'invite.commande@instruments.test';
    private const MOT_DE_PASSE = 'motdepasse-test';
    private const PORT_COLIS_FR = 2290;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    /** @var array<string, Instrument> */
    private array $instruments = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $categorie = (new Categorie())->addTraduction((new CategorieTraduction())->setLocale('fr')->setNom('Test commande')->setSlug('test-commande'));
        $this->entityManager->persist($categorie);

        foreach ([
            // clé => [prix HT, stock, pièce unique, classe]
            'oud' => [100000, 3, false, ClasseLivraison::Colis],
            'vielle' => [400000, 1, true, ClasseLivraison::Colis],
            'harpe' => [800000, 1, true, ClasseLivraison::RetraitUniquement],
        ] as $cle => [$prixHt, $stock, $pieceUnique, $classe]) {
            $instrument = (new Instrument())
                ->setCategorie($categorie)
                ->setReference(self::PREFIXE.strtoupper($cle))
                ->setEtat(EtatInstrument::Neuf)
                ->setPieceUnique($pieceUnique)
                ->setPrixHt($prixHt)
                ->setClasseLivraison($classe)
                ->setPublished(true)
                ->addTraduction((new InstrumentTraduction())->setLocale('fr')->setNom('Test '.$cle)->setSlug('test-commande-'.$cle));
            $instrument->getStock()->ajouter($stock);
            $this->entityManager->persist($instrument);
            $this->instruments[$cle] = $instrument;
        }

        $hasher = $container->get(UserPasswordHasherInterface::class);
        foreach ([self::EMAIL_CLIENT, self::EMAIL_AUTRE] as $email) {
            $utilisateur = (new Utilisateur())->setEmail($email)->setPrenom('Client')->setNom('Test')->setLocale('fr')->setVerified(true);
            $utilisateur->setPassword($hasher->hashPassword($utilisateur, self::MOT_DE_PASSE));
            $this->entityManager->persist($utilisateur);
        }

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $connexion = $this->entityManager->getConnection();
        $instruments = 'SELECT id FROM instrument WHERE reference LIKE ?';
        // Factures (ON DELETE RESTRICT) : notes de crédit d'abord, puis factures
        $commandes = "SELECT DISTINCT l.commande_id FROM ligne_commande l WHERE l.instrument_id IN ($instruments)";
        $connexion->executeStatement("DELETE FROM facture WHERE facture_origine_id IS NOT NULL AND commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE FROM facture WHERE commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE FROM mouvement_stock WHERE instrument_id IN ($instruments)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE c FROM commande c JOIN ligne_commande l ON l.commande_id = c.id WHERE l.instrument_id IN ($instruments)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE p FROM panier p JOIN ligne_panier l ON l.panier_id = p.id WHERE l.instrument_id IN ($instruments)", [self::PREFIXE.'%']);
        $connexion->executeStatement('DELETE FROM panier WHERE utilisateur_id IN (SELECT id FROM utilisateur WHERE email IN (?, ?))', [self::EMAIL_CLIENT, self::EMAIL_AUTRE]);
        $connexion->executeStatement('DELETE FROM instrument WHERE reference LIKE ?', [self::PREFIXE.'%']);
        $connexion->executeStatement('DELETE c FROM categorie c JOIN categorie_traduction t ON t.categorie_id = c.id WHERE t.slug = ?', ['test-commande']);
        $connexion->executeStatement('DELETE FROM utilisateur WHERE email IN (?, ?)', [self::EMAIL_CLIENT, self::EMAIL_AUTRE]);

        parent::tearDown();
    }

    public function testOptionsDeLivraison(): void
    {
        $this->ajouter('oud');

        $options = $this->requete('GET', '/api/panier/livraison?pays=FR');
        self::assertContains('BE', $options['paysLivrables']);
        self::assertSame([['domicile', self::PORT_COLIS_FR], ['retrait_atelier', 0]], array_map(static fn (array $o): array => [$o['code'], $o['prixTtc']], $options['options']));

        $options = $this->requete('GET', '/api/panier/livraison?pays=US');
        self::assertSame(['retrait_atelier'], array_column($options['options'], 'code'));
        self::assertSame(['pays_non_livrable'], $options['messages']);

        // Un instrument « retrait uniquement » impose le retrait pour tout le panier
        $this->ajouter('harpe');
        $options = $this->requete('GET', '/api/panier/livraison?pays=FR');
        self::assertSame(['retrait_atelier'], array_column($options['options'], 'code'));
        self::assertSame(['retrait_uniquement'], $options['messages']);
    }

    public function testCommandeInviteReserveLeStock(): void
    {
        $this->ajouter('oud', 2);

        $commande = $this->commander(242000 + self::PORT_COLIS_FR);

        self::assertResponseStatusCodeSame(201);
        self::assertMatchesRegularExpression('/^CMD-\d{4}-\d{6}$/', $commande['numero']);
        self::assertSame('en_attente_paiement', $commande['statut']);
        self::assertTrue($commande['invite']);
        self::assertSame(self::EMAIL_INVITE, $commande['email']);
        self::assertSame(244290, $commande['totalTtc']);
        self::assertSame(200000, $commande['totalHt']);
        self::assertSame(42000, $commande['totalTva']);
        self::assertNotNull($commande['jetonSuivi']);
        self::assertNotNull($commande['expireAt']);

        $stock = $this->instrumentFrais('oud')->getStock();
        self::assertSame(3, $stock->getQuantite());
        self::assertSame(2, $stock->getQuantiteReservee());
        self::assertSame(1, $this->compterMouvements('oud', TypeMouvementStock::Reservation));

        $entite = $this->commande($commande['numero']);
        self::assertSame('2026-10-provisoire', $entite->getCgvVersion());
        self::assertNull($entite->getUtilisateur());

        // Le panier n'est pas vidé avant le paiement
        self::assertCount(1, $this->requete('GET', '/api/panier')['lignes']);
    }

    public function testTotalDifferentRefuse(): void
    {
        $this->ajouter('oud');

        $this->commander(1);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(0, $this->instrumentFrais('oud')->getStock()->getQuantiteReservee());
    }

    public function testPieceUniqueDejaReserveeRefusee(): void
    {
        $this->ajouter('vielle');

        // Un autre visiteur commande la même pièce unique entre-temps
        $panierA = $this->client->getCookieJar()->get('panier');
        $this->client->getCookieJar()->clear();
        $this->ajouter('vielle');
        $this->commander(484000 + self::PORT_COLIS_FR);
        self::assertResponseStatusCodeSame(201);

        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set($panierA);
        $this->commander(484000 + self::PORT_COLIS_FR);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(1, $this->instrumentFrais('vielle')->getStock()->getQuantiteReservee());
    }

    public function testDemandesInvalides(): void
    {
        $this->commander(0);
        self::assertResponseStatusCodeSame(409, 'Panier vide.');

        $this->ajouter('oud');
        $total = 121000 + self::PORT_COLIS_FR;

        $this->commander($total, ['email' => null]);
        self::assertResponseStatusCodeSame(422);

        $this->commander($total, ['accepteCgv' => false]);
        self::assertResponseStatusCodeSame(422);

        $this->commander($total, ['adresseLivraison' => ['pays' => 'US'] + $this->adresse()]);
        self::assertResponseStatusCodeSame(409, 'Pays non livrable.');

        $this->commander($total, ['adresseLivraison' => null, 'adresseFacturation' => $this->adresse()]);
        self::assertResponseStatusCodeSame(409, 'Livraison à domicile sans adresse de livraison : aucun pays, donc pas de tarif.');
    }

    public function testRetraitSansAdresseDeLivraison(): void
    {
        $this->ajouter('harpe');

        $commande = $this->commander(968000, ['modeLivraison' => 'retrait_atelier', 'adresseLivraison' => null, 'adresseFacturation' => $this->adresse()]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(0, $commande['fraisPortTtc']);
        self::assertSame($commande['adresseFacturation'], $commande['adresseLivraison']);
    }

    public function testIdempotenceEtRemplacement(): void
    {
        $this->ajouter('vielle');
        $total = 484000 + self::PORT_COLIS_FR;

        $premiere = $this->commander($total, cle: 'cle-test-0001');
        $rejouee = $this->commander($total, cle: 'cle-test-0001');
        self::assertSame($premiere['numero'], $rejouee['numero']);

        // Nouvelle validation du même panier : la commande précédente est annulée, la pièce réservée une seule fois
        $nouvelle = $this->commander($total, cle: 'cle-test-0002');
        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($premiere['numero'], $nouvelle['numero']);
        self::assertSame(StatutCommande::Annulee, $this->commande($premiere['numero'])->getStatut());
        self::assertSame(1, $this->instrumentFrais('vielle')->getStock()->getQuantiteReservee());

        $this->commander($total, cle: 'mauvaise cle');
        self::assertResponseStatusCodeSame(400);
    }

    public function testClientConnecteEtAccesAuxCommandes(): void
    {
        $this->connecter(self::EMAIL_CLIENT);
        $this->ajouter('oud');
        $commande = $this->commander(121000 + self::PORT_COLIS_FR, ['email' => 'ignore@example.test']);

        self::assertFalse($commande['invite']);
        self::assertSame(self::EMAIL_CLIENT, $commande['email']);
        $numeros = array_column($this->requete('GET', '/api/commandes'), 'numero');
        self::assertSame([$commande['numero']], $numeros);
        $this->requete('GET', '/api/commandes/'.$commande['numero']);
        self::assertResponseIsSuccessful();

        // Un autre client ne voit ni la commande ni sa présence
        $this->client->request('POST', '/api/logout');
        $this->connecter(self::EMAIL_AUTRE);
        self::assertSame([], $this->requete('GET', '/api/commandes'));
        $this->requete('GET', '/api/commandes/'.$commande['numero']);
        self::assertResponseStatusCodeSame(404);

        // … sauf avec le jeton du lien de suivi
        $this->requete('GET', '/api/commandes/'.$commande['numero'].'?jeton='.$commande['jetonSuivi']);
        self::assertResponseIsSuccessful();
    }

    public function testSuiviInviteParJeton(): void
    {
        $this->ajouter('oud');
        $commande = $this->commander(121000 + self::PORT_COLIS_FR);
        $this->client->getCookieJar()->clear();

        $vue = $this->requete('GET', '/api/commandes/'.$commande['numero'].'?jeton='.$commande['jetonSuivi']);
        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('jetonSuivi', $vue);

        $this->requete('GET', '/api/commandes/'.$commande['numero']);
        self::assertResponseStatusCodeSame(404);
        $this->requete('GET', '/api/commandes/'.$commande['numero'].'?jeton=00000000-0000-4000-8000-000000000000');
        self::assertResponseStatusCodeSame(404);
        $this->requete('GET', '/api/commandes');
        self::assertResponseStatusCodeSame(401);
    }

    public function testExpirationLibereLeStock(): void
    {
        $this->ajouter('vielle');
        $numero = $this->commander(484000 + self::PORT_COLIS_FR)['numero'];
        $this->entityManager->getConnection()->executeStatement('UPDATE commande SET expire_at = ? WHERE numero = ?', ['2020-01-01 00:00:00', $numero]);

        $handler = static::getContainer()->get(ExpirerCommandesHandler::class);
        self::assertGreaterThanOrEqual(1, $handler(new ExpirerCommandes()));

        $this->entityManager->clear();
        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::Annulee, $commande->getStatut());
        self::assertNotNull($commande->getCancelledAt());
        self::assertNull($commande->getExpireAt());
        self::assertSame(0, $this->instrumentFrais('vielle')->getStock()->getQuantiteReservee());
        self::assertSame(1, $this->compterMouvements('vielle', TypeMouvementStock::Liberation));
    }

    public function testExpeditionSortLeStock(): void
    {
        $this->ajouter('oud', 2);
        $numero = $this->commander(242000 + self::PORT_COLIS_FR)['numero'];

        // Le client de test redémarre le noyau à chaque requête : services du conteneur courant
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var WorkflowInterface $workflow */
        $workflow = static::getContainer()->get('state_machine.commande');
        $commande = $this->commande($numero);
        foreach (['payer', 'preparer', 'expedier'] as $transition) {
            $workflow->apply($commande, $transition);
        }
        $this->entityManager->flush();

        $this->entityManager->clear();
        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::Expediee, $commande->getStatut());
        self::assertNotNull($commande->getPaidAt());
        self::assertNotNull($commande->getShippedAt());
        $stock = $this->instrumentFrais('oud')->getStock();
        self::assertSame(1, $stock->getQuantite());
        self::assertSame(0, $stock->getQuantiteReservee());
        self::assertSame(1, $this->compterMouvements('oud', TypeMouvementStock::Sortie));
    }

    /**
     * @return array<string, mixed>
     */
    private function ajouter(string $cle, int $quantite = 1): array
    {
        return $this->requete('POST', '/api/panier/lignes', [
            'instrument' => '/api/instruments/'.$this->instruments[$cle]->getId(),
            'quantite' => $quantite,
        ]);
    }

    /**
     * @param array<string, mixed> $remplacements
     *
     * @return array<string, mixed>
     */
    private function commander(int $totalTtcAttendu, array $remplacements = [], ?string $cle = null): array
    {
        $corps = array_merge([
            'email' => self::EMAIL_INVITE,
            'adresseLivraison' => $this->adresse(),
            'modeLivraison' => 'domicile',
            'accepteCgv' => true,
            'totalTtcAttendu' => $totalTtcAttendu,
        ], $remplacements);

        return $this->requete('POST', '/api/commandes', $corps, null !== $cle ? ['HTTP_IDEMPOTENCY_KEY' => $cle] : []);
    }

    /**
     * @return array<string, string>
     */
    private function adresse(): array
    {
        return ['prenom' => 'Ana', 'nom' => 'Test', 'rue' => '1 rue des Luthiers', 'codePostal' => '75001', 'ville' => 'Paris', 'pays' => 'FR'];
    }

    /**
     * @param array<string, mixed>|null $corps
     * @param array<string, string>     $entetes
     *
     * @return array<mixed>
     */
    private function requete(string $methode, string $url, ?array $corps = null, array $entetes = []): array
    {
        $this->client->request($methode, $url, server: array_filter([
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => null === $corps ? null : 'application/json',
        ]) + $entetes, content: null === $corps ? null : json_encode($corps, \JSON_THROW_ON_ERROR));

        return json_decode((string) $this->client->getResponse()->getContent() ?: '[]', true, flags: \JSON_THROW_ON_ERROR);
    }

    private function connecter(string $email): void
    {
        $this->client->jsonRequest('POST', '/api/login', ['email' => $email, 'password' => self::MOT_DE_PASSE]);
        self::assertResponseIsSuccessful();
    }

    private function commande(string $numero): Commande
    {
        return $this->entityManager->getRepository(Commande::class)->findOneBy(['numero' => $numero]);
    }

    private function instrumentFrais(string $cle): Instrument
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Instrument::class)->findOneBy(['reference' => self::PREFIXE.strtoupper($cle)]);
    }

    private function compterMouvements(string $cle, TypeMouvementStock $type): int
    {
        return $this->entityManager->getRepository(MouvementStock::class)->count([
            'instrument' => $this->instrumentFrais($cle),
            'type' => $type,
        ]);
    }
}
