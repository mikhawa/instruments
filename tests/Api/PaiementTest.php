<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Categorie;
use App\Entity\CategorieTraduction;
use App\Entity\Commande;
use App\Entity\EvenementWebhook;
use App\Entity\Instrument;
use App\Entity\InstrumentTraduction;
use App\Entity\MouvementStock;
use App\Entity\Paiement;
use App\Entity\Remboursement;
use App\Entity\Utilisateur;
use App\Enum\ClasseLivraison;
use App\Enum\EtatInstrument;
use App\Enum\MotifAnnulation;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutCommande;
use App\Enum\StatutPaiement;
use App\Enum\TypeMouvementStock;
use App\Message\TraiterNotificationPaiement;
use App\MessageHandler\TraiterNotificationPaiementHandler;
use App\Scheduler\Handler\ExpirerCommandesHandler;
use App\Scheduler\Handler\SynchroniserPaiementsHandler;
use App\Scheduler\Message\ExpirerCommandes;
use App\Scheduler\Message\SynchroniserPaiements;
use App\Service\Paiement\EncaissementPaiement;
use App\Service\Paiement\PrestataireIndisponibleException;
use App\Service\Paiement\Simulation\SimulationPrestataire;
use App\Service\Paiement\SynchronisationPaiement;
use App\Service\Paiement\Virement\CommunicationStructuree;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Paiement (phase 3) avec le faux prestataire « simulation » (MOLLIE_API_KEY vide en test) :
 * paiement en ligne, rejeu, échec, montant falsifié, paiement après expiration (reprise ou
 * remboursement), paiement en double, virement SEPA et sa validation dans le back-office,
 * notification Mollie et synchronisation planifiée.
 *
 * Commandes en retrait à l'atelier (pas de frais de port) : total = prix TTC des instruments.
 */
final class PaiementTest extends WebTestCase
{
    private const PREFIXE = 'TST-PAI-';
    private const EMAIL_INVITE = 'invite.paiement@instruments.test';
    private const EMAIL_ADMIN = 'admin.paiement@instruments.test';
    private const MOT_DE_PASSE = 'motdepasse-test';
    private const REFERENCE_MOLLIE = 'tr_TestPaiement01';

    /** clé => [prix HT, stock, pièce unique] ; TVA 21 % */
    private const INSTRUMENTS = [
        'flute' => [20000, 5, false],   // 242,00 € TTC : pas de virement (seuil 500 €)
        'vielle' => [400000, 1, true],  // 4 840,00 € TTC : virement proposé
    ];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $categorie = (new Categorie())->addTraduction((new CategorieTraduction())->setLocale('fr')->setNom('Test paiement')->setSlug('test-paiement'));
        $entityManager->persist($categorie);
        foreach (self::INSTRUMENTS as $cle => [$prixHt, $stock, $pieceUnique]) {
            $instrument = (new Instrument())
                ->setCategorie($categorie)
                ->setReference(self::PREFIXE.strtoupper($cle))
                ->setEtat(EtatInstrument::Neuf)
                ->setPieceUnique($pieceUnique)
                ->setPrixHt($prixHt)
                ->setClasseLivraison(ClasseLivraison::Colis)
                ->setPublished(true)
                ->addTraduction((new InstrumentTraduction())->setLocale('fr')->setNom('Test '.$cle)->setSlug('test-paiement-'.$cle));
            $instrument->getStock()->ajouter($stock);
            $entityManager->persist($instrument);
        }

        $admin = (new Utilisateur())->setEmail(self::EMAIL_ADMIN)->setPrenom('Admin')->setNom('Paiement')->setLocale('fr')->setRoles(['ROLE_ADMIN'])->setVerified(true);
        $admin->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($admin, self::MOT_DE_PASSE));
        $entityManager->persist($admin);

        $entityManager->flush();
    }

    protected function tearDown(): void
    {
        $connexion = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $instruments = 'SELECT id FROM instrument WHERE reference LIKE ?';
        $commandes = "SELECT DISTINCT l.commande_id FROM ligne_commande l WHERE l.instrument_id IN ($instruments)";
        $connexion->executeStatement("DELETE FROM facture WHERE facture_origine_id IS NOT NULL AND commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE FROM facture WHERE commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE r FROM remboursement r JOIN paiement p ON p.id = r.paiement_id WHERE p.commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE FROM paiement WHERE commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement('DELETE FROM evenement_webhook WHERE reference LIKE ?', ['tr_Test%']);
        $connexion->executeStatement("DELETE FROM mouvement_stock WHERE instrument_id IN ($instruments)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE c FROM commande c JOIN ligne_commande l ON l.commande_id = c.id WHERE l.instrument_id IN ($instruments)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE p FROM panier p JOIN ligne_panier l ON l.panier_id = p.id WHERE l.instrument_id IN ($instruments)", [self::PREFIXE.'%']);
        $connexion->executeStatement('DELETE FROM instrument WHERE reference LIKE ?', [self::PREFIXE.'%']);
        $connexion->executeStatement('DELETE c FROM categorie c JOIN categorie_traduction t ON t.categorie_id = c.id WHERE t.slug = ?', ['test-paiement']);
        $connexion->executeStatement('DELETE FROM utilisateur WHERE email = ?', [self::EMAIL_ADMIN]);

        parent::tearDown();
    }

    public function testPaiementEnLigneReussi(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton, 'moyensPaiement' => $moyens] = $this->commanderPour('flute');
        self::assertSame(['en_ligne'], $moyens, 'Moins de 500 € : pas de virement.');

        $paiement = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['ouvert', 'en_ligne', 24200], [$paiement['statut'], $paiement['type'], $paiement['montant']]);
        self::assertMatchesRegularExpression('#/paiement/simulation/sim_[0-9a-f]{20}$#', $paiement['urlPaiement']);

        // Double clic : même page de paiement, pas de second paiement
        $encore = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne']);
        self::assertSame($paiement['urlPaiement'], $encore['urlPaiement']);
        self::assertCount(1, $this->commande($numero)->getPaiements());

        $retour = $this->choisirIssue($paiement['urlPaiement'], 'paye');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("/commande/confirmation/$numero?jeton=$jeton&retour=1", $retour['urlRetour']);

        $vue = $this->requete('GET', "/api/commandes/$numero?jeton=$jeton");
        self::assertSame('payee', $vue['statut']);
        self::assertNotNull($vue['paidAt']);
        self::assertSame('paye', $vue['paiement']['statut']);
        self::assertSame([], $vue['moyensPaiement']);
        self::assertArrayNotHasKey('expireAt', $vue, 'Plus de réservation une fois payée.');
        self::assertSame([], $this->requete('GET', '/api/panier')['lignes'], 'Les instruments payés quittent le panier.');

        // Plus rien à payer
        $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne']);
        self::assertResponseStatusCodeSame(409);
        $this->choisirIssue($paiement['urlPaiement'], 'paye');
        self::assertResponseStatusCodeSame(409);
    }

    public function testRejeuSansEffet(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderPour('flute');
        $url = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne'])['urlPaiement'];
        $this->choisirIssue($url, 'paye');

        // Notification rejouée, synchronisation planifiée, retour du client : un seul effet
        $paiement = $this->commande($numero)->getDernierPaiement();
        self::assertSame(EncaissementPaiement::AUCUN, static::getContainer()->get(SynchronisationPaiement::class)->synchroniser($paiement));
        $this->requete('POST', "/api/commandes/$numero/verification-paiement?jeton=$jeton");
        self::assertResponseIsSuccessful();

        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::Payee, $commande->getStatut());
        self::assertSame(0, $this->entites(Remboursement::class)->count([]));
        self::assertSame(1, $this->compterMouvements('flute', TypeMouvementStock::Reservation));
    }

    public function testAccesRefuseSansJeton(): void
    {
        ['numero' => $numero] = $this->commanderPour('flute');

        $this->client->getCookieJar()->clear();
        $this->requete('POST', "/api/commandes/$numero/paiements", ['moyen' => 'en_ligne']);
        self::assertResponseStatusCodeSame(404);
        $this->requete('POST', "/api/commandes/$numero/paiements?jeton=00000000-0000-4000-8000-000000000000", ['moyen' => 'en_ligne']);
        self::assertResponseStatusCodeSame(404);
        $this->requete('POST', "/api/commandes/$numero/verification-paiement");
        self::assertResponseStatusCodeSame(404);
    }

    public function testEchecPuisNouvelEssai(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderPour('flute');
        $url = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne'])['urlPaiement'];
        $this->choisirIssue($url, 'echoue');

        $vue = $this->requete('GET', "/api/commandes/$numero?jeton=$jeton");
        self::assertSame('en_attente_paiement', $vue['statut']);
        self::assertSame('echoue', $vue['paiement']['statut']);
        self::assertArrayNotHasKey('urlPaiement', $vue['paiement']);
        self::assertSame(['en_ligne'], $vue['moyensPaiement']);

        $nouveau = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne']);
        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($url, $nouveau['urlPaiement']);
        $this->choisirIssue($nouveau['urlPaiement'], 'paye');
        self::assertSame(StatutCommande::Payee, $this->commande($numero)->getStatut());
    }

    public function testMontantFalsifieNonApplique(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderPour('flute');
        $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne']);

        $paiement = $this->commande($numero)->getDernierPaiement();
        $paiement->ajouterDonnees([SimulationPrestataire::ISSUE => 'paye', SimulationPrestataire::MONTANT => 100]);
        $this->entityManager()->flush();

        self::assertSame(EncaissementPaiement::ANOMALIE, static::getContainer()->get(SynchronisationPaiement::class)->synchroniser($paiement));
        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::EnAttentePaiement, $commande->getStatut());
        self::assertNull($commande->getPaidAt());
        self::assertStringContainsString('Montant encaissé 100 EUR, attendu 24200 EUR', $commande->getDernierPaiement()->getDonnees()['anomalie']);
    }

    public function testPaiementApresExpirationRepris(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderPour('flute');
        $url = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne'])['urlPaiement'];
        $this->expirer($numero);
        self::assertSame(0, $this->instrument('flute')->getStock()->getQuantiteReservee());

        // Le client termine son paiement trop tard : le stock suffit encore, la commande est reprise
        $this->choisirIssue($url, 'paye');

        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::Payee, $commande->getStatut());
        self::assertNotNull($commande->getPaidAt());
        self::assertNull($commande->getCancelledAt());
        self::assertNull($commande->getMotifAnnulation());
        self::assertSame(1, $this->instrument('flute')->getStock()->getQuantiteReservee());
        self::assertSame(2, $this->compterMouvements('flute', TypeMouvementStock::Reservation));
        self::assertSame(1, $this->compterMouvements('flute', TypeMouvementStock::Liberation));
    }

    public function testPaiementApresExpirationRembourseSansStock(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderPour('vielle');
        $url = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne'])['urlPaiement'];
        $this->expirer($numero);

        // Entre-temps, un autre client réserve la pièce unique
        $this->client->getCookieJar()->clear();
        $autre = $this->commanderPour('vielle');

        $this->choisirIssue($url, 'paye');

        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::Remboursee, $commande->getStatut());
        self::assertSame(MotifAnnulation::Expiree, $commande->getMotifAnnulation());
        $paiement = $commande->getDernierPaiement();
        self::assertSame(StatutPaiement::Rembourse, $paiement->getStatut());
        self::assertSame(484000, $paiement->getMontantRembourse());
        $remboursement = $paiement->getRemboursements()->first();
        self::assertSame([Remboursement::STATUT_EFFECTUE, 484000], [$remboursement->getStatut(), $remboursement->getMontant()]);
        self::assertSame(1, $this->instrument('vielle')->getStock()->getQuantiteReservee(), 'Seule la commande de l\'autre client réserve la pièce.');
        self::assertSame(StatutCommande::EnAttentePaiement, $this->commande($autre['numero'])->getStatut());
    }

    public function testPaiementEnDoubleRembourse(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderPour('flute');
        $url = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne'])['urlPaiement'];

        // Seconde page de paiement ouverte (autre onglet), payée elle aussi
        $commande = $this->commande($numero);
        $double = (new Paiement($commande, PrestatairePaiement::Simulation, $commande->getTotalTtc()))->ouvrir('sim_'.str_repeat('d', 20), 'http://localhost/');
        $this->entityManager()->persist($double);
        $this->entityManager()->flush();

        $this->choisirIssue($url, 'paye');
        $this->choisirIssue('/sim_'.str_repeat('d', 20), 'paye');

        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::Payee, $commande->getStatut());
        $paiements = $commande->getPaiements()->toArray();
        self::assertSame([StatutPaiement::Paye, StatutPaiement::Rembourse], array_map(static fn (Paiement $p) => $p->getStatut(), $paiements));
        self::assertStringContainsString('Paiement en double', $paiements[1]->getRemboursements()->first()->getMotif());
    }

    public function testVirementPuisValidationDansLeBackOffice(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton, 'moyensPaiement' => $moyens] = $this->commanderPour('vielle');
        self::assertSame(['en_ligne', 'virement'], $moyens);

        $paiement = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'virement']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['en_attente', 'virement'], [$paiement['statut'], $paiement['type']]);
        $virement = $paiement['virement'];
        self::assertTrue(CommunicationStructuree::estValide($virement['communication']));
        self::assertSame('BE68 5390 0754 7034', $virement['iban']);
        self::assertStringStartsWith("BCD\n002\n1\nSCT\nGEBABEBB\n", $virement['qrEpc']);
        self::assertStringEndsWith("\nBE68539007547034\nEUR4840.00\n\n\n".$virement['communication'], $virement['qrEpc']);

        // Réservation prolongée (au moins 5 jours) ; une seconde demande renvoie le même virement
        $commande = $this->commande($numero);
        self::assertGreaterThan(new \DateTimeImmutable('+5 days'), $commande->getExpireAt());
        $encore = $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'virement']);
        self::assertSame($virement['communication'], $encore['virement']['communication']);
        self::assertCount(1, $this->commande($numero)->getPaiements());

        // L'administrateur constate le virement sur le compte
        $this->client->request('GET', '/admin/login');
        $this->client->submitForm('Se connecter', ['email' => self::EMAIL_ADMIN, 'password' => self::MOT_DE_PASSE]);
        $crawler = $this->client->request('GET', '/admin/commande/'.$commande->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $virement['communication']);
        $this->client->submit($crawler->filter('form[action*="virement-recu"]')->form());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'payée par virement');

        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::Payee, $commande->getStatut());
        self::assertSame(StatutPaiement::Paye, $commande->getDernierPaiement()->getStatut());
        $this->client->request('GET', '/admin/paiement');
        self::assertResponseIsSuccessful();
    }

    public function testVirementRefuseSousLeSeuil(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderPour('flute');

        $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'virement']);
        self::assertResponseStatusCodeSame(409);
        $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'cheque']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testCommandeExpireeNonPayable(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderPour('flute');
        $this->entityManager()->getConnection()->executeStatement('UPDATE commande SET expire_at = ? WHERE numero = ?', ['2020-01-01 00:00:00', $numero]);

        $vue = $this->requete('GET', "/api/commandes/$numero?jeton=$jeton");
        self::assertSame([], $vue['moyensPaiement']);
        $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne']);
        self::assertResponseStatusCodeSame(409);
    }

    public function testNotificationMollie(): void
    {
        ['numero' => $numero] = $this->commanderPour('flute');

        // Identifiant inconnu ou mal formé : 200 sans rien enregistrer
        $this->client->request('POST', '/api/webhooks/mollie', ['id' => 'tr_Inconnu123']);
        self::assertResponseStatusCodeSame(200);
        $this->client->request('POST', '/api/webhooks/mollie', ['id' => '<script>']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $this->entites(EvenementWebhook::class)->count([]));

        $commande = $this->commande($numero);
        $paiement = (new Paiement($commande, PrestatairePaiement::Mollie, $commande->getTotalTtc()))->ouvrir(self::REFERENCE_MOLLIE, 'https://www.mollie.com/checkout/test');
        $this->entityManager()->persist($paiement);
        $this->entityManager()->flush();

        $this->client->request('POST', '/api/webhooks/mollie', ['id' => self::REFERENCE_MOLLIE]);
        self::assertResponseStatusCodeSame(200);
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = array_values(array_filter(array_map(static fn ($enveloppe) => $enveloppe->getMessage(), $transport->getSent()), static fn ($m): bool => $m instanceof TraiterNotificationPaiement));
        self::assertCount(1, $messages);

        // Traitement en arrière-plan : sans clé Mollie (tests), l'échec est noté et l'exception relancée pour un nouvel essai
        try {
            static::getContainer()->get(TraiterNotificationPaiementHandler::class)($messages[0]);
            self::fail('Une panne du prestataire doit être relancée.');
        } catch (PrestataireIndisponibleException) {
        }
        $evenement = $this->entites(EvenementWebhook::class)->findOneBy(['reference' => self::REFERENCE_MOLLIE]);
        self::assertNull($evenement->getTraiteAt());
        self::assertStringContainsString('MOLLIE_API_KEY', (string) $evenement->getErreur());
        self::assertSame(StatutCommande::EnAttentePaiement, $this->commande($numero)->getStatut());
    }

    public function testSynchronisationPlanifieeRattrapeUneNotificationPerdue(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderPour('flute');
        $this->requete('POST', "/api/commandes/$numero/paiements?jeton=$jeton", ['moyen' => 'en_ligne']);

        // Payé chez le prestataire, mais la notification n'est jamais arrivée
        $paiement = $this->commande($numero)->getDernierPaiement();
        $paiement->ajouterDonnees([SimulationPrestataire::ISSUE => 'paye']);
        $this->entityManager()->flush();
        $this->entityManager()->getConnection()->executeStatement('UPDATE paiement SET created_at = ? WHERE id = ?', [(new \DateTimeImmutable('-10 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'), $paiement->getId()]);

        self::assertGreaterThanOrEqual(1, static::getContainer()->get(SynchroniserPaiementsHandler::class)(new SynchroniserPaiements()));
        self::assertSame(StatutCommande::Payee, $this->commande($numero)->getStatut());
    }

    public function testDatesRelues(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton, 'expireAt' => $expireAt] = $this->commanderPour('flute');

        // Relue en base, la date garde le même instant (stockage en UTC, quel que soit le fuseau de PHP)
        $relue = $this->requete('GET', "/api/commandes/$numero?jeton=$jeton")['expireAt'];
        self::assertSame((new \DateTimeImmutable($expireAt))->getTimestamp(), (new \DateTimeImmutable($relue))->getTimestamp());
        self::assertGreaterThan(new \DateTimeImmutable('+25 minutes'), new \DateTimeImmutable($relue));
    }

    /**
     * Ajoute l'instrument au panier (nouveau visiteur ou même cookie) et commande en retrait à l'atelier.
     *
     * @return array<string, mixed>
     */
    private function commanderPour(string $cle): array
    {
        $this->requete('POST', '/api/panier/lignes', ['instrument' => '/api/instruments/'.$this->instrument($cle)->getId(), 'quantite' => 1]);
        self::assertResponseIsSuccessful();
        $total = $this->requete('GET', '/api/panier')['totalTtc'];

        $commande = $this->requete('POST', '/api/commandes', [
            'email' => self::EMAIL_INVITE,
            'adresseFacturation' => ['prenom' => 'Ana', 'nom' => 'Test', 'rue' => '1 rue des Luthiers', 'codePostal' => '1000', 'ville' => 'Bruxelles', 'pays' => 'BE'],
            'modeLivraison' => 'retrait_atelier',
            'accepteCgv' => true,
            'totalTtcAttendu' => $total,
        ]);
        self::assertResponseStatusCodeSame(201);

        return $commande;
    }

    /**
     * Issue choisie sur la page de paiement simulée (comme le client chez Mollie).
     *
     * @return array<mixed>
     */
    private function choisirIssue(string $urlPaiement, string $issue): array
    {
        return $this->requete('POST', '/api/paiements/simulation/'.basename($urlPaiement), ['issue' => $issue]);
    }

    private function expirer(string $numero): void
    {
        $this->entityManager()->getConnection()->executeStatement('UPDATE commande SET expire_at = ? WHERE numero = ?', ['2020-01-01 00:00:00', $numero]);
        static::getContainer()->get(ExpirerCommandesHandler::class)(new ExpirerCommandes());
        self::assertSame(StatutCommande::Annulee, $this->commande($numero)->getStatut());
    }

    /**
     * @param array<string, mixed>|null $corps
     *
     * @return array<mixed>
     */
    private function requete(string $methode, string $url, ?array $corps = null): array
    {
        $this->client->request($methode, $url, server: array_filter([
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => null === $corps ? null : 'application/json',
        ]), content: null === $corps ? null : json_encode($corps, \JSON_THROW_ON_ERROR));

        return json_decode((string) $this->client->getResponse()->getContent() ?: '[]', true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Gestionnaire d'entités du noyau courant (le client de test redémarre le noyau à chaque requête).
     */
    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $classe
     *
     * @return \Doctrine\ORM\EntityRepository<T>
     */
    private function entites(string $classe): \Doctrine\ORM\EntityRepository
    {
        return $this->entityManager()->getRepository($classe);
    }

    private function commande(string $numero): Commande
    {
        $this->entityManager()->clear();

        return $this->entites(Commande::class)->findOneBy(['numero' => $numero]);
    }

    private function instrument(string $cle): Instrument
    {
        $this->entityManager()->clear();

        return $this->entites(Instrument::class)->findOneBy(['reference' => self::PREFIXE.strtoupper($cle)]);
    }

    private function compterMouvements(string $cle, TypeMouvementStock $type): int
    {
        return $this->entites(MouvementStock::class)->count(['instrument' => $this->instrument($cle), 'type' => $type]);
    }
}
